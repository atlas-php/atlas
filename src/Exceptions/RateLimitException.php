<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Exceptions;

use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Thrown when a provider returns a rate limit error (HTTP 429).
 */
class RateLimitException extends AtlasException
{
    public function __construct(
        public readonly string $provider,
        public readonly string $model,
        public readonly ?int $retryAfter = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct("Rate limit exceeded for [{$provider}] model [{$model}].", 0, $previous);
    }

    /**
     * Create from a request exception, extracting the Retry-After header.
     */
    public static function from(string $provider, string $model, RequestException $e): self
    {
        $retryAfter = (int) $e->response->header('Retry-After') ?: null;

        return new self($provider, $model, $retryAfter, $e);
    }
}
