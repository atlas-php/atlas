<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

/**
 * Dispatched when a voice tool call completes successfully.
 *
 * Complements VoiceToolCallStarted to form a complete lifecycle pair,
 * allowing consumers to track tool execution duration and results.
 */
class VoiceToolCallCompleted
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $callId,
        public readonly string $name,
        public readonly string $result,
        public readonly int $durationMs,
    ) {}
}
