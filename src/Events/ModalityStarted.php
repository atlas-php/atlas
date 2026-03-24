<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Enums\Modality;

/**
 * Dispatched when a modality request begins execution.
 *
 * Consumers can filter by the modality enum to handle specific
 * request types (Text, Image, Audio, Video, Embed, etc.).
 */
class ModalityStarted
{
    public function __construct(
        public readonly Modality $modality,
        public readonly string $provider,
        public readonly string $model,
        public readonly ?string $agentKey = null,
        public readonly ?string $traceId = null,
    ) {}
}
