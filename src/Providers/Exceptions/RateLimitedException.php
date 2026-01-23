<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Exceptions;

use Prism\Prism\Exceptions\PrismRateLimitedException;

/**
 * Exception thrown when a provider rate limit is exceeded.
 *
 * Wraps the underlying Prism rate limit exception while preserving
 * rate limit details and retry-after information.
 */
class RateLimitedException extends ProviderException
{
    /**
     * @param  array<int, mixed>  $rateLimits  Rate limit information from the provider.
     * @param  int|null  $retryAfter  Seconds until retry is allowed.
     */
    public function __construct(
        string $message,
        private readonly array $rateLimits = [],
        private readonly ?int $retryAfter = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an instance from a Prism rate limited exception.
     */
    public static function fromPrism(PrismRateLimitedException $exception): self
    {
        return new self(
            message: $exception->getMessage(),
            rateLimits: $exception->rateLimits,
            retryAfter: $exception->retryAfter,
            code: $exception->getCode(),
            previous: $exception,
        );
    }

    /**
     * Get the number of seconds to wait before retrying.
     */
    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }

    /**
     * Get the rate limit information from the provider.
     *
     * @return array<int, mixed>
     */
    public function rateLimits(): array
    {
        return $this->rateLimits;
    }
}
