<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Enums\Modality;

/**
 * Dispatched when an audio generation request completes.
 */
class AudioCompleted
{
    public function __construct(
        public readonly Modality $modality,
        public readonly string $provider,
        public readonly string $model,
    ) {}
}
