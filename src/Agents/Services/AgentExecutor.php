<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Services;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Exceptions\AgentException;
use Atlasphp\Atlas\Agents\Support\AgentContext;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\AgentStreamResponse;
use Atlasphp\Atlas\Pipelines\PipelineRunner;
use Atlasphp\Atlas\Tools\Services\ToolBuilder;
use Atlasphp\Atlas\Tools\Support\ToolContext;
use Generator;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Streaming\Events\StreamEvent;
use Prism\Prism\Structured\PendingRequest as StructuredPendingRequest;
use Prism\Prism\Structured\Response as StructuredResponse;
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
     * Returns AgentResponse wrapping Prism's response with agent context.
     * Backward compatible property access works via __get magic:
     * - $response->text - Text response
     * - $response->usage - Full usage stats including cache tokens, thought tokens
     * - $response->steps - Multi-step agentic loop history
     * - $response->toolCalls - Tool calls as ToolCall objects
     * - $response->finishReason - Typed FinishReason enum
     * - $response->meta - Request metadata, rate limits
     *
     * If withSchema() was called, $response->isStructured() returns true.
     */
    public function execute(
        AgentContract $agent,
        string $input,
        AgentContext $context,
    ): AgentResponse {
        $systemPrompt = null;

        try {
            // Run before_execute pipeline
            $beforeData = $this->pipelineRunner->runWithRuntime(
                'agent.before_execute',
                [
                    'agent' => $agent,
                    'input' => $input,
                    'context' => $context,
                ],
                $context->getMiddlewareFor('agent.before_execute'),
            );

            /** @var AgentContract $agent */
            $agent = $beforeData['agent'];
            /** @var string $input */
            $input = $beforeData['input'];
            /** @var AgentContext $context */
            $context = $beforeData['context'];

            // Run context.validate pipeline for context-specific validation and transformation
            $contextData = $this->pipelineRunner->runWithRuntime(
                'agent.context.validate',
                [
                    'agent' => $agent,
                    'input' => $input,
                    'context' => $context,
                ],
                $context->getMiddlewareFor('agent.context.validate'),
            );

            /** @var AgentContext $context */
            $context = $contextData['context'];

            // Build system prompt
            $systemPrompt = $this->systemPromptBuilder->build($agent, $context);

            // Build request (text or structured based on schema presence)
            $request = $this->buildRequest($agent, $input, $context, $systemPrompt);

            // Execute appropriate terminal method
            $prismResponse = $request instanceof StructuredPendingRequest
                ? $request->asStructured()
                : $request->asText();

            // Run after_execute pipeline with Prism response directly
            $afterData = $this->pipelineRunner->runWithRuntime(
                'agent.after_execute',
                [
                    'agent' => $agent,
                    'input' => $input,
                    'context' => $context,
                    'response' => $prismResponse,
                    'system_prompt' => $systemPrompt,
                ],
                $context->getMiddlewareFor('agent.after_execute'),
            );

            /** @var PrismResponse|StructuredResponse $prismResponse */
            $prismResponse = $afterData['response'];

            return new AgentResponse(
                response: $prismResponse,
                agent: $agent,
                input: $input,
                systemPrompt: $systemPrompt,
                context: $context,
            );
        } catch (AgentException $e) {
            $recovery = $this->handleError($agent, $input, $context, $systemPrompt, $e);
            if ($recovery !== null) {
                return $recovery;
            }
            throw $e;
        } catch (Throwable $e) {
            $recovery = $this->handleError($agent, $input, $context, $systemPrompt, $e);
            if ($recovery !== null) {
                return $recovery;
            }
            throw AgentException::executionFailed($agent->key(), $e->getMessage(), $e);
        }
    }

    /**
     * Stream a response from an agent.
     *
     * Returns AgentStreamResponse which implements IteratorAggregate.
     * Agent context is available before iteration; the stream fires
     * the agent.stream.after pipeline when consumed.
     */
    public function stream(
        AgentContract $agent,
        string $input,
        AgentContext $context,
    ): AgentStreamResponse {
        // Run before_execute pipeline
        $beforeData = $this->pipelineRunner->runWithRuntime(
            'agent.before_execute',
            [
                'agent' => $agent,
                'input' => $input,
                'context' => $context,
            ],
            $context->getMiddlewareFor('agent.before_execute'),
        );

        /** @var AgentContract $agent */
        $agent = $beforeData['agent'];
        /** @var string $input */
        $input = $beforeData['input'];
        /** @var AgentContext $context */
        $context = $beforeData['context'];

        // Run context.validate pipeline for context-specific validation and transformation
        $contextData = $this->pipelineRunner->runWithRuntime(
            'agent.context.validate',
            [
                'agent' => $agent,
                'input' => $input,
                'context' => $context,
            ],
            $context->getMiddlewareFor('agent.context.validate'),
        );

        /** @var AgentContext $context */
        $context = $contextData['context'];

        // Build system prompt
        $systemPrompt = $this->systemPromptBuilder->build($agent, $context);

        // Build request
        $request = $this->buildRequest($agent, $input, $context, $systemPrompt);

        // Wrap generator to fire after pipeline on completion
        $wrappedStream = $this->wrapStreamWithAfterPipeline(
            $request->asStream(),
            $agent,
            $input,
            $context,
            $systemPrompt,
        );

        return new AgentStreamResponse(
            stream: $wrappedStream,
            agent: $agent,
            input: $input,
            systemPrompt: $systemPrompt,
            context: $context,
        );
    }

    /**
     * Wrap a stream generator to fire the after pipeline on completion.
     *
     * @param  Generator<int, StreamEvent>  $stream
     * @return Generator<int, StreamEvent>
     */
    protected function wrapStreamWithAfterPipeline(
        Generator $stream,
        AgentContract $agent,
        string $input,
        AgentContext $context,
        ?string $systemPrompt,
    ): Generator {
        $events = [];
        $error = null;

        try {
            foreach ($stream as $event) {
                $events[] = $event;
                yield $event;
            }
        } catch (Throwable $e) {
            $error = $e;
            $this->handleError($agent, $input, $context, $systemPrompt, $e);
            throw $e;
        } finally {
            // Run after_stream pipeline when streaming completes (success or error)
            $this->pipelineRunner->runWithRuntime(
                'agent.stream.after',
                [
                    'agent' => $agent,
                    'input' => $input,
                    'context' => $context,
                    'system_prompt' => $systemPrompt,
                    'events' => $events,
                    'error' => $error,
                ],
                $context->getMiddlewareFor('agent.stream.after'),
            );
        }
    }

    /**
     * Handle an error by running the error pipeline.
     *
     * If the pipeline returns a 'recovery' key with a valid response,
     * that response will be wrapped in AgentResponse and returned.
     *
     * @return AgentResponse|null Recovery response or null to rethrow
     */
    protected function handleError(
        AgentContract $agent,
        string $input,
        AgentContext $context,
        ?string $systemPrompt,
        Throwable $exception,
    ): ?AgentResponse {
        $result = $this->pipelineRunner->runWithRuntime(
            'agent.on_error',
            [
                'agent' => $agent,
                'input' => $input,
                'context' => $context,
                'system_prompt' => $systemPrompt,
                'exception' => $exception,
            ],
            $context->getMiddlewareFor('agent.on_error'),
        );

        // Check if pipeline provided a recovery response
        if (isset($result['recovery']) && ($result['recovery'] instanceof PrismResponse || $result['recovery'] instanceof StructuredResponse)) {
            return new AgentResponse(
                response: $result['recovery'],
                agent: $agent,
                input: $input,
                systemPrompt: $systemPrompt,
                context: $context,
            );
        }

        return null;
    }

    /**
     * Build a Prism request for the agent.
     *
     * Returns TextPendingRequest for normal text output, or StructuredPendingRequest
     * when a schema is defined (either on agent or via withSchema()).
     */
    protected function buildRequest(
        AgentContract $agent,
        string $input,
        AgentContext $context,
        ?string $systemPrompt,
    ): TextPendingRequest|StructuredPendingRequest {
        $provider = $context->providerOverride ?? $agent->provider();
        $model = $context->modelOverride ?? $agent->model();

        if ($provider === null) {
            throw new \InvalidArgumentException('Provider must be specified via agent definition or context override.');
        }

        if ($model === null) {
            throw new \InvalidArgumentException('Model must be specified via agent definition or context override.');
        }

        // On-demand schema takes priority, then agent schema
        $schema = $context->hasSchemaCall()
            ? $context->getSchemaFromCalls()
            : $agent->schema();

        $isStructured = $schema !== null;

        // Create base request (text or structured)
        $request = $isStructured
            ? Prism::structured()->using($provider, $model)->withSchema($schema)
            : Prism::text()->using($provider, $model);

        // Common: system prompt
        if ($systemPrompt !== null && $systemPrompt !== '') {
            $request = $request->withSystemPrompt($systemPrompt);
        }

        // Common: temperature and maxTokens (available on both request types)
        if ($agent->temperature() !== null) {
            $request = $request->usingTemperature($agent->temperature());
        }
        if ($agent->maxTokens() !== null) {
            $request = $request->withMaxTokens($agent->maxTokens());
        }

        // Common: client options and provider options
        if ($agent->clientOptions() !== []) {
            $request = $request->withClientOptions($agent->clientOptions());
        }
        if ($agent->providerOptions() !== []) {
            $request = $request->withProviderOptions($agent->providerOptions());
        }

        // Text-specific: tools, messages, maxSteps, providerTools
        if (! $isStructured) {
            $toolContext = new ToolContext($context->metadata, $agent);

            // Build all tools and merge in order:
            // 1. Agent-defined Atlas tools (from agent's tools() method)
            // 2. Runtime Atlas tools (from withTools())
            // 3. Agent-defined MCP tools (from agent's mcpTools() method)
            // 4. Runtime MCP tools (from withMcpTools())
            $allTools = $this->buildAllTools($agent, $context, $toolContext);

            if ($allTools !== []) {
                $request = $request->withTools($allTools);
            }

            $request = $context->hasMessages()
                ? $request->withMessages($this->buildMessages($context, $input))
                : $request->withPrompt($input, $context->prismMedia);

            if ($agent->maxSteps() !== null) {
                $request = $request->withMaxSteps($agent->maxSteps());
            }

            $providerTools = $this->buildProviderTools($agent->providerTools());
            if ($providerTools !== []) {
                $request = $request->withProviderTools($providerTools);
            }
        } else {
            // Structured: simple prompt only (no tools, no multi-turn messages)
            $request = $request->withPrompt($input);
        }

        // Replay captured Prism calls (excluding schema for structured)
        $prismCalls = $isStructured ? $context->getPrismCallsWithoutSchema() : $context->prismCalls;
        foreach ($prismCalls as $call) {
            $request = $request->{$call['method']}(...$call['args']);
        }

        return $request;
    }

    /**
     * Build the messages array for multi-turn conversation.
     *
     * Supports two formats for message history:
     * - Prism message objects (via prismMessages): Used directly for full Prism compatibility
     * - Array format (via messages): Converted to Prism objects, supports serialization
     *
     * @return array<int, UserMessage|AssistantMessage|SystemMessage>
     */
    protected function buildMessages(AgentContext $context, string $input): array
    {
        $messages = [];

        // Use Prism message objects directly if provided
        if ($context->hasPrismMessages()) {
            $messages = $context->prismMessages;
        } else {
            // Convert array format messages (history uses array format for serialization)
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
        }

        // Add current input as user message with Prism media objects directly
        $messages[] = new UserMessage($input, $context->prismMedia);

        return $messages;
    }

    /**
     * Create a UserMessage with optional attachments from message history.
     *
     * Uses MediaConverter to convert array-format attachments (for serialization)
     * into Prism media objects.
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
     * Build and merge all tools from agent and runtime context.
     *
     * Order: agent native → runtime native → agent MCP → runtime MCP
     *
     * Fires the agent.tools.merged pipeline to allow inspection and modification
     * of the complete tool set before sending to Prism.
     *
     * @return array<int, \Prism\Prism\Tool>
     */
    protected function buildAllTools(
        AgentContract $agent,
        AgentContext $context,
        ToolContext $toolContext
    ): array {
        // 1. Agent-defined Atlas tools (built from tool class names)
        $agentNativeTools = $this->toolBuilder->buildForAgent($agent, $toolContext);

        // 2. Runtime Atlas tools (built from tool class names via withTools())
        $runtimeNativeTools = $context->hasTools()
            ? $this->toolBuilder->buildFromClasses($context->tools, $toolContext)
            : [];

        // 3. Agent-defined MCP tools (already Prism Tool instances)
        $agentMcpTools = $agent->mcpTools();

        // 4. Runtime MCP tools (already Prism Tool instances via withMcpTools())
        $runtimeMcpTools = $context->mcpTools;

        // Combine native tools (agent + runtime)
        $allNativeTools = [...$agentNativeTools, ...$runtimeNativeTools];

        // Combine MCP tools (agent + runtime)
        $allMcpTools = [...$agentMcpTools, ...$runtimeMcpTools];

        // Merge all tools
        $allTools = [...$allNativeTools, ...$allMcpTools];

        // Run agent.tools.merged pipeline - allows auditing, filtering, or injecting tools
        $mergedData = $this->pipelineRunner->runWithRuntime(
            'agent.tools.merged',
            [
                'agent' => $agent,
                'context' => $context,
                'tool_context' => $toolContext,
                'agent_tools' => $allNativeTools,
                'agent_mcp_tools' => $allMcpTools,
                'tools' => $allTools,
            ],
            $context->getMiddlewareFor('agent.tools.merged'),
        );

        return $mergedData['tools'];
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
