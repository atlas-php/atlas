<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Embeddings;

use Atlasphp\Atlas\Facades\Atlas;

/**
 * Resolves a text string into an embedding vector.
 *
 * Acts as the single bridge between raw text and the vector array needed
 * by query macros and traits. Uses EmbeddingCache when enabled.
 */
class EmbeddingResolver
{
    public function __construct(
        protected readonly EmbeddingCache $cache,
    ) {}

    /**
     * Generate an embedding using configured defaults.
     *
     * @return array<int, float>
     */
    public function resolve(string $input): array
    {
        return $this->cache->remember(
            $input,
            fn (): array => $this->generate($input),
        );
    }

    /**
     * Generate an embedding with explicit provider and model.
     *
     * @return array<int, float>
     */
    public function resolveUsing(string $input, ?string $provider = null, ?string $model = null): array
    {
        return $this->cache->remember(
            $input,
            fn (): array => $this->generate($input, $provider, $model),
            $provider,
            $model,
        );
    }

    /**
     * Call Atlas embed API and return the first embedding vector.
     *
     * @return array<int, float>
     */
    protected function generate(string $input, ?string $provider = null, ?string $model = null): array
    {
        $response = Atlas::embed($provider, $model)
            ->fromInput($input)
            ->asEmbeddings();

        if (empty($response->embeddings)) {
            throw new \RuntimeException('Provider returned no embeddings for the given input.');
        }

        return $response->embeddings[0];
    }
}
