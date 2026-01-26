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
use Prism\Prism\ValueObjects\Media\Audio;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Media\Video;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

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
     * Conversation history in array format (for serialization).
     *
     * @var array<int, array{role: string, content: string, attachments?: array<int, array{type: string, source: string, data: string, mime_type?: string|null, title?: string|null, disk?: string|null}>}>
     */
    private array $messages = [];

    /**
     * Conversation history as Prism message objects (for direct Prism compatibility).
     *
     * @var array<int, UserMessage|AssistantMessage|SystemMessage>
     */
    private array $prismMessages = [];

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

    /**
     * Runtime Atlas tool classes.
     *
     * @var array<int, class-string<\Atlasphp\Atlas\Tools\Contracts\ToolContract>>
     */
    private array $tools = [];

    /**
     * MCP tools from prism-php/relay.
     *
     * @var array<int, \Prism\Prism\Tool>
     */
    private array $mcpTools = [];

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
     * Accepts either array format (for serialization/persistence) or Prism message objects directly:
     *
     * Array format:
     * ```php
     * ->withMessages([
     *     ['role' => 'user', 'content' => 'Hello'],
     *     ['role' => 'assistant', 'content' => 'Hi there!'],
     * ])
     * ```
     *
     * Prism message objects:
     * ```php
     * use Prism\Prism\ValueObjects\Messages\{UserMessage, AssistantMessage};
     *
     * ->withMessages([
     *     new UserMessage('Hello'),
     *     new AssistantMessage('Hi there!'),
     * ])
     * ```
     *
     * @param  array<int, array{role: string, content: string, attachments?: array<int, array{type: string, source: string, data: string, mime_type?: string|null, title?: string|null, disk?: string|null}>}|UserMessage|AssistantMessage|SystemMessage>  $messages
     */
    public function withMessages(array $messages): static
    {
        $clone = clone $this;

        // Determine if messages are Prism objects or array format
        if ($messages !== [] && $this->isPrismMessageObject($messages[0])) {
            $clone->prismMessages = $messages;
            $clone->messages = [];
        } else {
            $clone->messages = $messages;
            $clone->prismMessages = [];
        }

        return $clone;
    }

    /**
     * Check if a value is a Prism message object.
     */
    private function isPrismMessageObject(mixed $value): bool
    {
        return $value instanceof UserMessage
            || $value instanceof AssistantMessage
            || $value instanceof SystemMessage;
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
     * Add Atlas tools at runtime.
     *
     * Tools are accumulated across multiple calls to this method.
     * These are merged with the agent's defined tools.
     *
     * @param  array<int, class-string<\Atlasphp\Atlas\Tools\Contracts\ToolContract>>  $tools  Tool class names.
     */
    public function withTools(array $tools): static
    {
        $clone = clone $this;
        $clone->tools = [...$clone->tools, ...$tools];

        return $clone;
    }

    /**
     * Add MCP tools from prism-php/relay.
     *
     * Tools are accumulated across multiple calls to this method.
     *
     * @param  array<int, \Prism\Prism\Tool>  $tools  Prism Tool instances from MCP servers.
     */
    public function withMcpTools(array $tools): static
    {
        $clone = clone $this;
        $clone->mcpTools = [...$clone->mcpTools, ...$tools];

        return $clone;
    }

    /**
     * Execute a blocking chat with the configured agent.
     *
     * Supports two styles for attachments (Prism-consistent):
     *
     * ```php
     * // Style 1: Prism-style with inline attachments
     * ->chat('Describe this image', [Image::fromUrl('https://...')])
     *
     * // Style 2: Builder-style with convenience methods
     * ->withImage('https://example.com/photo.jpg')
     * ->chat('Describe this image')
     *
     * // Both can be combined (attachments are merged)
     * ->withImage('https://example.com/photo1.jpg')
     * ->chat('Compare with this', [Image::fromUrl('https://example.com/photo2.jpg')])
     * ```
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
     * @param  array<int, Image|Document|Audio|Video>  $attachments  Optional Prism media objects to attach.
     */
    public function chat(string $input, array $attachments = []): PrismResponse|StructuredResponse
    {
        $resolvedAgent = $this->agentResolver->resolve($this->agent);
        $context = $this->buildContext($attachments);

        return $this->agentExecutor->execute($resolvedAgent, $input, $context);
    }

    /**
     * Stream a response from the configured agent.
     *
     * Returns a Generator yielding Prism StreamEvents directly.
     * Consumers work with Prism's streaming API directly.
     *
     * @param  string  $input  The user input message.
     * @param  array<int, Image|Document|Audio|Video>  $attachments  Optional Prism media objects to attach.
     * @return Generator<int, StreamEvent>
     */
    public function stream(string $input, array $attachments = []): Generator
    {
        $resolvedAgent = $this->agentResolver->resolve($this->agent);
        $context = $this->buildContext($attachments);

        yield from $this->agentExecutor->stream($resolvedAgent, $input, $context);
    }

    /**
     * Build the execution context from current configuration.
     *
     * @param  array<int, Image|Document|Audio|Video>  $inlineAttachments  Attachments passed directly to chat()/stream().
     */
    private function buildContext(array $inlineAttachments = []): AgentContext
    {
        // Merge builder attachments with inline attachments
        $allMedia = array_merge($this->getPrismMedia(), $inlineAttachments);

        return new AgentContext(
            messages: $this->messages,
            variables: $this->getVariables(),
            metadata: $this->getMetadata(),
            providerOverride: $this->providerOverride,
            modelOverride: $this->modelOverride,
            prismCalls: $this->prismCalls,
            prismMedia: $allMedia,
            prismMessages: $this->prismMessages,
            tools: $this->tools,
            mcpTools: $this->mcpTools,
        );
    }
}
