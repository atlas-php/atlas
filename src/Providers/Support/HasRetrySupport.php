<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Support;

use Closure;

/**
 * Trait for services that support retry configuration.
 *
 * Provides a fluent withRetry() method for configuring retry behavior
 * on API requests. Uses the clone pattern for immutability.
 *
 * Stores configuration as a raw array to avoid unnecessary object creation
 * when passing through the system to Prism's withClientRetry() method.
 */
trait HasRetrySupport
{
    /**
     * Retry configuration array: [times, sleepMilliseconds, when, throw].
     *
     * @var array{0: array<int, int>|int, 1: Closure|int, 2: callable|null, 3: bool}|null
     */
    private ?array $retryConfig = null;

    /**
     * Configure retry behavior for API requests.
     *
     * @param  array<int, int>|int  $times  Number of attempts OR array of delays [100, 200, 300].
     * @param  Closure|int  $sleepMilliseconds  Fixed ms OR fn(int $attempt, Throwable $e): int for dynamic.
     * @param  callable|null  $when  fn(Throwable $e, PendingRequest $req): bool to control retry conditions.
     * @param  bool  $throw  Whether to throw after all retries fail.
     */
    public function withRetry(
        array|int $times,
        Closure|int $sleepMilliseconds = 0,
        ?callable $when = null,
        bool $throw = true,
    ): static {
        $clone = clone $this;
        $clone->retryConfig = [$times, $sleepMilliseconds, $when, $throw];

        return $clone;
    }

    /**
     * Get the retry configuration array for passing to PrismBuilder.
     *
     * Returns null if no retry is configured, otherwise returns the raw array.
     * The PrismBuilder handles checking if retry is actually enabled (times > 0).
     *
     * @return array{0: array<int, int>|int, 1: Closure|int, 2: callable|null, 3: bool}|null
     */
    protected function getRetryArray(): ?array
    {
        return $this->retryConfig;
    }
}
