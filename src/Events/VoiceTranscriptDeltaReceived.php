<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Broadcastable event for voice transcript chunks.
 *
 * Carries partial transcript text for user or assistant speech.
 * This event is dispatched by the consumer's WebSocket relay layer
 * (not by Atlas internally) when transcript data arrives from the provider.
 */
class VoiceTranscriptDeltaReceived implements ShouldBroadcastNow
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
        return 'voice.transcript.delta';
    }
}
