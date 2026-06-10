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

    /**
     * Create an exception for an embedding whose dimension does not match the
     * configured (and column-sized) `atlas.embeddings.dimensions`.
     */
    public static function dimensionMismatch(int $expected, int $actual): self
    {
        return new self(
            "Embedding provider returned a {$actual}-dimension vector but "
            ."atlas.embeddings.dimensions is {$expected}. The vector column is "
            ."sized to {$expected}, so this write would be rejected. Set "
            ."ATLAS_EMBEDDING_DIMENSIONS to your embedding model's dimension, "
            ."or pass a 'dimensions' provider option that matches the column."
        );
    }
}
