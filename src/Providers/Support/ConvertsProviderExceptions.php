<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Support;

use Atlasphp\Atlas\Providers\Exceptions\ProviderOverloadedException;
use Atlasphp\Atlas\Providers\Exceptions\RateLimitedException;
use Atlasphp\Atlas\Providers\Exceptions\RequestTooLargeException;
use Atlasphp\Atlas\Providers\Exceptions\StructuredDecodingException;
use Prism\Prism\Exceptions\PrismProviderOverloadedException;
use Prism\Prism\Exceptions\PrismRateLimitedException;
use Prism\Prism\Exceptions\PrismRequestTooLargeException;
use Prism\Prism\Exceptions\PrismStructuredDecodingException;
use Throwable;

/**
 * Trait for converting Prism exceptions to Atlas exceptions.
 *
 * Provides a centralized method for mapping Prism-specific exceptions
 * to their Atlas equivalents, preserving metadata and context.
 */
trait ConvertsProviderExceptions
{
    /**
     * Convert a Prism exception to an Atlas exception if applicable.
     *
     * Returns the converted Atlas exception if the input is a known Prism
     * exception type, otherwise returns the original exception unchanged.
     */
    protected function convertPrismException(Throwable $exception): Throwable
    {
        return match (true) {
            $exception instanceof PrismRateLimitedException => RateLimitedException::fromPrism($exception),
            $exception instanceof PrismProviderOverloadedException => ProviderOverloadedException::fromPrism($exception),
            $exception instanceof PrismRequestTooLargeException => RequestTooLargeException::fromPrism($exception),
            $exception instanceof PrismStructuredDecodingException => StructuredDecodingException::fromPrism($exception),
            default => $exception,
        };
    }
}
