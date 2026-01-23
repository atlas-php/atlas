<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Exceptions;

use Prism\Prism\Exceptions\PrismRequestTooLargeException;

/**
 * Exception thrown when a request exceeds the provider's size limits.
 *
 * Wraps the underlying Prism request too large exception for consistent
 * exception handling within Atlas.
 */
class RequestTooLargeException extends ProviderException
{
    /**
     * Create an instance from a Prism request too large exception.
     */
    public static function fromPrism(PrismRequestTooLargeException $exception): self
    {
        return new self(
            message: $exception->getMessage(),
            code: $exception->getCode(),
            previous: $exception,
        );
    }
}
