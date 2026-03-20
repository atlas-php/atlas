<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Exceptions;

use RuntimeException;

/**
 * Base exception for all Atlas errors.
 */
class AtlasException extends RuntimeException
{
    /**
     * Create an exception for a missing modality default.
     */
    public static function missingDefault(string $modality): self
    {
        $envVar = 'ATLAS_'.strtoupper($modality).'_PROVIDER';

        return new self(
            "No provider specified and no default configured for {$modality}. "
            ."Set {$envVar} in your .env or pass a provider."
        );
    }

    /**
     * Create an exception for an unknown driver.
     */
    public static function unknownDriver(string $driver, string $key): self
    {
        return new self("Unknown driver '{$driver}' for provider '{$key}'.");
    }
}
