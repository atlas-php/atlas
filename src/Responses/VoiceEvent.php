<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses;

/**
 * Value object representing a typed WebSocket event in a voice session.
 */
class VoiceEvent
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $type,
        public readonly ?string $eventId = null,
        public readonly array $data = [],
    ) {}
}
