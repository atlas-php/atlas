<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Http;

use Atlasphp\Atlas\Exceptions\ProviderException;
use Atlasphp\Atlas\Exceptions\RateLimitException;
use Atlasphp\Atlas\RequestConfig;
use Illuminate\Http\Client\RequestException;

/**
 * Stateless retry policy for provider HTTP calls.
 *
 * Answers two questions: should this exception be retried, and how long
 * to wait before the next attempt. Works with both Atlas exceptions
 * (thrown by handlers) and Laravel's RequestException (thrown by HttpClient).
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

        if ($e instanceof ProviderException && $this->isTransientStatus($e->statusCode)) {
            return $config->errors > 0 && $attempt <= $config->errors;
        }

        // Laravel's RequestException from $response->throw()
        if ($e instanceof RequestException) {
            $status = $e->response->status();

            if ($status === 429) {
                return $config->rateLimit > 0 && $attempt <= $config->rateLimit;
            }

            if ($this->isTransientStatus($status)) {
                return $config->errors > 0 && $attempt <= $config->errors;
            }
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

        if ($e instanceof RequestException && $e->response->status() === 429) {
            $retryAfter = (int) ($e->response->header('Retry-After') ?: 1);

            return min($retryAfter, 60) * 1_000_000;
        }

        // Full jitter: random(0, min(10000ms, 500ms * 2^attempt))
        $capMs = 10_000;
        $baseMs = 500;
        $ceilingMs = min($capMs, $baseMs * (2 ** $attempt));

        return random_int(0, (int) $ceilingMs) * 1_000;
    }

    /**
     * Whether the HTTP status code indicates a transient error.
     */
    protected function isTransientStatus(int $status): bool
    {
        return in_array($status, [0, 500, 502, 503, 504], true);
    }
}
