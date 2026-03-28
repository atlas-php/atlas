<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

/**
 * Fired when a queued Atlas execution completes successfully.
 */
class ExecutionCompleted extends ExecutionEvent
{
    public function broadcastAs(): string
    {
        return 'ExecutionCompleted';
    }
}
