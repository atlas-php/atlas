<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Streaming\Events;

use Atlasphp\Atlas\Streaming\StreamEvent;

/**
 * Event emitted when a stream completes.
 *
 * Contains final metadata about the stream including usage statistics
 * and finish reason.
 */
final readonly class StreamEndEvent extends StreamEvent
{
    /**
     * @param  string  $id  Unique identifier for this event.
     * @param  int  $timestamp  Unix timestamp when the event was created.
     * @param  string|null  $finishReason  The reason the stream ended.
     * @param  array<string, int>  $usage  Token usage statistics.
     */
    public function __construct(
        string $id,
        int $timestamp,
        public ?string $finishReason = null,
        public array $usage = [],
    ) {
        parent::__construct($id, $timestamp);
    }

    public function type(): string
    {
        return 'stream.end';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type(),
            'timestamp' => $this->timestamp,
            'finish_reason' => $this->finishReason,
            'usage' => $this->usage,
        ];
    }

    /**
     * Get total tokens used.
     */
    public function totalTokens(): int
    {
        return (int) ($this->usage['total_tokens'] ?? 0);
    }

    /**
     * Get prompt tokens used.
     */
    public function promptTokens(): int
    {
        return (int) ($this->usage['prompt_tokens'] ?? 0);
    }

    /**
     * Get completion tokens used.
     */
    public function completionTokens(): int
    {
        return (int) ($this->usage['completion_tokens'] ?? 0);
    }
}
