<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Streaming\Events;

use Atlasphp\Atlas\Streaming\StreamEvent;

/**
 * Event emitted when a thinking/reasoning chunk is received.
 *
 * Contains delta text from the model's internal reasoning process,
 * used with models that support extended thinking.
 */
final readonly class ThinkingDeltaEvent extends StreamEvent
{
    /**
     * @param  string  $id  Unique identifier for this event.
     * @param  int  $timestamp  Unix timestamp when the event was created.
     * @param  string  $delta  The thinking/reasoning text chunk.
     * @param  string  $reasoningId  Unique identifier for this reasoning session.
     * @param  array<string, mixed>|null  $summary  Optional reasoning summary.
     */
    public function __construct(
        string $id,
        int $timestamp,
        public string $delta,
        public string $reasoningId,
        public ?array $summary = null,
    ) {
        parent::__construct($id, $timestamp);
    }

    public function type(): string
    {
        return 'thinking.delta';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type(),
            'timestamp' => $this->timestamp,
            'delta' => $this->delta,
            'reasoning_id' => $this->reasoningId,
            'summary' => $this->summary,
        ];
    }
}
