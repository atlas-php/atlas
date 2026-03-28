<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Events\Concerns\BroadcastsOnChannel;
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
    use BroadcastsOnChannel;

    public function __construct(
        public readonly string $sessionId,
        public readonly string $text,
        public readonly string $role,
        protected readonly Channel $channel,
    ) {}

    public function broadcastAs(): string
    {
        return 'voice.transcript.delta';
    }
}
