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
     * @param  array<string, mixed>  $options  Additional options (dimensions, encoding_format, etc.).
     * @param  array{0: array<int, int>|int, 1: \Closure|int, 2: callable|null, 3: bool}|null  $retry  Optional retry configuration.
     * @return array<int, float> The embedding vector.
     */
    public function generate(string $text, array $options = [], ?array $retry = null): array
    {
        $request = $this->prismBuilder->forEmbeddings(
            $this->provider,
            $this->model,
            $text,
            $options,
            $retry,
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
     * @param  array<string, mixed>  $options  Additional options (dimensions, encoding_format, etc.).
     * @param  array{0: array<int, int>|int, 1: \Closure|int, 2: callable|null, 3: bool}|null  $retry  Optional retry configuration.
     * @return array<int, array<int, float>> Array of embedding vectors.
     */
    public function generateBatch(array $texts, array $options = [], ?array $retry = null): array
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
                $options,
                $retry,
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

    /**
     * Get the provider name.
     *
     * @return string The provider name.
     */
    public function provider(): string
    {
        return $this->provider;
    }

    /**
     * Get the model name.
     *
     * @return string The model name.
     */
    public function model(): string
    {
        return $this->model;
    }
}
