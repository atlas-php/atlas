<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Broadcast event fired for each text chunk during streaming.
 */
class StreamChunkReceived implements ShouldBroadcastNow
{
    public function __construct(
        protected Channel $channel,
        public readonly string $text,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [$this->channel];
    }

    public function broadcastAs(): string
    {
        return 'StreamChunkReceived';
    }
}
