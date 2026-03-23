<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * Broadcastable event for realtime audio chunks.
 *
 * Carries base64-encoded audio data to connected clients.
 */
class RealtimeAudioDelta implements ShouldBroadcast
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $audioData,
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
        return 'realtime.audio.delta';
    }
}
