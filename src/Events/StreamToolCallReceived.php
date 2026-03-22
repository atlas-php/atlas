<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Broadcast event fired for tool call chunks during streaming.
 */
class StreamToolCallReceived implements ShouldBroadcastNow
{
    /**
     * @param  array<int, array<string, mixed>>  $toolCalls
     */
    public function __construct(
        protected Channel $channel,
        public readonly array $toolCalls,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [$this->channel];
    }

    public function broadcastAs(): string
    {
        return 'StreamToolCallReceived';
    }
}
