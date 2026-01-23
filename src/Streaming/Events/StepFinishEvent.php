<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Streaming\Events;

use Atlasphp\Atlas\Streaming\StreamEvent;

/**
 * Event emitted when an agent step completes.
 *
 * Indicates the end of a processing step during agent execution.
 */
final readonly class StepFinishEvent extends StreamEvent
{
    public function type(): string
    {
        return 'step.finish';
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
