<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Exceptions;

use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Thrown when provider authorization fails (HTTP 403).
 */
class AuthorizationException extends ProviderException
{
    public function __construct(string $provider, string $model = '', ?Throwable $previous = null, string $providerMessage = '')
    {
        parent::__construct($provider, $model, 403, $providerMessage, $previous);
    }

    /**
     * Create from a request exception, preserving the provider's real 403 message
     * (e.g. "Your account is not authorized to use model X") so the developer sees
     * why access was denied.
     */
    public static function from(string $provider, string $model, RequestException $e, ?string $message = null): self
    {
        return new self($provider, $model, $e, self::resolveMessage($e, $message));
    }

    protected function buildMessage(): string
    {
        return "Authorization failed for [{$this->provider}] model [{$this->model}]".$this->providerSuffix();
    }
}
