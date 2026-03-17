<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pipelines\Middleware;

use Atlasphp\Atlas\Contracts\PipelineContract;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Pipeline middleware that caches embedding responses.
 *
 * Registered on `embeddings.before_embeddings` to short-circuit API calls
 * when a cached response exists. Supports global config and per-request
 * overrides via metadata.
 */
class CacheEmbeddings implements PipelineContract
{
    /**
     * Handle the embedding request with caching.
     *
     * @param  mixed  $data  Pipeline context array with request, metadata, etc.
     * @param  Closure  $next  The next handler in the pipeline chain.
     * @return mixed The processed data, potentially with a cached response.
     */
    public function handle(mixed $data, Closure $next): mixed
    {
        /** @var array<string, mixed> $config */
        $config = config('atlas.embeddings.cache', []);
        $enabled = $config['enabled'] ?? false;

        // Check per-request metadata override
        $requestEnabled = $data['metadata']['cache'] ?? null;
        if ($requestEnabled === false || (! $enabled && $requestEnabled !== true)) {
            return $next($data);
        }

        $store = $data['metadata']['cache_store'] ?? $config['store'] ?? null;
        $ttl = $data['metadata']['cache_ttl'] ?? $config['ttl'] ?? 3600;

        $cacheKey = $this->buildCacheKey($data);

        $cache = $store ? Cache::store($store) : Cache::store();

        if ($cache->has($cacheKey)) {
            $data['response'] = $cache->get($cacheKey);

            return $data;
        }

        $result = $next($data);

        $cache->put($cacheKey, $result['response'], $ttl);

        return $result;
    }

    /**
     * Build a cache key from the request data.
     *
     * Uses an explicit key from metadata if provided, otherwise hashes
     * the serialized request object for automatic keying.
     *
     * @param  array<string, mixed>  $data  Pipeline context data.
     */
    protected function buildCacheKey(array $data): string
    {
        if (isset($data['metadata']['cache_key'])) {
            return 'atlas:embeddings:'.$data['metadata']['cache_key'];
        }

        return 'atlas:embeddings:'.md5(serialize($data['request']));
    }
}
