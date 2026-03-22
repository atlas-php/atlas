<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

/**
 * Fired when an Atlas execution is dispatched to the queue.
 */
class ExecutionQueued extends ExecutionEvent
{
    public function broadcastAs(): string
    {
        return 'ExecutionQueued';
    }
}
