<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Support;

use Closure;

/**
 * Value object for retry configuration.
 *
 * Provides factory methods for common retry strategies and captures
 * configuration to pass through to Prism's withClientRetry() method.
 */
final readonly class RetryConfig
{
    /**
     * @param  array<int, int>|int  $times  Number of attempts OR array of delays [100, 200, 300].
     * @param  Closure|int  $sleepMilliseconds  Fixed ms OR fn(int $attempt, Throwable $e): int for dynamic.
     * @param  callable|null  $when  fn(Throwable $e, PendingRequest $req): bool to control retry conditions.
     * @param  bool  $throw  Whether to throw after all retries fail.
     */
    public function __construct(
        public array|int $times,
        public Closure|int $sleepMilliseconds = 0,
        public mixed $when = null,
        public bool $throw = true,
    ) {}

    /**
     * Create a config that disables retry.
     */
    public static function none(): self
    {
        return new self(0);
    }

    /**
     * Create a config with exponential backoff.
     *
     * @param  int  $times  Number of retry attempts.
     * @param  int  $baseDelayMs  Base delay in milliseconds (default 100ms).
     */
    public static function exponential(int $times, int $baseDelayMs = 100): self
    {
        return new self(
            $times,
            fn (int $attempt): int => (int) ($baseDelayMs * (2 ** ($attempt - 1))),
        );
    }

    /**
     * Create a config with fixed delay between retries.
     *
     * @param  int  $times  Number of retry attempts.
     * @param  int  $delayMs  Fixed delay in milliseconds.
     */
    public static function fixed(int $times, int $delayMs): self
    {
        return new self($times, $delayMs);
    }

    /**
     * Convert the config to an array for passing to Prism.
     *
     * @return array{0: array<int, int>|int, 1: Closure|int, 2: callable|null, 3: bool}
     */
    public function toArray(): array
    {
        return [$this->times, $this->sleepMilliseconds, $this->when, $this->throw];
    }

    /**
     * Check if retry is enabled.
     */
    public function isEnabled(): bool
    {
        if (is_array($this->times)) {
            return $this->times !== [];
        }

        return $this->times > 0;
    }
}
