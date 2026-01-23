<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Streaming\Events;

use Atlasphp\Atlas\Streaming\StreamEvent;

/**
 * Event emitted when model thinking/reasoning begins.
 *
 * Indicates the start of a model's internal reasoning process,
 * used with models that support extended thinking.
 */
final readonly class ThinkingStartEvent extends StreamEvent
{
    /**
     * @param  string  $id  Unique identifier for this event.
     * @param  int  $timestamp  Unix timestamp when the event was created.
     * @param  string  $reasoningId  Unique identifier for this reasoning session.
     */
    public function __construct(
        string $id,
        int $timestamp,
        public string $reasoningId,
    ) {
        parent::__construct($id, $timestamp);
    }

    public function type(): string
    {
        return 'thinking.start';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type(),
            'timestamp' => $this->timestamp,
            'reasoning_id' => $this->reasoningId,
        ];
    }
}
