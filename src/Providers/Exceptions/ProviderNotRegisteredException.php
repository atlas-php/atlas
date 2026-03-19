<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Exceptions;

use RuntimeException;

/**
 * Thrown when attempting to resolve a provider that has not been registered.
 */
class ProviderNotRegisteredException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("No provider registered for key [{$key}].");
    }
}
