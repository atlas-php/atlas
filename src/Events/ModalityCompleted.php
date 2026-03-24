<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Responses\Usage;

/**
 * Dispatched when a modality request completes (success or failure).
 *
 * Always fires after ModalityStarted — even if the request throws.
 * On failure, usage will be null. Consumers can filter by modality.
 */
class ModalityCompleted
{
    public function __construct(
        public readonly Modality $modality,
        public readonly string $provider,
        public readonly string $model,
        public readonly ?Usage $usage = null,
        public readonly ?string $agentKey = null,
        public readonly ?string $traceId = null,
    ) {}
}
