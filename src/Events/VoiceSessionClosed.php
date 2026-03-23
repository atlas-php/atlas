<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

/**
 * Dispatched when a voice session is closed.
 */
class VoiceSessionClosed
{
    public function __construct(
        public readonly string $provider,
        public readonly string $sessionId,
        public readonly ?int $durationMs = null,
    ) {}
}
