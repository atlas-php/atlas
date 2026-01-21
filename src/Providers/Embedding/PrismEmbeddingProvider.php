<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Embedding;

use Atlasphp\Atlas\Providers\Contracts\EmbeddingProviderContract;
use Atlasphp\Atlas\Providers\Services\PrismBuilder;

/**
 * Embedding provider implementation using Prism.
 *
 * Generates text embeddings using the Prism PHP library.
 */
class PrismEmbeddingProvider implements EmbeddingProviderContract
{
    public function __construct(
        private readonly PrismBuilder $prismBuilder,
        private readonly string $provider,
        private readonly string $model,
        private readonly int $dimensions,
        private readonly int $batchSize = 100,
    ) {}

    /**
     * Generate an embedding for a single text input.
     *
     * @param  string  $text  The text to embed.
     * @return array<int, float> The embedding vector.
     */
    public function generate(string $text): array
    {
        $request = $this->prismBuilder->forEmbeddings(
            $this->provider,
            $this->model,
            $text,
        );

        $response = $request->asEmbeddings();

        if (! isset($response->embeddings[0])) {
            return [];
        }

        return $response->embeddings[0]->embedding;
    }

    /**
     * Generate embeddings for multiple text inputs.
     *
     * @param  array<int, string>  $texts  The texts to embed.
     * @return array<int, array<int, float>> Array of embedding vectors.
     */
    public function generateBatch(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $embeddings = [];
        $batches = array_chunk($texts, $this->batchSize);

        foreach ($batches as $batch) {
            $request = $this->prismBuilder->forEmbeddings(
                $this->provider,
                $this->model,
                $batch,
            );

            $response = $request->asEmbeddings();

            foreach ($response->embeddings as $embedding) {
                $embeddings[] = $embedding->embedding;
            }
        }

        return $embeddings;
    }

    /**
     * Get the dimensions of the embedding vectors.
     *
     * @return int The number of dimensions.
     */
    public function dimensions(): int
    {
        return $this->dimensions;
    }
}
