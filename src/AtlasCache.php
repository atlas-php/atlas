<?php

declare(strict_types=1);

namespace Atlasphp\Atlas;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Class AtlasCache
 *
 * Unified cache service for Atlas. Handles models, voices, and embeddings
 * with configurable TTLs per type. A TTL of 0 disables caching for that type.
 */
class AtlasCache
{
    /**
     * Remember a value by type and key.
     *
     * @param  string  $type  One of: 'models', 'voices', 'embeddings'
     * @param  string  $key  Unique identifier within the type
     * @param  Closure(): mixed  $callback  Generator if cache miss
     */
    public function remember(string $type, string $key, Closure $callback): mixed
    {
        $ttl = $this->ttl($type);

        if ($ttl <= 0) {
            return $callback();
        }

        return $this->store()->remember(
            $this->key($type, $key),
            $ttl,
            $callback,
        );
    }

    /**
     * Forget a specific cached value.
     */
    public function forget(string $type, string $key): bool
    {
        return $this->store()->forget($this->key($type, $key));
    }

    /**
     * Flush multiple entries for a given type.
     *
     * @param  array<int, string>  $keys
     */
    public function flushKeys(string $type, array $keys): void
    {
        foreach ($keys as $key) {
            $this->store()->forget($this->key($type, $key));
        }
    }

    /**
     * Check if caching is enabled for a given type.
     */
    public function enabled(string $type): bool
    {
        return $this->ttl($type) > 0;
    }

    // ─── Internals ───────────────────────────────────────────────────

    protected function store(): Repository
    {
        /** @var string|null $store */
        $store = config('atlas.cache.store');

        return Cache::store($store);
    }

    protected function ttl(string $type): int
    {
        return (int) config("atlas.cache.ttl.{$type}", 0);
    }

    protected function key(string $type, string $key): string
    {
        $prefix = config('atlas.cache.prefix', 'atlas');

        return "{$prefix}:{$type}:{$key}";
    }
}
