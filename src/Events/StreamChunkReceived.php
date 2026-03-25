<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Events\Concerns\BroadcastsOnChannel;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Broadcast event fired for each text chunk during streaming.
 */
class StreamChunkReceived implements ShouldBroadcastNow
{
    use BroadcastsOnChannel;

    public function __construct(
        protected Channel $channel,
        public readonly string $text,
    ) {}

    public function broadcastAs(): string
    {
        return 'StreamChunkReceived';
    }
}
