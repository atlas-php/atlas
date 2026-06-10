<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Exceptions;

use Throwable;

/**
 * Thrown when provider authentication fails (HTTP 401).
 */
class AuthenticationException extends ProviderException
{
    public function __construct(string $provider, string $model = '', ?Throwable $previous = null)
    {
        parent::__construct($provider, $model, 401, '', $previous);
    }

    protected function buildMessage(): string
    {
        return "Authentication failed for provider [{$this->provider}].";
    }
}
