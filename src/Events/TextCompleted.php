<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Responses\Usage;

/**
 * Dispatched when a text generation request completes.
 */
class TextCompleted
{
    public function __construct(
        public readonly Modality $modality,
        public readonly string $provider,
        public readonly string $model,
        public readonly ?Usage $usage = null,
    ) {}
}
