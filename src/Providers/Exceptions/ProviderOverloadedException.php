<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Exceptions;

use Prism\Prism\Exceptions\PrismProviderOverloadedException;

/**
 * Exception thrown when a provider is overloaded and cannot process requests.
 *
 * Wraps the underlying Prism overloaded exception for consistent exception
 * handling within Atlas.
 */
class ProviderOverloadedException extends ProviderException
{
    /**
     * Create an instance from a Prism provider overloaded exception.
     */
    public static function fromPrism(PrismProviderOverloadedException $exception): self
    {
        return new self(
            message: $exception->getMessage(),
            code: $exception->getCode(),
            previous: $exception,
        );
    }
}
