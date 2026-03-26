<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

/**
 * Dispatched when a voice call session is created and ready for connection.
 */
class VoiceCallStarted
{
    public function __construct(
        public readonly int $voiceCallId,
        public readonly ?int $conversationId,
        public readonly string $sessionId,
        public readonly string $provider,
        public readonly ?string $agentKey,
    ) {}
}
