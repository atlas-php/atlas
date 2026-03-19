<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Exceptions;

use Throwable;

/**
 * Thrown when provider authentication fails (HTTP 401).
 */
class AuthenticationException extends AtlasException
{
    public function __construct(
        public readonly string $provider,
        ?Throwable $previous = null,
    ) {
        parent::__construct("Authentication failed for provider [{$provider}].", 0, $previous);
    }
}
