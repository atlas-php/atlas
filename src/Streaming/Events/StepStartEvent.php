<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Streaming\Events;

use Atlasphp\Atlas\Streaming\StreamEvent;

/**
 * Event emitted when an agent step begins.
 *
 * Indicates the start of a new processing step during agent execution.
 */
final readonly class StepStartEvent extends StreamEvent
{
    public function type(): string
    {
        return 'step.start';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type(),
            'timestamp' => $this->timestamp,
        ];
    }
}
