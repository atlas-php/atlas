<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Support;

/**
 * Trait for services that support metadata configuration.
 *
 * Provides fluent methods for attaching metadata to operations.
 * Metadata is passed to all pipeline stages and can be used for logging,
 * tracing, or custom middleware. Uses the clone pattern for immutability.
 */
trait HasMetadataSupport
{
    /**
     * Additional metadata for pipeline middleware.
     *
     * @var array<string, mixed>
     */
    private array $metadata = [];

    /**
     * Set metadata for pipeline middleware.
     *
     * Replaces any previously set metadata entirely.
     * Metadata is passed to all pipeline stages and can be used
     * for logging, tracing, or custom middleware.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): static
    {
        $clone = clone $this;
        $clone->metadata = $metadata;

        return $clone;
    }

    /**
     * Merge metadata with any previously set metadata.
     *
     * Later calls override earlier values for the same keys.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function mergeMetadata(array $metadata): static
    {
        $clone = clone $this;
        $clone->metadata = [...$clone->metadata, ...$metadata];

        return $clone;
    }

    /**
     * Clear all metadata.
     */
    public function clearMetadata(): static
    {
        $clone = clone $this;
        $clone->metadata = [];

        return $clone;
    }

    /**
     * Get the configured metadata.
     *
     * @return array<string, mixed>
     */
    protected function getMetadata(): array
    {
        return $this->metadata;
    }
}
