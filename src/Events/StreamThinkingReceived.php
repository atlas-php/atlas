<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Broadcast event fired for thinking/reasoning chunks during streaming.
 */
class StreamThinkingReceived implements ShouldBroadcastNow
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
        return 'StreamThinkingReceived';
    }
}
