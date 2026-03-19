<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Exceptions;

use Throwable;

/**
 * Thrown when provider authorization fails (HTTP 403).
 */
class AuthorizationException extends AtlasException
{
    public function __construct(
        public readonly string $provider,
        public readonly string $model,
        ?Throwable $previous = null,
    ) {
        parent::__construct("Authorization failed for [{$provider}] model [{$model}].", 0, $previous);
    }
}
