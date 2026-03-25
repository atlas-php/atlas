<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Contracts;

use Atlasphp\Atlas\Exceptions\ProviderNotFoundException;
use Atlasphp\Atlas\Providers\Driver;
use Closure;

/**
 * Contract for the provider registry.
 *
 * Defines the interface for registering and resolving provider factories.
 */
interface ProviderRegistryContract
{
    /**
     * Register a factory for the given key.
     */
    public function register(string $key, Closure $factory): void;

    /**
     * Resolve the provider for the given key.
     *
     * @throws ProviderNotFoundException
     */
    public function resolve(string $key): Driver;

    /**
     * Determine if a provider can be resolved for the given key.
     */
    public function has(string $key): bool;

    /**
     * Get all registered provider keys.
     *
     * @return array<int, string>
     */
    public function available(): array;
}
