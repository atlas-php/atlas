<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Fired when a queued Atlas execution transitions from queued to processing.
 */
class ExecutionProcessing implements ShouldBroadcastNow
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
        return 'ExecutionProcessing';
    }

    public function broadcastWhen(): bool
    {
        return $this->channel !== null;
    }
}
