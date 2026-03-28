<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Enums\VoiceTransport;

/**
 * Dispatched when a voice session is successfully created.
 */
class VoiceSessionCreated
{
    public function __construct(
        public readonly string $provider,
        public readonly string $model,
        public readonly string $sessionId,
        public readonly VoiceTransport $transport,
    ) {}
}
