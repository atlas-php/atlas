<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Foundation\Contracts;

/**
 * Contract for extension resolvers.
 *
 * Defines the interface for classes that resolve extensions by key.
 * Used in extension registries to provide pluggable functionality.
 */
interface ExtensionResolverContract
{
    /**
     * Get the unique key for this resolver.
     *
     * @return string The resolver's unique identifier.
     */
    public function key(): string;

    /**
     * Resolve and return the extension instance.
     *
     * @return mixed The resolved extension.
     */
    public function resolve(): mixed;

    /**
     * Check if this resolver supports the given key.
     *
     * @param  string  $key  The key to check.
     * @return bool True if this resolver handles the key.
     */
    public function supports(string $key): bool;
}
