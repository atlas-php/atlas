<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Exceptions;

use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Thrown when provider authentication fails (HTTP 401).
 */
class AuthenticationException extends ProviderException
{
    public function __construct(string $provider, string $model = '', ?Throwable $previous = null, string $providerMessage = '')
    {
        parent::__construct($provider, $model, 401, $providerMessage, $previous);
    }

    /**
     * Create from a request exception, preserving the provider's real 401 message
     * (e.g. "Incorrect API key provided") so the developer sees why auth failed.
     */
    public static function from(string $provider, string $model, RequestException $e, ?string $message = null): self
    {
        return new self($provider, $model, $e, self::resolveMessage($e, $message));
    }

    protected function buildMessage(): string
    {
        return "Authentication failed for provider [{$this->provider}]".$this->providerSuffix();
    }
}
