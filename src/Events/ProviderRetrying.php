<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

/**
 * Dispatched when an HTTP call to a provider is about to be retried.
 *
 * Listeners can use this for logging, metrics, or circuit-breaker logic.
 */
class ProviderRetrying
{
    public function __construct(
        public readonly string $url,
        public readonly \Throwable $exception,
        public readonly int $attempt,
        public readonly int $waitMicroseconds,
    ) {}
}
