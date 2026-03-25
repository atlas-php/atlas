<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Events\Concerns\BroadcastsOnChannel;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Broadcast when a stream response begins iteration.
 */
class StreamStarted implements ShouldBroadcastNow
{
    use BroadcastsOnChannel;

    public function __construct(
        protected readonly Channel $channel,
    ) {}

    public function broadcastAs(): string
    {
        return 'StreamStarted';
    }
}
