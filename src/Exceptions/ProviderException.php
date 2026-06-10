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
     *
     * Optionally accepts a message already resolved by the driver (e.g. via a
     * provider-specific override) which takes precedence over the shared chain.
     */
    public static function from(string $provider, string $model, RequestException $e, ?string $message = null): self
    {
        $body = $e->response->json();

        return new self(
            $provider,
            $model,
            $e->response->status(),
            $message ?? self::extractMessage(is_array($body) ? $body : []) ?? $e->getMessage(),
            $e,
        );
    }

    /**
     * Create from a provider error payload received mid-stream.
     *
     * Stream errors arrive as SSE event payloads rather than HTTP responses, so
     * they carry no HTTP status. The message is extracted from the common
     * provider error shapes.
     *
     * @param  array<string, mixed>  $error
     */
    public static function fromStreamError(string $provider, string $model, array $error, ?int $status = null): self
    {
        $rawCode = data_get($error, 'code');
        $code = $status ?? (is_int($rawCode) ? $rawCode : 0);

        return new self(
            $provider,
            $model,
            $code,
            self::extractMessage($error) ?? 'Provider returned an error during streaming.',
        );
    }

    /**
     * Extract a human-readable error message from a decoded provider error body.
     *
     * Providers nest the message differently: OpenAI/Anthropic/Google/xAI use
     * `error.message`; ElevenLabs/Jina use `detail` (string) or `detail.message`;
     * Cohere uses a top-level `message`; Ollama uses a string `error`. Returns
     * null when no recognizable message is present.
     *
     * Order is most-specific to least: the structured `error.message` is
     * preferred over a bare top-level `message`, since when both exist the
     * nested one is the canonical provider error.
     *
     * @param  array<string, mixed>  $body
     */
    private static function extractMessage(array $body): ?string
    {
        foreach (['error.message', 'detail.message', 'detail', 'message', 'error'] as $path) {
            $value = data_get($body, $path);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
