<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Contracts;

/**
 * Contract for embedding providers.
 *
 * Defines the interface for generating text embeddings using various AI providers.
 */
interface EmbeddingProviderContract
{
    /**
     * Generate an embedding for a single text input.
     *
     * @param  string  $text  The text to embed.
     * @return array<int, float> The embedding vector.
     */
    public function generate(string $text): array;

    /**
     * Generate embeddings for multiple text inputs.
     *
     * @param  array<int, string>  $texts  The texts to embed.
     * @return array<int, array<int, float>> Array of embedding vectors.
     */
    public function generateBatch(array $texts): array;

    /**
     * Get the dimensions of the embedding vectors.
     *
     * @return int The number of dimensions.
     */
    public function dimensions(): int;
}
