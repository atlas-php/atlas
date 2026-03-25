<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Events\Concerns\BroadcastsOnChannel;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Broadcast event fired for tool call chunks during streaming.
 */
class StreamToolCallReceived implements ShouldBroadcastNow
{
    use BroadcastsOnChannel;

    /**
     * @param  array<int, array<string, mixed>>  $toolCalls
     */
    public function __construct(
        protected Channel $channel,
        public readonly array $toolCalls,
    ) {}

    public function broadcastAs(): string
    {
        return 'StreamToolCallReceived';
    }
}
