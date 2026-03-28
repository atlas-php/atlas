<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Exceptions;

use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Thrown when a provider returns an unexpected error.
 */
class ProviderException extends AtlasException
{
    public function __construct(
        public readonly string $provider,
        public readonly string $model,
        public readonly int $statusCode,
        public readonly string $providerMessage,
        ?Throwable $previous = null,
    ) {
        parent::__construct("Provider [{$provider}] error [{$statusCode}]: {$providerMessage}", 0, $previous);
    }

    /**
     * Create from a request exception, extracting status code and error message.
     */
    public static function from(string $provider, string $model, RequestException $e): self
    {
        return new self(
            $provider,
            $model,
            $e->response->status(),
            $e->response->json('error.message', $e->getMessage()),
            $e,
        );
    }
}
