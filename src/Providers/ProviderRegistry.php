<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers;

use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Exceptions\ProviderNotFoundException;
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

        /** @var array<string, mixed> $config */
        $config = config("atlas.providers.{$key}", []);

        if (isset($this->factories[$key])) {
            return $this->resolved[$key] = ($this->factories[$key])($this->app, $config);
        }

        if (isset($config['driver'])) {
            return $this->resolved[$key] = $this->resolveFromDriver($key, $config);
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

        return match (true) {
            $driver === 'chat_completions' => new ChatCompletionsDriver($providerConfig, $http, $stack),
            $driver === 'responses' => new ResponsesDriver($providerConfig, $http, $stack),
            is_string($driver) && class_exists($driver) => $this->app->make($driver, ['config' => $providerConfig]),
            default => throw AtlasException::unknownDriver($driver, $key),
        };
    }

    /**
     * Determine if a factory is registered for the given key.
     */
    public function has(string $key): bool
    {
        return isset($this->factories[$key]);
    }

    /**
     * Get all registered provider keys.
     *
     * @return array<int, string>
     */
    public function available(): array
    {
        return array_keys($this->factories);
    }
}
