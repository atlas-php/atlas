<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Foundation\Services;

use Atlasphp\Atlas\Foundation\Contracts\ExtensionResolverContract;
use Atlasphp\Atlas\Foundation\Exceptions\AtlasException;

/**
 * Base class for extension registries.
 *
 * Provides common registration, retrieval, and query functionality
 * for managing collections of named extensions.
 */
abstract class AbstractExtensionRegistry
{
    /**
     * Registered extension resolvers keyed by their identifier.
     *
     * @var array<string, ExtensionResolverContract>
     */
    protected array $resolvers = [];

    /**
     * Register an extension resolver.
     *
     * @throws AtlasException If a resolver with the same key is already registered.
     */
    public function register(ExtensionResolverContract $resolver): static
    {
        $key = $resolver->key();

        if (isset($this->resolvers[$key])) {
            throw AtlasException::duplicateRegistration('extension resolver', $key);
        }

        $this->resolvers[$key] = $resolver;

        return $this;
    }

    /**
     * Get a resolved extension by key.
     *
     * @throws AtlasException If no resolver is registered for the given key.
     */
    public function get(string $key): mixed
    {
        if (! isset($this->resolvers[$key])) {
            throw AtlasException::notFound('extension resolver', $key);
        }

        return $this->resolvers[$key]->resolve();
    }

    /**
     * Check if a resolver supports the given key.
     */
    public function supports(string $key): bool
    {
        return isset($this->resolvers[$key]);
    }

    /**
     * Get all registered resolver keys.
     *
     * @return array<int, string>
     */
    public function registered(): array
    {
        return array_keys($this->resolvers);
    }

    /**
     * Check if any resolvers are registered.
     */
    public function hasResolvers(): bool
    {
        return $this->resolvers !== [];
    }

    /**
     * Get the count of registered resolvers.
     */
    public function count(): int
    {
        return count($this->resolvers);
    }
}
