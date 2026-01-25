<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Support;

/**
 * Trait for services that support metadata configuration.
 *
 * Provides a fluent withMetadata() method for attaching metadata to operations.
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
     * Get the configured metadata.
     *
     * @return array<string, mixed>
     */
    protected function getMetadata(): array
    {
        return $this->metadata;
    }
}
