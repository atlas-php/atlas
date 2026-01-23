<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Support;

/**
 * Stateless execution context for agent invocation.
 *
 * Carries conversation history, variable bindings, metadata, and provider
 * overrides without any database or session dependencies. Consumer manages
 * all persistence.
 */
final readonly class ExecutionContext
{
    /**
     * @param  array<int, array{role: string, content: string, attachments?: array<int, array{type: string, source: string, data: string, mime_type?: string|null, title?: string|null, disk?: string|null}>}>  $messages  Conversation history with optional attachments.
     * @param  array<string, mixed>  $variables  Variables for system prompt interpolation.
     * @param  array<string, mixed>  $metadata  Additional metadata for pipeline middleware.
     * @param  string|null  $providerOverride  Override the agent's configured provider.
     * @param  string|null  $modelOverride  Override the agent's configured model.
     * @param  array<int, array{type: string, source: string, data: string, mime_type?: string|null, title?: string|null, disk?: string|null}>  $currentAttachments  Attachments for the current input message.
     */
    public function __construct(
        public array $messages = [],
        public array $variables = [],
        public array $metadata = [],
        public ?string $providerOverride = null,
        public ?string $modelOverride = null,
        public array $currentAttachments = [],
    ) {}

    /**
     * Create a new context with the given messages.
     *
     * @param  array<int, array{role: string, content: string, attachments?: array<int, array{type: string, source: string, data: string, mime_type?: string|null, title?: string|null, disk?: string|null}>}>  $messages
     */
    public function withMessages(array $messages): self
    {
        return new self($messages, $this->variables, $this->metadata, $this->providerOverride, $this->modelOverride, $this->currentAttachments);
    }

    /**
     * Create a new context with the given variables.
     *
     * @param  array<string, mixed>  $variables
     */
    public function withVariables(array $variables): self
    {
        return new self($this->messages, $variables, $this->metadata, $this->providerOverride, $this->modelOverride, $this->currentAttachments);
    }

    /**
     * Create a new context with the given metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self($this->messages, $this->variables, $metadata, $this->providerOverride, $this->modelOverride, $this->currentAttachments);
    }

    /**
     * Create a new context with the given provider override.
     *
     * @param  string|null  $provider  The provider name to override with.
     */
    public function withProviderOverride(?string $provider): self
    {
        return new self($this->messages, $this->variables, $this->metadata, $provider, $this->modelOverride, $this->currentAttachments);
    }

    /**
     * Create a new context with the given model override.
     *
     * @param  string|null  $model  The model name to override with.
     */
    public function withModelOverride(?string $model): self
    {
        return new self($this->messages, $this->variables, $this->metadata, $this->providerOverride, $model, $this->currentAttachments);
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
            array_merge($this->variables, $variables),
            $this->metadata,
            $this->providerOverride,
            $this->modelOverride,
            $this->currentAttachments,
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
            array_merge($this->metadata, $metadata),
            $this->providerOverride,
            $this->modelOverride,
            $this->currentAttachments,
        );
    }

    /**
     * Create a new context with the given current attachments.
     *
     * @param  array<int, array{type: string, source: string, data: string, mime_type?: string|null, title?: string|null, disk?: string|null}>  $attachments
     */
    public function withCurrentAttachments(array $attachments): self
    {
        return new self(
            $this->messages,
            $this->variables,
            $this->metadata,
            $this->providerOverride,
            $this->modelOverride,
            $attachments,
        );
    }

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
     * Check if messages are present.
     */
    public function hasMessages(): bool
    {
        return $this->messages !== [];
    }

    /**
     * Check if current attachments are present.
     */
    public function hasCurrentAttachments(): bool
    {
        return $this->currentAttachments !== [];
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
}
