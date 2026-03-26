<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Http;

use Atlasphp\Atlas\Exceptions\ProviderException;
use Atlasphp\Atlas\Exceptions\RateLimitException;
use Atlasphp\Atlas\RequestConfig;

/**
 * Stateless retry policy for provider HTTP calls.
 *
 * Answers two questions: should this exception be retried, and how long
 * to wait before the next attempt.
 */
class RetryDecider
{
    /**
     * Whether the exception should be retried given the config and attempt count.
     *
     * Rate limits (429) are retried up to rateLimit times.
     * Transient errors (5xx, connection failures) are retried up to errors times.
     * Permanent failures (401, 403, other 4xx) are never retried.
     */
    public function shouldRetry(\Throwable $e, RequestConfig $config, int $attempt): bool
    {
        if ($e instanceof RateLimitException) {
            return $config->rateLimit > 0 && $attempt <= $config->rateLimit;
        }

        if ($e instanceof ProviderException && $this->isTransient($e)) {
            return $config->errors > 0 && $attempt <= $config->errors;
        }

        return false;
    }

    /**
     * Wait time in microseconds before the next attempt.
     *
     * Rate limits: respects Retry-After header, capped at 60 seconds.
     * Transient errors: exponential backoff with full jitter, capped at 10 seconds.
     */
    public function waitMicroseconds(\Throwable $e, int $attempt): int
    {
        if ($e instanceof RateLimitException) {
            $wait = min($e->retryAfter ?? 1, 60);

            return $wait * 1_000_000;
        }

        // Full jitter: random(0, min(10000ms, 500ms * 2^attempt))
        $capMs = 10_000;
        $baseMs = 500;
        $ceilingMs = min($capMs, $baseMs * (2 ** $attempt));

        return random_int(0, (int) $ceilingMs) * 1_000;
    }

    /**
     * Whether the error is transient (worth retrying).
     */
    protected function isTransient(ProviderException $e): bool
    {
        return in_array($e->statusCode, [0, 500, 502, 503, 504], true);
    }
}
