<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Support;

/**
 * Stateless execution context for agent invocation.
 *
 * Carries conversation history, variable bindings, and metadata
 * without any database or session dependencies. Consumer manages
 * all persistence.
 */
final readonly class ExecutionContext
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages  Conversation history.
     * @param  array<string, mixed>  $variables  Variables for system prompt interpolation.
     * @param  array<string, mixed>  $metadata  Additional metadata for pipeline middleware.
     */
    public function __construct(
        public array $messages = [],
        public array $variables = [],
        public array $metadata = [],
    ) {}

    /**
     * Create a new context with the given messages.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function withMessages(array $messages): self
    {
        return new self($messages, $this->variables, $this->metadata);
    }

    /**
     * Create a new context with the given variables.
     *
     * @param  array<string, mixed>  $variables
     */
    public function withVariables(array $variables): self
    {
        return new self($this->messages, $variables, $this->metadata);
    }

    /**
     * Create a new context with the given metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self($this->messages, $this->variables, $metadata);
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
}
