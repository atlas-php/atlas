<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

/**
 * Dispatched when a voice tool call fails with an error.
 *
 * Complements VoiceToolCallStarted to form a complete lifecycle pair,
 * allowing consumers to track tool execution failures and duration.
 */
class VoiceToolCallFailed
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $callId,
        public readonly string $name,
        public readonly string $error,
        public readonly int $durationMs,
    ) {}
}
