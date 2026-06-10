<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Exceptions;

use Throwable;

/**
 * Thrown when provider authorization fails (HTTP 403).
 */
class AuthorizationException extends ProviderException
{
    public function __construct(string $provider, string $model, ?Throwable $previous = null)
    {
        parent::__construct($provider, $model, 403, '', $previous);
    }

    protected function buildMessage(): string
    {
        return "Authorization failed for [{$this->provider}] model [{$this->model}].";
    }
}
