<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Services;

use Atlasphp\Atlas\Providers\Contracts\EmbeddingProviderContract;

/**
 * Service layer for generating text embeddings.
 *
 * Delegates to the configured embedding provider while providing a clean API.
 */
class EmbeddingService
{
    public function __construct(
        private readonly EmbeddingProviderContract $provider,
    ) {}

    /**
     * Generate an embedding for a single text input.
     *
     * @return array<int, float>
     */
    public function generate(string $text): array
    {
        return $this->provider->generate($text);
    }

    /**
     * Generate embeddings for multiple text inputs.
     *
     * @param  array<string>  $texts  The texts to embed.
     * @return array<int, array<int, float>>
     */
    public function generateBatch(array $texts): array
    {
        return $this->provider->generateBatch($texts);
    }

    /**
     * Get the dimensions of the embedding vectors.
     */
    public function dimensions(): int
    {
        return $this->provider->dimensions();
    }
}
