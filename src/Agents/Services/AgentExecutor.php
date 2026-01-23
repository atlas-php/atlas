<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Services;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Exceptions\AgentException;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Foundation\Services\PipelineRunner;
use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;
use Atlasphp\Atlas\Providers\Services\ProviderConfigService;
use Atlasphp\Atlas\Providers\Services\UsageExtractorRegistry;
use Atlasphp\Atlas\Streaming\Events\ErrorEvent;
use Atlasphp\Atlas\Streaming\Events\StreamEndEvent;
use Atlasphp\Atlas\Streaming\Events\StreamStartEvent;
use Atlasphp\Atlas\Streaming\Events\TextDeltaEvent;
use Atlasphp\Atlas\Streaming\Events\ToolCallEndEvent;
use Atlasphp\Atlas\Streaming\Events\ToolCallStartEvent;
use Atlasphp\Atlas\Streaming\StreamEvent;
use Atlasphp\Atlas\Streaming\StreamResponse;
use Atlasphp\Atlas\Tools\Services\ToolBuilder;
use Atlasphp\Atlas\Tools\Support\ToolContext;
use Generator;
use Prism\Prism\Contracts\Schema;
use Prism\Prism\Enums\StructuredMode;
use Prism\Prism\Streaming\Events\ErrorEvent as PrismErrorEvent;
use Prism\Prism\Streaming\Events\StreamEndEvent as PrismStreamEndEvent;
use Prism\Prism\Streaming\Events\StreamEvent as PrismStreamEvent;
use Prism\Prism\Streaming\Events\StreamStartEvent as PrismStreamStartEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent as PrismTextDeltaEvent;
use Prism\Prism\Streaming\Events\ToolCallEvent as PrismToolCallEvent;
use Prism\Prism\Streaming\Events\ToolResultEvent as PrismToolResultEvent;
use Prism\Prism\ValueObjects\ProviderTool;
use Throwable;

/**
 * Executes agents using the Prism AI abstraction layer.
 *
 * Orchestrates system prompt building, tool preparation, and
 * Prism request execution with pipeline middleware support.
 * Supports both blocking and streaming execution modes.
 */
class AgentExecutor implements AgentExecutorContract
{
    public function __construct(
        protected PrismBuilderContract $prismBuilder,
        protected ToolBuilder $toolBuilder,
        protected SystemPromptBuilder $systemPromptBuilder,
        protected PipelineRunner $pipelineRunner,
        protected UsageExtractorRegistry $usageExtractor,
        protected ProviderConfigService $configService,
    ) {}

    /**
     * Execute an agent with the given input.
     *
     * @param  AgentContract  $agent  The agent to execute.
     * @param  string  $input  The user input message.
     * @param  ExecutionContext|null  $context  Optional execution context.
     * @param  Schema|null  $schema  Optional schema for structured output.
     * @param  array{0: array<int, int>|int, 1: \Closure|int, 2: callable|null, 3: bool}|null  $retry  Optional retry configuration.
     * @param  StructuredMode|null  $structuredMode  Optional mode for structured output (Auto, Structured, Json).
     */
    public function execute(
        AgentContract $agent,
        string $input,
        ?ExecutionContext $context = null,
        ?Schema $schema = null,
        ?array $retry = null,
        ?StructuredMode $structuredMode = null,
    ): AgentResponse {
        $context = $context ?? new ExecutionContext;
        $systemPrompt = null;
        $retry = $retry ?? $this->configService->getRetryConfig();

        try {
            // Prepare the execution (shared with streaming)
            $prepared = $this->prepareExecution($agent, $input, $context, $schema, $retry);
            $agent = $prepared['agent'];
            $input = $prepared['input'];
            $context = $prepared['context'];
            $schema = $prepared['schema'];
            $systemPrompt = $prepared['systemPrompt'];
            $request = $prepared['request'];

            // Execute the request
            $response = $schema !== null
                ? $this->executeStructuredRequest($agent, $input, $context, $systemPrompt, $schema, $retry, $structuredMode)
                : $this->executeTextRequestWithPreparedRequest($request, $agent);

            // Run after_execute pipeline
            $afterData = $this->pipelineRunner->runIfActive('agent.after_execute', [
                'agent' => $agent,
                'input' => $input,
                'context' => $context,
                'response' => $response,
                'system_prompt' => $systemPrompt,
            ]);

            /** @var AgentResponse $response */
            $response = $afterData['response'];

            return $response;
        } catch (AgentException $e) {
            $this->handleError($agent, $input, $context, $systemPrompt, $e);
            throw $e;
        } catch (Throwable $e) {
            $this->handleError($agent, $input, $context, $systemPrompt, $e);
            throw AgentException::executionFailed($agent->key(), $e->getMessage(), $e);
        }
    }

    /**
     * Stream a response from an agent.
     *
     * Uses the same preparation as execute() but returns events via generator.
     *
     * @param  AgentContract  $agent  The agent to execute.
     * @param  string  $input  The user input message.
     * @param  ExecutionContext|null  $context  Optional execution context.
     * @param  array{0: array<int, int>|int, 1: \Closure|int, 2: callable|null, 3: bool}|null  $retry  Optional retry configuration.
     */
    public function stream(
        AgentContract $agent,
        string $input,
        ?ExecutionContext $context = null,
        ?array $retry = null,
    ): StreamResponse {
        $context = $context ?? new ExecutionContext;
        $retry = $retry ?? $this->configService->getRetryConfig();

        return new StreamResponse(
            $this->createStreamGenerator($agent, $input, $context, $retry)
        );
    }

    /**
     * Prepare execution by running before pipeline, building system prompt, and building request.
     *
     * Shared between execute() and stream() to avoid duplication.
     *
     * @param  array{0: array<int, int>|int, 1: \Closure|int, 2: callable|null, 3: bool}|null  $retry  Optional retry configuration.
     * @return array{agent: AgentContract, input: string, context: ExecutionContext, schema: Schema|null, systemPrompt: string, request: mixed}
     */
    protected function prepareExecution(
        AgentContract $agent,
        string $input,
        ExecutionContext $context,
        ?Schema $schema = null,
        ?array $retry = null,
    ): array {
        // Run before_execute pipeline (used by both execute and stream)
        $beforeData = $this->pipelineRunner->runIfActive('agent.before_execute', [
            'agent' => $agent,
            'input' => $input,
            'context' => $context,
            'schema' => $schema,
        ]);

        /** @var AgentContract $agent */
        $agent = $beforeData['agent'];
        /** @var string $input */
        $input = $beforeData['input'];
        /** @var ExecutionContext $context */
        $context = $beforeData['context'];
        /** @var Schema|null $schema */
        $schema = $beforeData['schema'] ?? null;

        // Build system prompt
        $systemPrompt = $this->systemPromptBuilder->build($agent, $context);

        // Build request (for text/stream - structured has its own path)
        $request = $schema === null
            ? $this->buildTextRequest($agent, $input, $context, $systemPrompt, $retry)
            : null;

        return [
            'agent' => $agent,
            'input' => $input,
            'context' => $context,
            'schema' => $schema,
            'systemPrompt' => $systemPrompt,
            'request' => $request,
        ];
    }

    /**
     * Create the generator that produces stream events.
     *
     * @param  array{0: array<int, int>|int, 1: \Closure|int, 2: callable|null, 3: bool}|null  $retry  Optional retry configuration.
     * @return Generator<int, StreamEvent>
     */
    protected function createStreamGenerator(
        AgentContract $agent,
        string $input,
        ExecutionContext $context,
        ?array $retry = null,
    ): Generator {
        $systemPrompt = null;

        try {
            // Use shared preparation
            $prepared = $this->prepareExecution($agent, $input, $context, null, $retry);
            $agent = $prepared['agent'];
            $input = $prepared['input'];
            $context = $prepared['context'];
            $systemPrompt = $prepared['systemPrompt'];
            $request = $prepared['request'];

            // Get Prism stream and convert to Atlas events
            /** @var Generator<PrismStreamEvent> $prismStream */
            $prismStream = $request->asStream();

            foreach ($prismStream as $prismEvent) {
                $atlasEvent = $this->convertStreamEvent($prismEvent);

                if ($atlasEvent !== null) {
                    // Run stream.on_event pipeline (streaming-specific)
                    $this->pipelineRunner->runIfActive('stream.on_event', [
                        'event' => $atlasEvent,
                        'agent' => $agent,
                        'context' => $context,
                    ]);

                    yield $atlasEvent;
                }
            }

            // Run stream.after_complete pipeline (streaming-specific, called when stream ends)
            $this->pipelineRunner->runIfActive('stream.after_complete', [
                'agent' => $agent,
                'input' => $input,
                'context' => $context,
                'system_prompt' => $systemPrompt,
            ]);

        } catch (AgentException $e) {
            $this->handleError($agent, $input, $context, $systemPrompt, $e);

            yield $this->createErrorEvent($e);

            throw $e;
        } catch (Throwable $e) {
            $this->handleError($agent, $input, $context, $systemPrompt, $e);

            yield $this->createErrorEvent($e);

            throw AgentException::executionFailed($agent->key(), $e->getMessage(), $e);
        }
    }

    /**
     * Handle an error by running the error pipeline.
     */
    protected function handleError(
        AgentContract $agent,
        string $input,
        ExecutionContext $context,
        ?string $systemPrompt,
        Throwable $exception,
    ): void {
        $this->pipelineRunner->runIfActive('agent.on_error', [
            'agent' => $agent,
            'input' => $input,
            'context' => $context,
            'system_prompt' => $systemPrompt,
            'exception' => $exception,
        ]);
    }

    /**
     * Execute a text request with a prepared Prism request.
     */
    protected function executeTextRequestWithPreparedRequest(mixed $request, AgentContract $agent): AgentResponse
    {
        $prismResponse = $request->asText();

        return $this->buildResponse($agent, $prismResponse);
    }

    /**
     * Build a text request for the agent.
     *
     * @param  array{0: array<int, int>|int, 1: \Closure|int, 2: callable|null, 3: bool}|null  $retry  Optional retry configuration.
     * @return mixed The configured Prism pending request.
     */
    protected function buildTextRequest(
        AgentContract $agent,
        string $input,
        ExecutionContext $context,
        string $systemPrompt,
        ?array $retry = null,
    ): mixed {
        $toolContext = new ToolContext($context->metadata);
        $tools = $this->toolBuilder->buildForAgent($agent, $toolContext);

        // Use context overrides if present, otherwise fall back to agent config
        $provider = $context->providerOverride ?? $agent->provider();
        $model = $context->modelOverride ?? $agent->model();

        $request = $context->hasMessages()
            ? $this->prismBuilder->forMessages(
                $provider,
                $model,
                $this->buildMessages($context, $input),
                $systemPrompt,
                $tools,
                $retry,
            )
            : $this->prismBuilder->forPrompt(
                $provider,
                $model,
                $input,
                $systemPrompt,
                $tools,
                $retry,
                $context->currentAttachments,
            );

        return $this->applyAgentSettings($request, $agent);
    }

    /**
     * Execute a structured output request.
     *
     * @param  array{0: array<int, int>|int, 1: \Closure|int, 2: callable|null, 3: bool}|null  $retry  Optional retry configuration.
     * @param  StructuredMode|null  $structuredMode  Optional mode for structured output.
     */
    protected function executeStructuredRequest(
        AgentContract $agent,
        string $input,
        ExecutionContext $context,
        string $systemPrompt,
        Schema $schema,
        ?array $retry = null,
        ?StructuredMode $structuredMode = null,
    ): AgentResponse {
        // Use context overrides if present, otherwise fall back to agent config
        $provider = $context->providerOverride ?? $agent->provider();
        $model = $context->modelOverride ?? $agent->model();

        $request = $this->prismBuilder->forStructured(
            $provider,
            $model,
            $schema,
            $context->hasMessages() ? $this->combineMessagesWithInput($context, $input) : $input,
            $systemPrompt,
            $retry,
            $structuredMode,
        );

        $request = $this->applyAgentSettings($request, $agent);
        $prismResponse = $request->asStructured();

        return $this->buildStructuredResponse($agent, $prismResponse);
    }

    /**
     * Build the messages array for multi-turn conversation.
     *
     * @return array<int, array{role: string, content: string, attachments?: array<int, array{type: string, source: string, data: string, mime_type?: string|null, title?: string|null, disk?: string|null}>}>
     */
    protected function buildMessages(ExecutionContext $context, string $input): array
    {
        $messages = $context->messages;

        $currentMessage = ['role' => 'user', 'content' => $input];

        if ($context->hasCurrentAttachments()) {
            $currentMessage['attachments'] = $context->currentAttachments;
        }

        $messages[] = $currentMessage;

        return $messages;
    }

    /**
     * Combine existing messages with new input for structured requests.
     */
    protected function combineMessagesWithInput(ExecutionContext $context, string $input): string
    {
        $parts = [];

        foreach ($context->messages as $message) {
            $role = ucfirst($message['role']);
            $parts[] = "{$role}: {$message['content']}";
        }

        $parts[] = "User: {$input}";

        return implode("\n\n", $parts);
    }

    /**
     * Apply agent settings to the Prism request.
     *
     * @param  mixed  $request  The Prism pending request.
     * @return mixed The modified request.
     */
    protected function applyAgentSettings(mixed $request, AgentContract $agent): mixed
    {
        if ($agent->temperature() !== null) {
            $request = $request->withTemperature($agent->temperature());
        }

        if ($agent->maxTokens() !== null) {
            $request = $request->withMaxTokens($agent->maxTokens());
        }

        if ($agent->maxSteps() !== null) {
            $request = $request->withMaxSteps($agent->maxSteps());
        }

        $providerTools = $this->buildProviderTools($agent->providerTools());
        if ($providerTools !== []) {
            $request = $request->withProviderTools($providerTools);
        }

        return $request;
    }

    /**
     * Convert agent provider tool definitions to Prism ProviderTool objects.
     *
     * @param  array<int, string|array<string, mixed>|ProviderTool>  $tools
     * @return array<int, ProviderTool>
     */
    protected function buildProviderTools(array $tools): array
    {
        $providerTools = [];

        foreach ($tools as $index => $tool) {
            if ($tool instanceof ProviderTool) {
                $providerTools[] = $tool;

                continue;
            }

            if (is_string($tool)) {
                $providerTools[] = new ProviderTool(type: $tool);

                continue;
            }

            if (isset($tool['type'])) {
                $type = $tool['type'];
                $name = $tool['name'] ?? null;
                unset($tool['type'], $tool['name']);

                $providerTools[] = new ProviderTool(
                    type: $type,
                    name: $name,
                    options: $tool,
                );

                continue;
            }

            throw new \InvalidArgumentException(
                sprintf('Invalid provider tool format at index %d. Array must have a "type" key.', $index)
            );
        }

        return $providerTools;
    }

    /**
     * Build an AgentResponse from the Prism response.
     */
    protected function buildResponse(AgentContract $agent, mixed $prismResponse): AgentResponse
    {
        $usage = $this->usageExtractor->extract($agent->provider(), $prismResponse);
        $toolCalls = $this->extractToolCalls($prismResponse);

        return new AgentResponse(
            text: $prismResponse->text ?? null,
            toolCalls: $toolCalls,
            usage: $usage,
            metadata: ['finish_reason' => $prismResponse->finishReason->value ?? null],
        );
    }

    /**
     * Extract tool calls from the Prism response.
     *
     * @return array<int, array{id: string|null, name: string, arguments: array<string, mixed>, result: string|null}>
     */
    protected function extractToolCalls(mixed $prismResponse): array
    {
        $toolCalls = [];

        if (property_exists($prismResponse, 'steps') && $prismResponse->steps) {
            foreach ($prismResponse->steps as $step) {
                if (isset($step->toolCalls) && ! empty($step->toolCalls)) {
                    foreach ($step->toolCalls as $i => $call) {
                        $result = $step->toolResults[$i]->result ?? null;
                        $toolCalls[] = [
                            'id' => $call->id ?? null,
                            'name' => $call->name,
                            'arguments' => $call->arguments(),
                            'result' => $result,
                        ];
                    }
                }
            }
        }

        if (empty($toolCalls) && property_exists($prismResponse, 'toolCalls') && $prismResponse->toolCalls) {
            foreach ($prismResponse->toolCalls as $call) {
                $toolCalls[] = [
                    'id' => $call->id ?? null,
                    'name' => $call->name,
                    'arguments' => $call->arguments(),
                    'result' => null,
                ];
            }
        }

        return $toolCalls;
    }

    /**
     * Build an AgentResponse for structured output.
     */
    protected function buildStructuredResponse(AgentContract $agent, mixed $prismResponse): AgentResponse
    {
        $usage = $this->usageExtractor->extract($agent->provider(), $prismResponse);

        return new AgentResponse(
            structured: $prismResponse->structured ?? null,
            usage: $usage,
            metadata: ['finish_reason' => $prismResponse->finishReason->value ?? null],
        );
    }

    /**
     * Convert a Prism stream event to an Atlas stream event.
     */
    protected function convertStreamEvent(PrismStreamEvent $event): ?StreamEvent
    {
        return match (true) {
            $event instanceof PrismStreamStartEvent => new StreamStartEvent(
                id: $event->id,
                timestamp: $event->timestamp,
                model: $event->model,
                provider: $event->provider,
            ),
            $event instanceof PrismTextDeltaEvent => new TextDeltaEvent(
                id: $event->id,
                timestamp: $event->timestamp,
                text: $event->delta,
            ),
            $event instanceof PrismToolCallEvent => new ToolCallStartEvent(
                id: $event->id,
                timestamp: $event->timestamp,
                toolId: $event->toolCall->id ?? uniqid('tool_'),
                toolName: $event->toolCall->name,
                arguments: $event->toolCall->arguments(),
            ),
            $event instanceof PrismToolResultEvent => new ToolCallEndEvent(
                id: $event->id,
                timestamp: $event->timestamp,
                toolId: $event->toolResult->toolCallId,
                toolName: $event->toolResult->toolName,
                result: $event->toolResult->result,
                success: $event->success,
            ),
            $event instanceof PrismStreamEndEvent => new StreamEndEvent(
                id: $event->id,
                timestamp: $event->timestamp,
                finishReason: $event->finishReason->value ?? null,
                usage: $this->extractStreamUsage($event),
            ),
            $event instanceof PrismErrorEvent => new ErrorEvent(
                id: $event->id,
                timestamp: $event->timestamp,
                errorType: $event->errorType,
                message: $event->message,
                recoverable: $event->recoverable,
            ),
            default => null,
        };
    }

    /**
     * Extract usage statistics from the StreamEndEvent.
     *
     * @return array<string, int>
     */
    protected function extractStreamUsage(PrismStreamEndEvent $event): array
    {
        if ($event->usage === null) {
            return [];
        }

        return [
            'prompt_tokens' => $event->usage->promptTokens,
            'completion_tokens' => $event->usage->completionTokens,
            'total_tokens' => $event->usage->promptTokens + $event->usage->completionTokens,
        ];
    }

    /**
     * Create an error event from an exception.
     */
    protected function createErrorEvent(Throwable $e): ErrorEvent
    {
        return new ErrorEvent(
            id: uniqid('error_'),
            timestamp: time(),
            errorType: $e instanceof AgentException ? 'agent_error' : 'execution_error',
            message: $e->getMessage(),
            recoverable: false,
        );
    }
}
