<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Handlers;

use Atlasphp\Atlas\Cache\AtlasCache;
use Atlasphp\Atlas\Providers\Concerns\BuildsHeaders;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ModelList;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\VoiceList;

/**
 * Base provider handler with cached models/voices and shared logic.
 *
 * Subclasses override fetchModels() and fetchVoices() for provider-specific
 * API calls. Caching is handled by AtlasCache with configurable TTLs.
 */
abstract class AbstractProviderHandler implements ProviderHandler
{
    use BuildsHeaders;

    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
        protected readonly AtlasCache $cache,
    ) {}

    // ─── Public API (cached) ─────────────────────────────────────────

    public function models(): ModelList
    {
        return $this->cache->remember(
            'models',
            $this->cacheKeyPrefix(),
            fn () => $this->fetchModels(),
        );
    }

    public function voices(): VoiceList
    {
        return $this->cache->remember(
            'voices',
            $this->cacheKeyPrefix(),
            fn () => $this->fetchVoices(),
        );
    }

    public function validate(): bool
    {
        try {
            $this->models();

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    // ─── Uncached fetch — subclasses override these ──────────────────

    /**
     * Fetch models from the provider API.
     */
    protected function fetchModels(): ModelList
    {
        $data = $this->http->get(
            url: "{$this->config->baseUrl}/models",
            headers: $this->headersWithoutContentType(),
            timeout: $this->config->timeout,
        );

        /** @var array<int, array<string, mixed>> $models */
        $models = $data['data'] ?? [];

        $ids = array_map(fn (array $model): string => (string) $model['id'], $models);

        sort($ids);

        return new ModelList($ids);
    }

    /**
     * Fetch voices from the provider API.
     */
    abstract protected function fetchVoices(): VoiceList;

    // ─── Cache key ───────────────────────────────────────────────────

    /**
     * Cache key prefix for this provider instance.
     * Override for providers with per-account data (like ElevenLabs).
     */
    protected function cacheKeyPrefix(): string
    {
        $host = parse_url($this->config->baseUrl, PHP_URL_HOST);

        return is_string($host) ? $host : 'unknown';
    }

    // ─── Cache management ────────────────────────────────────────────

    /**
     * Clear cached models and voices for this provider.
     */
    public function flushCache(): void
    {
        $prefix = $this->cacheKeyPrefix();
        $this->cache->flushKeys('models', [$prefix]);
        $this->cache->flushKeys('voices', [$prefix]);
    }
}
