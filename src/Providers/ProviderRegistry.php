<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers;

use Atlasphp\Atlas\AtlasCache;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Exceptions\ProviderNotFoundException;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Middleware\MiddlewareResolver;
use Atlasphp\Atlas\Middleware\MiddlewareStack;
use Atlasphp\Atlas\Providers\ChatCompletions\ChatCompletionsDriver;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Closure;
use Illuminate\Contracts\Foundation\Application;

/**
 * Closure-based factory registry with lazy resolution and per-key caching.
 *
 * Stores provider factories by key and resolves them on first access,
 * caching the result for subsequent calls.
 */
class ProviderRegistry implements ProviderRegistryContract
{
    /** @var array<string, Closure> */
    protected array $factories = [];

    /** @var array<string, Driver> */
    protected array $resolved = [];

    public function __construct(
        protected readonly Application $app,
        protected readonly AtlasConfig $config,
    ) {}

    /**
     * Register a factory for the given key.
     */
    public function register(string $key, Closure $factory): void
    {
        $this->factories[$key] = $factory;

        unset($this->resolved[$key]);
    }

    /**
     * Resolve the provider for the given key.
     *
     * @throws ProviderNotFoundException If no factory is registered for the key.
     */
    public function resolve(string $key): Driver
    {
        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        /** @var array<string, mixed> $providerConfig */
        $providerConfig = $this->config->providers[$key] ?? [];

        if (isset($this->factories[$key])) {
            return $this->resolved[$key] = ($this->factories[$key])($this->app, $providerConfig);
        }

        if (isset($providerConfig['driver'])) {
            return $this->resolved[$key] = $this->resolveFromDriver($key, $providerConfig);
        }

        throw new ProviderNotFoundException($key);
    }

    /**
     * Resolve a driver from the config 'driver' key.
     *
     * @param  array<string, mixed>  $config
     */
    protected function resolveFromDriver(string $key, array $config): Driver
    {
        $driver = $config['driver'];
        $providerConfig = ProviderConfig::fromArray($config);
        $http = $this->app->make(HttpClient::class);
        $stack = $this->app->make(MiddlewareStack::class);

        $cache = $this->app->make(AtlasCache::class);
        $resolver = $this->app->make(MiddlewareResolver::class);

        return match (true) {
            $driver === 'chat_completions' => new ChatCompletionsDriver($providerConfig, $http, $stack, $cache, $resolver),
            $driver === 'responses' => new ResponsesDriver($providerConfig, $http, $stack, $cache, $resolver),
            is_string($driver) && class_exists($driver) => $this->app->make($driver, [
                'config' => $providerConfig,
                'http' => $http,
                'middlewareStack' => $stack,
                'cache' => $this->app->make(AtlasCache::class),
                'middlewareResolver' => $resolver,
            ]),
            default => throw AtlasException::unknownDriver($driver, $key),
        };
    }

    /**
     * Determine if a provider can be resolved for the given key.
     *
     * Checks both registered factories and config-only providers
     * that can be resolved via the 'driver' config key.
     */
    public function has(string $key): bool
    {
        return isset($this->factories[$key]) || isset($this->config->providers[$key]['driver']);
    }

    /**
     * Get all available provider keys.
     *
     * Includes both factory-registered providers and config-only providers
     * that declare a 'driver' key.
     *
     * @return array<int, string>
     */
    public function available(): array
    {
        $configKeys = array_keys(
            array_filter($this->config->providers, fn (array $v): bool => isset($v['driver']))
        );

        return array_values(array_unique([...array_keys($this->factories), ...$configKeys]));
    }
}
