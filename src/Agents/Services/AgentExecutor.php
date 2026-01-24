<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Services;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Exceptions\AgentException;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Pipelines\PipelineRunner;
use Atlasphp\Atlas\Tools\Services\ToolBuilder;
use Atlasphp\Atlas\Tools\Support\ToolContext;
use Generator;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Streaming\Events\StreamEvent;
use Prism\Prism\Text\PendingRequest as TextPendingRequest;
use Prism\Prism\Text\Response as PrismResponse;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\ProviderTool;
use Throwable;

/**
 * Executes agents using the Prism AI abstraction layer.
 *
 * Orchestrates system prompt building, tool preparation, and
 * Prism request execution with pipeline middleware support.
 * Supports both blocking and streaming execution modes.
 *
 * All Prism-specific configuration is replayed from context->prismCalls.
 */
class AgentExecutor implements AgentExecutorContract
{
    public function __construct(
        protected ToolBuilder $toolBuilder,
        protected SystemPromptBuilder $systemPromptBuilder,
        protected PipelineRunner $pipelineRunner,
        protected MediaConverter $mediaConverter = new MediaConverter,
    ) {}

    /**
     * Execute an agent with the given input.
     *
     * Returns Prism's Response directly, giving consumers access to:
     * - $response->text - Text response
     * - $response->usage - Full usage stats including cache tokens, thought tokens
     * - $response->steps - Multi-step agentic loop history
     * - $response->toolCalls - Tool calls as ToolCall objects
     * - $response->finishReason - Typed FinishReason enum
     * - $response->meta - Request metadata, rate limits
     */
    public function execute(
        AgentContract $agent,
        string $input,
        ExecutionContext $context,
    ): PrismResponse {
        $systemPrompt = null;

        try {
            // Run before_execute pipeline
            $beforeData = $this->pipelineRunner->runIfActive('agent.before_execute', [
                'agent' => $agent,
                'input' => $input,
                'context' => $context,
            ]);

            /** @var AgentContract $agent */
            $agent = $beforeData['agent'];
            /** @var string $input */
            $input = $beforeData['input'];
            /** @var ExecutionContext $context */
            $context = $beforeData['context'];

            // Build system prompt
            $systemPrompt = $this->systemPromptBuilder->build($agent, $context);

            // Build and execute request
            $request = $this->buildRequest($agent, $input, $context, $systemPrompt);
            $prismResponse = $request->asText();

            // Run after_execute pipeline with Prism response directly
            $afterData = $this->pipelineRunner->runIfActive('agent.after_execute', [
                'agent' => $agent,
                'input' => $input,
                'context' => $context,
                'response' => $prismResponse,
                'system_prompt' => $systemPrompt,
            ]);

            /** @var PrismResponse */
            return $afterData['response'];
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
     * @return Generator<int, StreamEvent>
     */
    public function stream(
        AgentContract $agent,
        string $input,
        ExecutionContext $context,
    ): Generator {
        // Run before_execute pipeline
        $beforeData = $this->pipelineRunner->runIfActive('agent.before_execute', [
            'agent' => $agent,
            'input' => $input,
            'context' => $context,
        ]);

        /** @var AgentContract $agent */
        $agent = $beforeData['agent'];
        /** @var string $input */
        $input = $beforeData['input'];
        /** @var ExecutionContext $context */
        $context = $beforeData['context'];

        // Build system prompt
        $systemPrompt = $this->systemPromptBuilder->build($agent, $context);

        // Build request
        $request = $this->buildRequest($agent, $input, $context, $systemPrompt);

        return $request->asStream();
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
     * Build a Prism request for the agent.
     *
     * Single unified path for all request types. Prism-specific configuration
     * (schema, toolChoice, retry, etc.) is replayed from context->prismCalls.
     */
    protected function buildRequest(
        AgentContract $agent,
        string $input,
        ExecutionContext $context,
        ?string $systemPrompt,
    ): TextPendingRequest {
        // Use context overrides if present, otherwise fall back to agent config
        $provider = $context->providerOverride ?? $agent->provider();
        $model = $context->modelOverride ?? $agent->model();

        if ($provider === null) {
            throw new \InvalidArgumentException('Provider must be specified via agent definition or context override.');
        }

        if ($model === null) {
            throw new \InvalidArgumentException('Model must be specified via agent definition or context override.');
        }

        // Build tools from agent definition
        $toolContext = new ToolContext($context->metadata);
        $tools = $this->toolBuilder->buildForAgent($agent, $toolContext);

        // Create base Prism request
        $request = Prism::text()->using($provider, $model);

        // Add system prompt if present
        if ($systemPrompt !== null && $systemPrompt !== '') {
            $request = $request->withSystemPrompt($systemPrompt);
        }

        // Add tools if present
        if ($tools !== []) {
            $request = $request->withTools($tools);
        }

        // Add messages or prompt
        $request = $context->hasMessages()
            ? $request->withMessages($this->buildMessages($context, $input))
            : $request->withPrompt($input, $this->buildAttachments($context));

        // Apply agent settings (temperature, maxTokens, maxSteps, providerTools)
        $request = $this->applyAgentSettings($request, $agent);

        // Replay captured Prism method calls from context
        foreach ($context->prismCalls as $call) {
            $request = $request->{$call['method']}(...$call['args']);
        }

        return $request;
    }

    /**
     * Build the messages array for multi-turn conversation.
     *
     * @return array<int, UserMessage|AssistantMessage|SystemMessage>
     */
    protected function buildMessages(ExecutionContext $context, string $input): array
    {
        $messages = [];

        // Convert existing messages
        foreach ($context->messages as $message) {
            $messages[] = match ($message['role']) {
                'user' => $this->createUserMessage($message),
                'assistant' => new AssistantMessage($message['content']),
                'system' => new SystemMessage($message['content']),
                default => throw new \InvalidArgumentException(
                    sprintf('Unknown message role: %s. Valid roles are: user, assistant, system.', $message['role'])
                ),
            };
        }

        // Add current input as user message
        $currentMessage = ['role' => 'user', 'content' => $input];
        if ($context->hasCurrentAttachments()) {
            $currentMessage['attachments'] = $context->currentAttachments;
        }
        $messages[] = $this->createUserMessage($currentMessage);

        return $messages;
    }

    /**
     * Build attachments for the current input (prompt mode only).
     *
     * Handles both array format (for serialization/queues) and direct
     * Prism media objects (for direct API access).
     *
     * @return array<int, mixed>
     */
    protected function buildAttachments(ExecutionContext $context): array
    {
        if (! $context->hasCurrentAttachments()) {
            return [];
        }

        $attachments = [];

        // Add direct Prism media objects first
        foreach ($context->prismMedia as $media) {
            $attachments[] = $media;
        }

        // Convert array format attachments if present
        if ($context->currentAttachments !== []) {
            $attachments = array_merge(
                $attachments,
                $this->mediaConverter->convertMany($context->currentAttachments)
            );
        }

        return $attachments;
    }

    /**
     * Create a UserMessage with optional attachments.
     *
     * @param  array{role: string, content: string, attachments?: array<int, array{type: string, source: string, data: string, mime_type?: string|null, title?: string|null, disk?: string|null}>}  $message
     */
    protected function createUserMessage(array $message): UserMessage
    {
        $attachments = $message['attachments'] ?? [];

        if ($attachments === []) {
            return new UserMessage($message['content']);
        }

        $additionalContent = $this->mediaConverter->convertMany($attachments);

        return new UserMessage($message['content'], $additionalContent);
    }

    /**
     * Apply agent settings to the Prism request.
     */
    protected function applyAgentSettings(TextPendingRequest $request, AgentContract $agent): TextPendingRequest
    {
        if ($agent->temperature() !== null) {
            $request = $request->usingTemperature($agent->temperature());
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
}
