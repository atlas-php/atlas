<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Exceptions;

use Throwable;

/**
 * Thrown when a provider request fails at the network level before any HTTP
 * response is received (connection timeout, DNS failure, refused connection).
 *
 * A ProviderException with a null statusCode: it's a provider-communication
 * failure, but no HTTP response was returned.
 */
class ConnectionException extends ProviderException
{
    public function __construct(string $provider, string $model, ?Throwable $previous = null)
    {
        parent::__construct($provider, $model, null, $previous?->getMessage() ?? '', $previous);
    }

    protected function buildMessage(): string
    {
        return "Connection to provider [{$this->provider}] failed".$this->providerSuffix();
    }
}
