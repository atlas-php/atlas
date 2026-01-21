<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Exceptions;

use Atlasphp\Atlas\Foundation\Exceptions\AtlasException;

/**
 * Exception for provider-related errors.
 *
 * Provides static factory methods for common provider error scenarios.
 */
class ProviderException extends AtlasException
{
    /**
     * Create an exception for unknown provider.
     *
     * @param  string  $provider  The provider name that was not found.
     */
    public static function unknownProvider(string $provider): self
    {
        return new self("Unknown provider: '{$provider}'.");
    }

    /**
     * Create an exception for missing configuration.
     *
     * @param  string  $key  The missing configuration key.
     * @param  string  $provider  The provider name.
     */
    public static function missingConfiguration(string $key, string $provider): self
    {
        return new self("Missing configuration '{$key}' for provider '{$provider}'.");
    }

    /**
     * Create an exception for invalid provider configuration value.
     *
     * @param  string  $key  The invalid configuration key.
     * @param  string  $provider  The provider name.
     * @param  string  $reason  The reason the configuration is invalid.
     */
    public static function invalidConfigurationValue(string $key, string $provider, string $reason): self
    {
        return new self("Invalid configuration '{$key}' for provider '{$provider}': {$reason}.");
    }

    /**
     * Create an exception for API errors.
     *
     * @param  string  $provider  The provider name.
     * @param  string  $message  The error message from the API.
     * @param  int  $code  The error code.
     */
    public static function apiError(string $provider, string $message, int $code = 0): self
    {
        return new self("API error from '{$provider}': {$message}", $code);
    }
}
