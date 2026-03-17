<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Models\Services;

use Atlasphp\Atlas\Models\Enums\ProviderEndpoint;
use Atlasphp\Atlas\Models\Enums\ResponseFormat;
use Atlasphp\Atlas\Models\Support\ModelResponseParser;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\Provider;

/**
 * Fetches and caches available models from AI provider APIs.
 *
 * Provides a standardized interface for listing models across all Prism-supported
 * providers. Results are cached to minimize API calls, with support for forced
 * refresh and cache clearing.
 */
class ListModelsService
{
    public function __construct(
        protected HttpFactory $http,
        protected CacheRepository $cache,
    ) {}

    /**
     * Check if a provider supports model listing.
     */
    public function has(Provider|string $provider): bool
    {
        $endpoint = $this->resolveEndpoint($provider);

        return $endpoint !== null && $endpoint->hasModelsEndpoint();
    }

    /**
     * Get models for a provider, returning cached results when available.
     *
     * @return list<string>|null
     */
    public function get(Provider|string $provider): ?array
    {
        $endpoint = $this->resolveEndpoint($provider);

        if ($endpoint === null || ! $endpoint->hasModelsEndpoint()) {
            return null;
        }

        $cacheKey = $this->cacheKey($endpoint);

        if ($this->isCacheEnabled()) {
            $cached = $this->cache->get($cacheKey);

            if ($cached !== null) {
                return $cached;
            }
        }

        $models = $this->fetchModels($endpoint);

        if ($models !== null && $this->isCacheEnabled()) {
            $this->cache->put($cacheKey, $models, $this->cacheTtl());
        }

        return $models;
    }

    /**
     * Force refresh models from the provider API, updating cache.
     *
     * @return list<string>|null
     */
    public function refresh(Provider|string $provider): ?array
    {
        $endpoint = $this->resolveEndpoint($provider);

        if ($endpoint === null || ! $endpoint->hasModelsEndpoint()) {
            return null;
        }

        $models = $this->fetchModels($endpoint);

        if ($models !== null && $this->isCacheEnabled()) {
            $this->cache->put($this->cacheKey($endpoint), $models, $this->cacheTtl());
        }

        return $models;
    }

    /**
     * Clear cached models for a provider.
     */
    public function clear(Provider|string $provider): void
    {
        $endpoint = $this->resolveEndpoint($provider);

        if ($endpoint === null) {
            return;
        }

        $this->cache->forget($this->cacheKey($endpoint));
    }

    /**
     * Get models from all configured providers.
     *
     * @return array<string, list<string>>
     */
    public function all(): array
    {
        $results = [];

        foreach (ProviderEndpoint::cases() as $endpoint) {
            if (! $endpoint->hasModelsEndpoint()) {
                continue;
            }

            $models = $this->get($endpoint->value);

            if ($models !== null) {
                $results[$endpoint->value] = $models;
            }
        }

        return $results;
    }

    /**
     * Resolve a provider string or enum to a ProviderEndpoint.
     */
    protected function resolveEndpoint(Provider|string $provider): ?ProviderEndpoint
    {
        $value = $provider instanceof Provider ? $provider->value : $provider;

        return ProviderEndpoint::tryFrom($value);
    }

    /**
     * Fetch models from the provider API.
     *
     * @return list<string>|null
     */
    protected function fetchModels(ProviderEndpoint $endpoint): ?array
    {
        $config = config("prism.providers.{$endpoint->value}");

        if (! is_array($config)) {
            return null;
        }

        $apiKey = $config['api_key'] ?? '';
        $baseUrl = $config['url'] ?? '';

        if ($apiKey === '' && ! $endpoint->isKeyless()) {
            return null;
        }

        if ($baseUrl === '') {
            return null;
        }

        if ($endpoint === ProviderEndpoint::Ollama) {
            return $this->fetchOllamaModels($endpoint, $baseUrl, $apiKey);
        }

        return $this->makeRequest(
            $endpoint->buildUrl($baseUrl, $apiKey),
            $endpoint->buildHeaders($apiKey),
            $endpoint->responseFormat(),
        );
    }

    /**
     * Fetch Ollama models with fallback from /v1/models to /api/tags.
     *
     * @return list<string>|null
     */
    protected function fetchOllamaModels(ProviderEndpoint $endpoint, string $baseUrl, string $apiKey): ?array
    {
        $headers = $apiKey !== '' ? $endpoint->buildHeaders($apiKey) : [];

        $models = $this->makeRequest(
            $endpoint->buildUrl($baseUrl, $apiKey),
            $headers,
            ResponseFormat::OpenAiCompatible,
        );

        if ($models !== null) {
            return $models;
        }

        $fallbackUrl = rtrim($baseUrl, '/').$endpoint->ollamaFallbackPath();

        return $this->makeRequest($fallbackUrl, $headers, ResponseFormat::ModelsArray);
    }

    /**
     * Make an HTTP request and parse the response.
     *
     * @param  array<string, string>  $headers
     * @return list<string>|null
     */
    protected function makeRequest(string $url, array $headers, ?ResponseFormat $format): ?array
    {
        if ($format === null) {
            return null;
        }

        try {
            $response = $this->http->withHeaders($headers)->get($url);

            if (! $response->successful()) {
                Log::warning('Atlas: Failed to fetch models', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $json = $response->json();

            if (! is_array($json)) {
                return null;
            }

            return match ($format) {
                ResponseFormat::OpenAiCompatible => ModelResponseParser::parseOpenAiCompatible($json),
                ResponseFormat::Anthropic => ModelResponseParser::parseAnthropic($json),
                ResponseFormat::Gemini => ModelResponseParser::parseGemini($json),
                ResponseFormat::ModelsArray => ModelResponseParser::parseModelsArray($json),
                ResponseFormat::ElevenLabs => ModelResponseParser::parseElevenLabs($json),
            };
        } catch (\Throwable $e) {
            Log::warning('Atlas: Exception fetching models', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Generate the cache key for a provider.
     */
    protected function cacheKey(ProviderEndpoint $endpoint): string
    {
        return "atlas:models:{$endpoint->value}";
    }

    /**
     * Check if caching is enabled.
     */
    protected function isCacheEnabled(): bool
    {
        return config('atlas.models.cache.enabled', true) === true;
    }

    /**
     * Get the cache TTL in seconds.
     */
    protected function cacheTtl(): int
    {
        return (int) config('atlas.models.cache.ttl', 3600);
    }
}
