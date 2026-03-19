<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers;

use Atlasphp\Atlas\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Exceptions\ProviderNotRegisteredException;
use Closure;

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

    /** @var array<string, mixed> */
    protected array $resolved = [];

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
     * @throws ProviderNotRegisteredException If no factory is registered for the key.
     */
    public function resolve(string $key): mixed
    {
        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        if (! isset($this->factories[$key])) {
            throw ProviderNotRegisteredException::forKey($key);
        }

        return $this->resolved[$key] = ($this->factories[$key])();
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
