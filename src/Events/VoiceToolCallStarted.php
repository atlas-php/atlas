<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

/**
 * Dispatched when the AI requests a tool call during a voice session.
 */
class VoiceToolCallStarted
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $callId,
        public readonly string $name,
        public readonly string $arguments,
    ) {}
}
