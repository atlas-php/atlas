<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Exceptions;

use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Base for all provider-side errors: the request reached (or tried to reach) a
 * provider and something went wrong. Catch this to handle any provider failure;
 * catch a subclass (AuthenticationException, RateLimitException, etc.) for a
 * specific category.
 *
 * `statusCode` is null when no HTTP response was received (see ConnectionException).
 */
class ProviderException extends AtlasException
{
    public function __construct(
        public readonly string $provider,
        public readonly string $model,
        public readonly ?int $statusCode,
        public readonly string $providerMessage,
        ?Throwable $previous = null,
    ) {
        parent::__construct($this->buildMessage(), 0, $previous);
    }

    /**
     * The provider's raw response body, decoded as an array.
     *
     * Returns null when the failure carried no HTTP response (e.g.
     * ConnectionException) or the body was not valid JSON. Reads through the
     * preserved request exception so debugging doesn't require digging into
     * `getPrevious()` by hand.
     *
     * @return array<string, mixed>|null
     */
    public function responseBody(): ?array
    {
        $previous = $this->getPrevious();

        if (! $previous instanceof RequestException) {
            return null;
        }

        $body = $previous->response->json();

        return is_array($body) ? $body : null;
    }

    /**
     * The provider's raw response body as an unparsed string.
     *
     * Returns null when the failure carried no HTTP response. Useful when the
     * body is not JSON (HTML error pages, plain text) or you need the exact bytes.
     */
    public function rawResponse(): ?string
    {
        $previous = $this->getPrevious();

        return $previous instanceof RequestException ? $previous->response->body() : null;
    }

    /**
     * Build the human-readable exception message. Subclasses override this to
     * keep their own phrasing while still sharing the base properties.
     */
    protected function buildMessage(): string
    {
        $status = $this->statusCode !== null ? " [{$this->statusCode}]" : '';

        return "Provider [{$this->provider}] error{$status}: {$this->providerMessage}";
    }

    /**
     * The ": {message}" suffix subclasses append to their own phrasing when the
     * provider supplied an error message, or a closing period when it didn't.
     */
    protected function providerSuffix(): string
    {
        return $this->providerMessage !== '' ? ": {$this->providerMessage}" : '.';
    }

    /**
     * Create from a request exception, extracting status code and error message.
     *
     * Optionally accepts a message already resolved by the driver (e.g. via a
     * provider-specific override) which takes precedence over the shared chain.
     */
    public static function from(string $provider, string $model, RequestException $e, ?string $message = null): self
    {
        return new self($provider, $model, $e->response->status(), self::resolveMessage($e, $message), $e);
    }

    /**
     * Resolve the provider error message from a failed response, honoring a
     * caller-supplied override, then the shared extraction chain, then the
     * request exception's own message. Used by the typed subclasses too.
     */
    public static function resolveMessage(RequestException $e, ?string $override = null): string
    {
        $body = $e->response->json();

        return $override ?? self::extractMessage(is_array($body) ? $body : []) ?? $e->getMessage();
    }

    /**
     * Create from a provider error payload received mid-stream.
     *
     * Returns the base ProviderException, not a typed subclass: a mid-stream
     * error arrives after a successful, authenticated connection, so the
     * status-based categories (auth, not-found, invalid-request) don't apply —
     * those are already classified at connection time by handleRequestException.
     * Mid-stream errors are overload/server conditions; catch ProviderException.
     *
     * Stream errors arrive as SSE event payloads rather than HTTP responses, so
     * they usually carry no HTTP status. The message is extracted from the
     * common provider error shapes.
     *
     * @param  array<string, mixed>  $error
     */
    public static function fromStreamError(string $provider, string $model, array $error, ?int $status = null): self
    {
        $rawCode = data_get($error, 'code');
        // Null (not 0) when there's no real status, so buildMessage omits the bracket.
        $code = $status ?? (is_int($rawCode) && $rawCode > 0 ? $rawCode : null);

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
