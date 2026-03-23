<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Enums\RealtimeTransport;

/**
 * Dispatched when a realtime session is successfully created.
 */
class RealtimeSessionCreated
{
    public function __construct(
        public readonly string $provider,
        public readonly string $model,
        public readonly string $sessionId,
        public readonly RealtimeTransport $transport,
    ) {}
}
