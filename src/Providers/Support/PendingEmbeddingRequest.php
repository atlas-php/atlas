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
    use HasMetadataSupport;
    use HasRetrySupport;

    public function __construct(
        private readonly EmbeddingService $embeddingService,
    ) {}

    /**
     * Generate embedding(s) for text input.
     *
     * @param  string|array<string>  $input  Single text or array of texts.
     * @return array<int, float>|array<int, array<int, float>> Embedding vector(s).
     */
    public function generate(string|array $input): array
    {
        $metadata = $this->getMetadata();
        $options = $metadata !== [] ? ['metadata' => $metadata] : [];

        if (is_string($input)) {
            return $this->embeddingService->generate($input, $options, $this->getRetryArray());
        }

        return $this->embeddingService->generateBatch($input, $options, $this->getRetryArray());
    }

    /**
     * Get the dimensions of embedding vectors.
     *
     * @return int The number of dimensions.
     */
    public function dimensions(): int
    {
        return $this->embeddingService->dimensions();
    }
}
