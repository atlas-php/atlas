<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Support;

use Prism\Prism\ValueObjects\Media\Audio;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Media\Video;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

/**
 * Stateless context for agent invocation.
 *
 * Carries conversation history, variable bindings, metadata, provider
 * overrides, and captured Prism method calls without any database or
 * session dependencies. Consumer manages all persistence.
 *
 * Message history can be provided in two formats:
 * - Array format ($messages): For serialization and persistence
 * - Prism message objects ($prismMessages): For direct Prism compatibility
 *
 * Current input attachments are stored as Prism media objects directly.
 *
 * This is a read-only value object built by PendingAgentRequest.
 */
final readonly class AgentContext
{
    /**
     * @param  array<int, array{role: string, content: string, attachments?: array<int, array{type: string, source: string, data: string, mime_type?: string|null, title?: string|null, disk?: string|null}>}>  $messages  Conversation history with optional attachments (array format for serialization).
     * @param  array<string, mixed>  $variables  Variables for system prompt interpolation.
     * @param  array<string, mixed>  $metadata  Additional metadata for pipeline middleware.
     * @param  string|null  $providerOverride  Override the agent's configured provider.
     * @param  string|null  $modelOverride  Override the agent's configured model.
     * @param  array<int, array{method: string, args: array<int, mixed>}>  $prismCalls  Captured Prism method calls to replay on the request.
     * @param  array<int, Image|Document|Audio|Video>  $prismMedia  Prism media objects for the current input.
     * @param  array<int, UserMessage|AssistantMessage|SystemMessage>  $prismMessages  Prism message objects for conversation history (direct Prism compatibility).
     * @param  array<int, class-string<\Atlasphp\Atlas\Tools\Contracts\ToolContract>>  $tools  Runtime Atlas tool classes.
     * @param  array<int, \Prism\Prism\Tool>  $mcpTools  MCP tools from prism-php/relay for runtime tool injection.
     */
    public function __construct(
        public array $messages = [],
        public array $variables = [],
        public array $metadata = [],
        public ?string $providerOverride = null,
        public ?string $modelOverride = null,
        public array $prismCalls = [],
        public array $prismMedia = [],
        public array $prismMessages = [],
        public array $tools = [],
        public array $mcpTools = [],
    ) {}

    /**
     * Get a variable value.
     *
     * @param  string  $key  The variable key.
     * @param  mixed  $default  Default value if not found.
     */
    public function getVariable(string $key, mixed $default = null): mixed
    {
        return $this->variables[$key] ?? $default;
    }

    /**
     * Get a metadata value.
     *
     * @param  string  $key  The metadata key.
     * @param  mixed  $default  Default value if not found.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Check if messages are present (either array or Prism format).
     */
    public function hasMessages(): bool
    {
        return $this->messages !== [] || $this->prismMessages !== [];
    }

    /**
     * Check if Prism message objects are present.
     *
     * When true, prismMessages should be used instead of messages array.
     */
    public function hasPrismMessages(): bool
    {
        return $this->prismMessages !== [];
    }

    /**
     * Check if attachments are present for current input.
     */
    public function hasAttachments(): bool
    {
        return $this->prismMedia !== [];
    }

    /**
     * Check if a variable exists.
     */
    public function hasVariable(string $key): bool
    {
        return array_key_exists($key, $this->variables);
    }

    /**
     * Check if metadata exists.
     */
    public function hasMeta(string $key): bool
    {
        return array_key_exists($key, $this->metadata);
    }

    /**
     * Check if provider override is set.
     */
    public function hasProviderOverride(): bool
    {
        return $this->providerOverride !== null;
    }

    /**
     * Check if model override is set.
     */
    public function hasModelOverride(): bool
    {
        return $this->modelOverride !== null;
    }

    /**
     * Check if there are captured Prism calls to replay.
     */
    public function hasPrismCalls(): bool
    {
        return $this->prismCalls !== [];
    }

    /**
     * Check if runtime tools are present.
     */
    public function hasTools(): bool
    {
        return $this->tools !== [];
    }

    /**
     * Check if MCP tools are present.
     */
    public function hasMcpTools(): bool
    {
        return $this->mcpTools !== [];
    }

    /**
     * Check if a schema call is present in prism calls.
     *
     * When true, the executor should use Prism's Structured module
     * instead of the Text module for execution.
     */
    public function hasSchemaCall(): bool
    {
        foreach ($this->prismCalls as $call) {
            if ($call['method'] === 'withSchema') {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the schema from prism calls if present.
     *
     * @return \Prism\Prism\Contracts\Schema|null
     */
    public function getSchemaFromCalls(): mixed
    {
        foreach ($this->prismCalls as $call) {
            if ($call['method'] === 'withSchema') {
                return $call['args'][0] ?? null;
            }
        }

        return null;
    }

    /**
     * Get prism calls without the schema call (for replay after schema is set).
     *
     * @return array<int, array{method: string, args: array<int, mixed>}>
     */
    public function getPrismCallsWithoutSchema(): array
    {
        return array_values(array_filter(
            $this->prismCalls,
            fn (array $call): bool => $call['method'] !== 'withSchema'
        ));
    }

    /**
     * Create a new context with the given variables (replaces existing).
     *
     * @param  array<string, mixed>  $variables
     */
    public function withVariables(array $variables): self
    {
        return new self(
            $this->messages,
            $variables,
            $this->metadata,
            $this->providerOverride,
            $this->modelOverride,
            $this->prismCalls,
            $this->prismMedia,
            $this->prismMessages,
            $this->tools,
            $this->mcpTools,
        );
    }

    /**
     * Create a new context with merged variables.
     *
     * @param  array<string, mixed>  $variables
     */
    public function mergeVariables(array $variables): self
    {
        return new self(
            $this->messages,
            [...$this->variables, ...$variables],
            $this->metadata,
            $this->providerOverride,
            $this->modelOverride,
            $this->prismCalls,
            $this->prismMedia,
            $this->prismMessages,
            $this->tools,
            $this->mcpTools,
        );
    }

    /**
     * Create a new context with cleared variables.
     */
    public function clearVariables(): self
    {
        return new self(
            $this->messages,
            [],
            $this->metadata,
            $this->providerOverride,
            $this->modelOverride,
            $this->prismCalls,
            $this->prismMedia,
            $this->prismMessages,
            $this->tools,
            $this->mcpTools,
        );
    }

    /**
     * Create a new context with the given metadata (replaces existing).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            $this->messages,
            $this->variables,
            $metadata,
            $this->providerOverride,
            $this->modelOverride,
            $this->prismCalls,
            $this->prismMedia,
            $this->prismMessages,
            $this->tools,
            $this->mcpTools,
        );
    }

    /**
     * Create a new context with merged metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function mergeMetadata(array $metadata): self
    {
        return new self(
            $this->messages,
            $this->variables,
            [...$this->metadata, ...$metadata],
            $this->providerOverride,
            $this->modelOverride,
            $this->prismCalls,
            $this->prismMedia,
            $this->prismMessages,
            $this->tools,
            $this->mcpTools,
        );
    }

    /**
     * Create a new context with cleared metadata.
     */
    public function clearMetadata(): self
    {
        return new self(
            $this->messages,
            $this->variables,
            [],
            $this->providerOverride,
            $this->modelOverride,
            $this->prismCalls,
            $this->prismMedia,
            $this->prismMessages,
            $this->tools,
            $this->mcpTools,
        );
    }

    /**
     * Serialize context for queue transport.
     *
     * Note: Runtime-only properties (prismMedia, prismMessages, mcpTools) are
     * not serialized as they contain Prism objects that cannot be serialized.
     * These must be re-attached after deserialization if needed.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'messages' => $this->messages,
            'variables' => $this->variables,
            'metadata' => $this->metadata,
            'provider_override' => $this->providerOverride,
            'model_override' => $this->modelOverride,
            'prism_calls' => $this->prismCalls,
            'tools' => $this->tools,
        ];
    }

    /**
     * Restore context from serialized data.
     *
     * Note: Runtime-only properties (prismMedia, prismMessages, mcpTools) are
     * set to empty arrays. Use withMedia() or other builder methods to re-attach
     * these after deserialization if needed.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            messages: $data['messages'] ?? [],
            variables: $data['variables'] ?? [],
            metadata: $data['metadata'] ?? [],
            providerOverride: $data['provider_override'] ?? null,
            modelOverride: $data['model_override'] ?? null,
            prismCalls: $data['prism_calls'] ?? [],
            prismMedia: [],
            prismMessages: [],
            tools: $data['tools'] ?? [],
            mcpTools: [],
        );
    }
}
