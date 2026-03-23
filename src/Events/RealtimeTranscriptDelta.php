<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * Broadcastable event for realtime transcript chunks.
 *
 * Carries partial transcript text for user or assistant speech.
 */
class RealtimeTranscriptDelta implements ShouldBroadcast
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $text,
        public readonly string $role,
        public readonly string $channelName,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel($this->channelName)];
    }

    public function broadcastAs(): string
    {
        return 'realtime.transcript.delta';
    }
}
