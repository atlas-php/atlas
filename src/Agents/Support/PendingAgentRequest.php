<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Support;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Generator;
use Prism\Prism\Streaming\Events\StreamEvent;
use Prism\Prism\Structured\Response as StructuredResponse;
use Prism\Prism\Text\Response as PrismResponse;

/**
 * Fluent builder for agent chat operations.
 *
 * Provides a fluent API for configuring and executing chat operations
 * with messages, variables, metadata, and provider/model overrides.
 * Uses immutable cloning for method chaining.
 *
 * Unknown methods are forwarded to Prism's PendingRequest via __call(),
 * allowing full access to Prism's API without explicit wrappers.
 *
 * @mixin \Prism\Prism\Text\PendingRequest
 */
final class PendingAgentRequest
{
    use HasMediaSupport;
    use HasMetadataSupport;
    use HasVariablesSupport;

    /**
     * Conversation history with optional attachments.
     *
     * @var array<int, array{role: string, content: string, attachments?: array<int, array{type: string, source: string, data: string, mime_type?: string|null, title?: string|null, disk?: string|null}>}>
     */
    private array $messages = [];

    /**
     * Provider override.
     */
    private ?string $providerOverride = null;

    /**
     * Model override.
     */
    private ?string $modelOverride = null;

    /**
     * Captured Prism method calls to replay on the request.
     *
     * @var array<int, array{method: string, args: array<int, mixed>}>
     */
    private array $prismCalls = [];

    public function __construct(
        private readonly AgentResolver $agentResolver,
        private readonly AgentExecutorContract $agentExecutor,
        private readonly string|AgentContract $agent,
    ) {}

    /**
     * Forward unknown methods to Prism's PendingRequest.
     *
     * Captures the call for replay when chat()/stream() is called.
     * This allows seamless access to all Prism methods like withSchema(),
     * withToolChoice(), withClientRetry(), usingTemperature(), etc.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): static
    {
        $clone = clone $this;
        $clone->prismCalls[] = ['method' => $method, 'args' => $arguments];

        return $clone;
    }

    /**
     * Set conversation history messages.
     *
     * @param  array<int, array{role: string, content: string, attachments?: array<int, array{type: string, source: string, data: string, mime_type?: string|null, title?: string|null, disk?: string|null}>}>  $messages
     */
    public function withMessages(array $messages): static
    {
        $clone = clone $this;
        $clone->messages = $messages;

        return $clone;
    }

    /**
     * Override the provider and optionally the model for this request.
     *
     * @param  string  $provider  The provider name (e.g., 'openai', 'anthropic').
     * @param  string|null  $model  Optional model name (e.g., 'gpt-4', 'dall-e-3').
     */
    public function withProvider(string $provider, ?string $model = null): static
    {
        $clone = clone $this;
        $clone->providerOverride = $provider;

        if ($model !== null) {
            $clone->modelOverride = $model;
        }

        return $clone;
    }

    /**
     * Override the model for this request.
     *
     * @param  string  $model  The model name (e.g., 'gpt-4', 'dall-e-3').
     */
    public function withModel(string $model): static
    {
        $clone = clone $this;
        $clone->modelOverride = $model;

        return $clone;
    }

    /**
     * Execute a blocking chat with the configured agent.
     *
     * Returns Prism's Response directly for full API access:
     * - $response->text - Text response
     * - $response->usage - Full usage stats including cache tokens, thought tokens
     * - $response->steps - Multi-step agentic loop history
     * - $response->toolCalls - Tool calls as ToolCall objects
     * - $response->finishReason - Typed FinishReason enum
     * - $response->meta - Request metadata, rate limits
     *
     * If withSchema() was called, returns StructuredResponse instead:
     * - $response->structured - The structured data extracted
     * - $response->text - The raw text (if available)
     * - $response->usage - Full usage stats
     *
     * @param  string  $input  The user input message.
     */
    public function chat(string $input): PrismResponse|StructuredResponse
    {
        $resolvedAgent = $this->agentResolver->resolve($this->agent);
        $context = $this->buildContext();

        return $this->agentExecutor->execute($resolvedAgent, $input, $context);
    }

    /**
     * Stream a response from the configured agent.
     *
     * Returns a Generator yielding Prism StreamEvents directly.
     * Consumers work with Prism's streaming API directly.
     *
     * @param  string  $input  The user input message.
     * @return Generator<int, StreamEvent>
     */
    public function stream(string $input): Generator
    {
        $resolvedAgent = $this->agentResolver->resolve($this->agent);
        $context = $this->buildContext();

        yield from $this->agentExecutor->stream($resolvedAgent, $input, $context);
    }

    /**
     * Build the execution context from current configuration.
     */
    private function buildContext(): ExecutionContext
    {
        return new ExecutionContext(
            messages: $this->messages,
            variables: $this->getVariables(),
            metadata: $this->getMetadata(),
            providerOverride: $this->providerOverride,
            modelOverride: $this->modelOverride,
            currentAttachments: $this->getCurrentAttachments(),
            prismCalls: $this->prismCalls,
            prismMedia: $this->getPrismMedia(),
        );
    }
}
