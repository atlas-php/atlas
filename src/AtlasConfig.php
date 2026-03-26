<?php

declare(strict_types=1);

namespace Atlasphp\Atlas;

use Atlasphp\Atlas\Embeddings\EmbeddingResolver;
use Atlasphp\Atlas\Exceptions\ProviderNotFoundException;
use Atlasphp\Atlas\Middleware\MiddlewareResolver;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\ProviderRegistry;
use Atlasphp\Atlas\Support\VariableRegistry;
use ReflectionProperty;

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
        public readonly array $defaults = [],
        /** @var array<string, array<string, mixed>> */
        public readonly array $providers = [],
        public readonly string $queue = 'default',
        /** @var array<int, class-string|object> */
        public readonly array $middleware = [],
        /** @var array<string, mixed> */
        public readonly array $variables = [],
        public readonly int $retryTimeout = 60,
        public readonly int $retryRateLimit = 3,
        public readonly int $retryErrors = 2,
        public readonly int $streamChunkDelayUs = 15_000,
        public readonly ?string $storageDisk = null,
        public readonly string $storagePrefix = 'atlas',
        public readonly int $embeddingDimensions = 1536,
        public readonly ?string $cacheStore = null,
        public readonly string $cachePrefix = 'atlas',
        /** @var array{models: int, voices: int, embeddings: int} */
        public readonly array $cacheTtl = ['models' => 86400, 'voices' => 3600, 'embeddings' => 0],
        public readonly bool $persistenceEnabled = false,
        public readonly string $tablePrefix = 'atlas_',
        public readonly int $messageLimit = 50,
        public readonly bool $autoStoreAssets = true,
        public readonly string $voiceRoutePrefix = 'atlas',
        public readonly int $voiceSessionTtl = 60,
        /** @var array<string, class-string> */
        public readonly array $persistenceModels = [],
        /** @var array{path: string|null, namespace: string|null} */
        public readonly array $agents = ['path' => null, 'namespace' => null],
    ) {}

    /**
     * Refresh the singleton instance from current config values.
     *
     * Rebuilds the AtlasConfig singleton and forces all dependent
     * singletons to be re-resolved on next access.
     * Useful in tests after modifying config at runtime.
     */
    public static function refresh(): self
    {
        $instance = self::fromConfig();
        app()->instance(self::class, $instance);

        // Force dependent singletons to re-resolve with the new config.
        // ProviderRegistry is rebuilt with factory transfer to preserve
        // registrations from boot(). AtlasManager is forgotten and re-resolved.
        app()->forgetInstance(VariableRegistry::class);
        app()->forgetInstance(AtlasCache::class);
        app()->forgetInstance(EmbeddingResolver::class);
        app()->forgetInstance(AtlasManager::class);
        app()->forgetInstance(MiddlewareResolver::class);

        // Rebuild ProviderRegistry with new config while preserving factories
        $container = app();
        if ($container->resolved(ProviderRegistryContract::class)) {
            $oldRegistry = $container->make(ProviderRegistryContract::class);
            $newRegistry = new ProviderRegistry($container, $instance);

            // Re-register all factories from the old registry
            $factoriesRef = new ReflectionProperty($oldRegistry, 'factories');
            foreach ($factoriesRef->getValue($oldRegistry) as $key => $factory) {
                $newRegistry->register($key, $factory);
            }

            $container->instance(ProviderRegistryContract::class, $newRegistry);
        }

        return $instance;
    }

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
            voiceRoutePrefix: config('atlas.persistence.voice_route_prefix', 'atlas'),
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
