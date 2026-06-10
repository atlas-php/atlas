<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Exceptions;

use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Thrown when a provider signals it is rate limited or overloaded (HTTP 429,
 * and Anthropic's 529 Overloaded).
 */
class RateLimitException extends ProviderException
{
    public function __construct(
        string $provider,
        string $model,
        public readonly ?int $retryAfter = null,
        ?Throwable $previous = null,
        int $statusCode = 429,
    ) {
        parent::__construct($provider, $model, $statusCode, 'Rate limit exceeded.', $previous);
    }

    protected function buildMessage(): string
    {
        return "Rate limit exceeded for [{$this->provider}] model [{$this->model}].";
    }

    /**
     * Create from a request exception, extracting the Retry-After header and
     * preserving the real status (429 or 529). The $message argument exists for
     * signature compatibility with the parent factory and is unused — a rate
     * limit's message is fixed.
     */
    public static function from(string $provider, string $model, RequestException $e, ?string $message = null): self
    {
        $retryAfter = (int) $e->response->header('Retry-After') ?: null;

        return new self($provider, $model, $retryAfter, $e, $e->response->status());
    }
}
