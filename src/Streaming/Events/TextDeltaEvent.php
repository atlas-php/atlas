<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Streaming\Events;

use Atlasphp\Atlas\Streaming\StreamEvent;

/**
 * Event emitted when a text chunk is received from the stream.
 *
 * The primary event for streaming text responses, containing the delta text
 * that should be appended to the accumulated response.
 */
final readonly class TextDeltaEvent extends StreamEvent
{
    /**
     * @param  string  $id  Unique identifier for this event.
     * @param  int  $timestamp  Unix timestamp when the event was created.
     * @param  string  $text  The text delta to append.
     */
    public function __construct(
        string $id,
        int $timestamp,
        public string $text,
    ) {
        parent::__construct($id, $timestamp);
    }

    public function type(): string
    {
        return 'text.delta';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type(),
            'timestamp' => $this->timestamp,
            'text' => $this->text,
        ];
    }
}
