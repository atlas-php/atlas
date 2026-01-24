<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Support;

/**
 * Stateless execution context for agent invocation.
 *
 * Carries conversation history, variable bindings, metadata, provider
 * overrides, and captured Prism method calls without any database or
 * session dependencies. Consumer manages all persistence.
 *
 * This is a read-only value object built by PendingAgentRequest.
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
     * @param  array<int, array{method: string, args: array<int, mixed>}>  $prismCalls  Captured Prism method calls to replay on the request.
     */
    public function __construct(
        public array $messages = [],
        public array $variables = [],
        public array $metadata = [],
        public ?string $providerOverride = null,
        public ?string $modelOverride = null,
        public array $currentAttachments = [],
        public array $prismCalls = [],
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

    /**
     * Check if there are captured Prism calls to replay.
     */
    public function hasPrismCalls(): bool
    {
        return $this->prismCalls !== [];
    }
}
