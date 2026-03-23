<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

/**
 * Dispatched when a realtime session is closed.
 */
class RealtimeSessionClosed
{
    public function __construct(
        public readonly string $provider,
        public readonly string $sessionId,
        public readonly ?int $durationMs = null,
    ) {}
}
