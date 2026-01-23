<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Streaming\Events;

use Atlasphp\Atlas\Streaming\StreamEvent;

/**
 * Event emitted when a citation is received from the model.
 *
 * Contains citation information linking response content to source materials.
 */
final readonly class CitationEvent extends StreamEvent
{
    /**
     * @param  string  $id  Unique identifier for this event.
     * @param  int  $timestamp  Unix timestamp when the event was created.
     * @param  array<string, mixed>  $citation  The citation data.
     * @param  string  $messageId  The message this citation belongs to.
     * @param  int|null  $blockIndex  Content block index for this citation.
     * @param  array<string, mixed>|null  $metadata  Additional citation metadata.
     */
    public function __construct(
        string $id,
        int $timestamp,
        public array $citation,
        public string $messageId,
        public ?int $blockIndex = null,
        public ?array $metadata = null,
    ) {
        parent::__construct($id, $timestamp);
    }

    public function type(): string
    {
        return 'citation';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type(),
            'timestamp' => $this->timestamp,
            'citation' => $this->citation,
            'message_id' => $this->messageId,
            'block_index' => $this->blockIndex,
            'metadata' => $this->metadata,
        ];
    }
}
