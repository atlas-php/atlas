<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers;

use Atlasphp\Atlas\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Exceptions\ProviderNotFoundException;
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

        $factory = $this->factories[$key]
            ?? throw new ProviderNotFoundException($key);

        /** @var array<string, mixed> $config */
        $config = config("atlas.providers.{$key}", []);

        return $this->resolved[$key] = ($factory)($this->app, $config);
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
