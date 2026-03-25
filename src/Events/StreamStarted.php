<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Broadcast when a stream response begins iteration.
 *
 * Channel is nullable because StreamStarted fires even for non-broadcast streams.
 * Cannot use BroadcastsOnChannel trait which requires a non-null channel.
 * Sibling events (StreamChunkReceived, etc.) require a channel and use the trait.
 */
class StreamStarted implements ShouldBroadcastNow
{
    public function __construct(
        protected readonly ?Channel $channel = null,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return $this->channel !== null ? [$this->channel] : [];
    }

    public function broadcastAs(): string
    {
        return 'StreamStarted';
    }

    public function broadcastWhen(): bool
    {
        return $this->channel !== null;
    }
}
