<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Illuminate\Broadcasting\Channel;

/**
 * Fired when a queued Atlas execution fails after all retries.
 */
class ExecutionFailed extends ExecutionEvent
{
    public function __construct(
        ?int $executionId,
        public readonly string $error,
        ?Channel $channel = null,
    ) {
        parent::__construct($executionId, $channel);
    }

    public function broadcastAs(): string
    {
        return 'ExecutionFailed';
    }
}
