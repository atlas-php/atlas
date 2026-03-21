<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Embeddings;

use Atlasphp\Atlas\Cache\AtlasCache;
use Atlasphp\Atlas\Facades\Atlas;

/**
 * Resolves a text string into an embedding vector.
 *
 * Acts as the single bridge between raw text and the vector array needed
 * by query macros and traits. Uses AtlasCache when enabled.
 */
class EmbeddingResolver
{
    public function __construct(
        protected readonly AtlasCache $cache,
    ) {}

    /**
     * Generate an embedding using configured defaults.
     *
     * @return array<int, float>
     */
    public function resolve(string $input): array
    {
        return $this->cache->remember(
            'embeddings',
            $this->cacheKey($input),
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
            'embeddings',
            $this->cacheKey($input, $provider, $model),
            fn (): array => $this->generate($input, $provider, $model),
        );
    }

    /**
     * Remove a cached embedding.
     */
    public function forget(string $input, ?string $provider = null, ?string $model = null): bool
    {
        return $this->cache->forget('embeddings', $this->cacheKey($input, $provider, $model));
    }

    /**
     * Build a unique cache key for an embedding.
     */
    protected function cacheKey(string $input, ?string $provider = null, ?string $model = null): string
    {
        $provider ??= config('atlas.defaults.embed.provider', 'default');
        $model ??= config('atlas.defaults.embed.model', 'default');
        $dimensions = (int) config('atlas.embeddings.dimensions', 1536);

        return hash('xxh128', "{$provider}:{$model}:{$dimensions}:{$input}");
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
