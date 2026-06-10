<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Exceptions;

use Throwable;

/**
 * Thrown when a provider request fails at the network level before any HTTP
 * response is received (connection timeout, DNS failure, refused connection).
 *
 * Distinct from ProviderException, which represents an HTTP error response the
 * provider actually returned. Connection failures carry no HTTP status.
 */
class ConnectionException extends AtlasException
{
    public function __construct(
        public readonly string $provider,
        public readonly string $model,
        ?Throwable $previous = null,
    ) {
        $reason = $previous?->getMessage();

        parent::__construct(
            "Connection to provider [{$provider}] failed".($reason !== null && $reason !== '' ? ": {$reason}" : '.'),
            0,
            $previous,
        );
    }
}
