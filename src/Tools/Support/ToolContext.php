<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Support;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;

/**
 * Stateless context for tool execution.
 *
 * Carries metadata and agent reference for pipeline middleware.
 */
final readonly class ToolContext
{
    /**
     * @param  array<string, mixed>  $metadata  Additional metadata for tool execution.
     * @param  AgentContract|null  $agent  The agent executing this tool.
     */
    public function __construct(
        public array $metadata = [],
        public ?AgentContract $agent = null,
    ) {}

    /**
     * Get the agent executing this tool.
     */
    public function getAgent(): ?AgentContract
    {
        return $this->agent;
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
     * Check if metadata exists.
     *
     * @param  string  $key  The metadata key.
     */
    public function hasMeta(string $key): bool
    {
        return array_key_exists($key, $this->metadata);
    }

    /**
     * Create a new context with the given metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self($metadata, $this->agent);
    }

    /**
     * Create a new context with merged metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function mergeMetadata(array $metadata): self
    {
        return new self(array_merge($this->metadata, $metadata), $this->agent);
    }

    /**
     * Create a new context with cleared metadata.
     */
    public function clearMetadata(): self
    {
        return new self([], $this->agent);
    }
}
