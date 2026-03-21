<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Embeddings;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Optional caching layer for embedding vectors.
 *
 * Avoids redundant API calls by caching the vector result keyed on
 * provider, model, dimensions, and input text. Reads configuration
 * from atlas.embeddings.cache.*.
 */
class EmbeddingCache
{
    public function isEnabled(): bool
    {
        return (bool) config('atlas.embeddings.cache.enabled', false);
    }

    /**
     * Return cached embedding or generate and cache it.
     *
     * @param  Closure(): array<int, float>  $generate
     * @return array<int, float>
     */
    public function remember(string $input, Closure $generate, ?string $provider = null, ?string $model = null): array
    {
        if (! $this->isEnabled()) {
            return $generate();
        }

        $key = $this->buildKey($input, $provider, $model);
        $ttl = (int) config('atlas.embeddings.cache.ttl', 2592000);

        return $this->store()->remember($key, $ttl, $generate);
    }

    /**
     * Remove a cached embedding.
     */
    public function forget(string $input, ?string $provider = null, ?string $model = null): bool
    {
        return $this->store()->forget(
            $this->buildKey($input, $provider, $model)
        );
    }

    /**
     * Build a unique cache key incorporating provider, model, dimensions, and input.
     */
    public function buildKey(string $input, ?string $provider = null, ?string $model = null): string
    {
        $provider ??= config('atlas.defaults.embed.provider', 'default');
        $model ??= config('atlas.defaults.embed.model', 'default');
        $dimensions = (int) config('atlas.embeddings.dimensions', 1536);

        return 'atlas:embedding:'.hash('xxh128', "{$provider}:{$model}:{$dimensions}:{$input}");
    }

    protected function store(): Repository
    {
        $store = config('atlas.embeddings.cache.store');

        return Cache::store($store);
    }
}
