<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Contracts;

/**
 * Contract for extension resolvers.
 *
 * Defines the interface for classes that resolve extensions by key.
 * Used in extension registries to provide pluggable functionality.
 *
 * Note: The base AbstractExtensionRegistry uses key() for registration
 * and lookup. Custom registries may use supports() for more flexible
 * key matching (e.g., pattern-based or multi-key resolvers).
 */
interface ExtensionResolverContract
{
    /**
     * Get the unique key for this resolver.
     *
     * This is the primary key used by AbstractExtensionRegistry
     * for registration and lookup.
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
     * The base AbstractExtensionRegistry does not invoke this method,
     * using key() for direct lookup instead. Custom registry implementations
     * may use this for more flexible resolution strategies (e.g., pattern
     * matching, prefix-based lookup, or multi-key support).
     *
     * @param  string  $key  The key to check.
     * @return bool True if this resolver handles the key.
     */
    public function supports(string $key): bool;
}
