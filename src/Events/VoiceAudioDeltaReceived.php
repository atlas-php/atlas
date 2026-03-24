<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Broadcastable event for voice audio chunks.
 *
 * Carries base64-encoded audio data to connected clients.
 * This event is dispatched by the consumer's WebSocket relay layer
 * (not by Atlas internally) when audio data arrives from the provider.
 */
class VoiceAudioDeltaReceived implements ShouldBroadcastNow
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
        return 'voice.audio.delta';
    }
}
