<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Support;

use Atlasphp\Atlas\Providers\Services\EmbeddingService;

/**
 * Fluent builder for embedding operations.
 *
 * Provides a fluent API for configuring embedding generation with metadata
 * and retry support. Uses immutable cloning for method chaining.
 */
final class PendingEmbeddingRequest
{
    use HasRetrySupport;

    /**
     * Additional metadata for pipeline middleware.
     *
     * @var array<string, mixed>
     */
    private array $metadata = [];

    public function __construct(
        private readonly EmbeddingService $embeddingService,
    ) {}

    /**
     * Set metadata for pipeline middleware.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        $clone = clone $this;
        $clone->metadata = $metadata;

        return $clone;
    }

    /**
     * Generate an embedding for a single text input.
     *
     * @param  string  $text  The text to embed.
     * @return array<int, float> The embedding vector.
     */
    public function generate(string $text): array
    {
        $options = $this->metadata !== [] ? ['metadata' => $this->metadata] : [];

        return $this->embeddingService->generate($text, $options, $this->getRetryArray());
    }

    /**
     * Generate embeddings for multiple text inputs.
     *
     * @param  array<string>  $texts  The texts to embed.
     * @return array<int, array<int, float>> Array of embedding vectors.
     */
    public function generateBatch(array $texts): array
    {
        $options = $this->metadata !== [] ? ['metadata' => $this->metadata] : [];

        return $this->embeddingService->generateBatch($texts, $options, $this->getRetryArray());
    }
}
