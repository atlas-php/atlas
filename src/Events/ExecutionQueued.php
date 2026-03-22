<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Fired when an Atlas execution is dispatched to the queue.
 */
class ExecutionQueued implements ShouldBroadcastNow
{
    public function __construct(
        public readonly ?int $executionId,
        protected readonly ?Channel $channel = null,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return $this->channel !== null ? [$this->channel] : [];
    }

    public function broadcastAs(): string
    {
        return 'ExecutionQueued';
    }

    public function broadcastWhen(): bool
    {
        return $this->channel !== null;
    }
}
