<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Enums\FinishReason;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Broadcast event fired when a stream completes with accumulated text, usage, and finish reason.
 */
class StreamCompleted implements ShouldBroadcastNow
{
    /**
     * @param  array<string, int>|null  $usage
     */
    public function __construct(
        protected Channel $channel,
        public readonly string $text,
        public readonly ?array $usage = null,
        public readonly ?FinishReason $finishReason = null,
    ) {}

    public function broadcastOn(): Channel
    {
        return $this->channel;
    }

    public function broadcastAs(): string
    {
        return 'StreamCompleted';
    }
}
