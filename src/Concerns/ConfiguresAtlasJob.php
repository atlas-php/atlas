<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Concerns;

/**
 * Shared queue configuration for Atlas job classes.
 *
 * Reads tries, backoff, and timeout from atlas.queue config with
 * minimum-value guards to prevent misconfigured values from causing
 * silent failures (e.g. tries=0 means "never run").
 *
 * @property int $tries
 * @property int $backoff
 * @property int $timeout
 */
trait ConfiguresAtlasJob
{
    /**
     * Apply queue configuration from atlas config.
     */
    protected function applyQueueConfig(): void
    {
        $this->tries = max(1, (int) config('atlas.queue.tries', 3));
        $this->backoff = max(0, (int) config('atlas.queue.backoff', 30));
        $this->timeout = max(1, (int) config('atlas.queue.timeout', 300));
    }
}
