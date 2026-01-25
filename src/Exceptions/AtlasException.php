<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Exceptions;

use Exception;

/**
 * Base exception for all Atlas-related errors.
 *
 * Provides static factory methods for common error scenarios.
 */
class AtlasException extends Exception
{
    /**
     * Create an exception for duplicate registration attempts.
     *
     * @param  string  $type  The type of item being registered.
     * @param  string  $key  The duplicate key.
     */
    public static function duplicateRegistration(string $type, string $key): self
    {
        return new self("A {$type} with key '{$key}' has already been registered.");
    }

    /**
     * Create an exception for items not found.
     *
     * @param  string  $type  The type of item being searched.
     * @param  string  $key  The key that was not found.
     */
    public static function notFound(string $type, string $key): self
    {
        return new self("No {$type} found with key '{$key}'.");
    }

    /**
     * Create an exception for invalid configuration.
     *
     * @param  string  $message  The configuration error message.
     */
    public static function invalidConfiguration(string $message): self
    {
        return new self("Invalid configuration: {$message}");
    }
}
