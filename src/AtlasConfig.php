<?php

declare(strict_types=1);

namespace Atlasphp\Atlas;

use Atlasphp\Atlas\Exceptions\ProviderNotFoundException;

/**
 * Centralized configuration for the Atlas package.
 *
 * Reads all values from config/atlas.php at construction time and exposes
 * them as typed properties. All classes that need configuration receive
 * this singleton via constructor injection — no raw config() calls.
 */
class AtlasConfig
{
    public function __construct(
        /** @var array<string, array{provider?: string|null, model?: string|null}> */
        public readonly array $defaults,
        /** @var array<string, array<string, mixed>> */
        public readonly array $providers,
        public readonly string $queue,
        /** @var array{agent: array<int, class-string>, step: array<int, class-string>, tool: array<int, class-string>, provider: array<int, class-string>} */
        public readonly array $middleware,
        /** @var array<string, mixed> */
        public readonly array $variables,
        public readonly int $retryTimeout,
        public readonly int $retryRateLimit,
        public readonly int $retryErrors,
        public readonly int $streamChunkDelayUs,
        public readonly ?string $storageDisk,
        public readonly string $storagePrefix,
        public readonly int $embeddingDimensions,
        public readonly ?string $cacheStore,
        public readonly string $cachePrefix,
        /** @var array{models: int, voices: int, embeddings: int} */
        public readonly array $cacheTtl,
        public readonly bool $persistenceEnabled,
        public readonly string $tablePrefix,
        public readonly int $messageLimit,
        public readonly bool $autoStoreAssets,
        /** @var array<string, mixed> */
        public readonly array $voiceTranscripts,
        public readonly int $voiceSessionTtl,
        /** @var array<string, class-string> */
        public readonly array $persistenceModels,
        /** @var array{path: string|null, namespace: string|null} */
        public readonly array $agents,
    ) {}

    /**
     * Build from the published config file.
     */
    public static function fromConfig(): self
    {
        return new self(
            defaults: config('atlas.defaults', []),
            providers: config('atlas.providers', []),
            queue: config('atlas.queue', 'default'),
            middleware: config('atlas.middleware', []),
            variables: config('atlas.variables', []),
            retryTimeout: (int) config('atlas.retry.timeout', 60),
            retryRateLimit: (int) config('atlas.retry.rate_limit', 3),
            retryErrors: (int) config('atlas.retry.errors', 2),
            streamChunkDelayUs: (int) config('atlas.stream.chunk_delay_us', 15_000),
            storageDisk: config('atlas.storage.disk'),
            storagePrefix: config('atlas.storage.prefix', 'atlas'),
            embeddingDimensions: (int) config('atlas.embeddings.dimensions', 1536),
            cacheStore: config('atlas.cache.store'),
            cachePrefix: config('atlas.cache.prefix', 'atlas'),
            cacheTtl: config('atlas.cache.ttl', ['models' => 86400, 'voices' => 3600, 'embeddings' => 0]),
            persistenceEnabled: (bool) config('atlas.persistence.enabled', false),
            tablePrefix: config('atlas.persistence.table_prefix', 'atlas_'),
            messageLimit: (int) config('atlas.persistence.message_limit', 50),
            autoStoreAssets: (bool) config('atlas.persistence.auto_store_assets', true),
            voiceTranscripts: config('atlas.persistence.voice_transcripts', ['enabled' => true, 'middleware' => [], 'route_prefix' => 'atlas']),
            voiceSessionTtl: (int) config('atlas.persistence.voice_session_ttl', 60),
            persistenceModels: config('atlas.persistence.models', []),
            agents: config('atlas.agents', ['path' => null, 'namespace' => null]),
        );
    }

    /**
     * Get raw provider config by key.
     *
     * @return array<string, mixed>
     *
     * @throws ProviderNotFoundException
     */
    public function forProvider(string $key): array
    {
        return $this->providers[$key]
            ?? throw new ProviderNotFoundException($key);
    }

    /**
     * Check whether a provider is configured.
     */
    public function hasProvider(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    /**
     * Get the default provider and model for a modality.
     *
     * @return array{provider: string, model: string|null}|null
     */
    public function defaultFor(string $modality): ?array
    {
        $default = $this->defaults[$modality] ?? null;

        if ($default === null || empty($default['provider'])) {
            return null;
        }

        return $default;
    }

    /**
     * Resolve a persistence model class, falling back to the Atlas default.
     *
     * @param  class-string  $default
     * @return class-string
     */
    public function model(string $key, string $default): string
    {
        return $this->persistenceModels[$key] ?? $default;
    }
}
