<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Exceptions;

/**
 * Thrown when attempting to resolve a provider that has not been registered.
 */
class ProviderNotFoundException extends AtlasException
{
    public function __construct(string $key)
    {
        parent::__construct("No provider registered for key [{$key}].");
    }
}
