<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

/**
 * Fired when a queued Atlas execution transitions from queued to processing.
 */
class ExecutionProcessing extends ExecutionEvent
{
    public function broadcastAs(): string
    {
        return 'ExecutionProcessing';
    }
}
