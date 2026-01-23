<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Exceptions;

use Prism\Prism\Exceptions\PrismStructuredDecodingException;

/**
 * Exception thrown when structured output cannot be decoded.
 *
 * Wraps the underlying Prism structured decoding exception for consistent
 * exception handling within Atlas.
 */
class StructuredDecodingException extends ProviderException
{
    /**
     * Create an instance from a Prism structured decoding exception.
     */
    public static function fromPrism(PrismStructuredDecodingException $exception): self
    {
        return new self(
            message: $exception->getMessage(),
            code: $exception->getCode(),
            previous: $exception,
        );
    }
}
