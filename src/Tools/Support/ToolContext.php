<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Support;

/**
 * Stateless context for tool execution.
 *
 * Carries metadata for pipeline middleware without any database
 * or session dependencies. Consumer manages all persistence.
 */
final readonly class ToolContext
{
    /**
     * @param  array<string, mixed>  $metadata  Additional metadata for tool execution.
     */
    public function __construct(
        public array $metadata = [],
    ) {}

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
        return new self($metadata);
    }

    /**
     * Create a new context with merged metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function mergeMetadata(array $metadata): self
    {
        return new self(array_merge($this->metadata, $metadata));
    }
}
