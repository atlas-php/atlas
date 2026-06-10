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

    /**
     * Create from a provider error payload received mid-stream.
     *
     * Stream errors arrive as SSE event payloads rather than HTTP responses, so
     * they carry no HTTP status. The message is extracted from the common
     * provider error shapes (object with `message`, nested `error.message`, or a
     * plain string `error`).
     *
     * @param  array<string, mixed>  $error
     */
    public static function fromStreamError(string $provider, string $model, array $error, ?int $status = null): self
    {
        $nested = data_get($error, 'error');
        $rawCode = data_get($error, 'code');

        $message = data_get($error, 'message')
            ?? data_get($error, 'error.message')
            ?? (is_string($nested) ? $nested : null)
            ?? 'Provider returned an error during streaming.';

        $code = $status ?? (is_int($rawCode) ? $rawCode : 0);

        return new self($provider, $model, $code, (string) $message);
    }
}
