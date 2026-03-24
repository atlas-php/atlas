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
        ?string $provider = null,
        ?string $model = null,
        ?string $agentKey = null,
        ?string $traceId = null,
    ) {
        parent::__construct($executionId, $channel, $provider, $model, $agentKey, $traceId);
    }

    public function broadcastAs(): string
    {
        return 'ExecutionFailed';
    }
}
