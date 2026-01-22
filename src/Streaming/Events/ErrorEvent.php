<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Streaming\Events;

use Atlasphp\Atlas\Streaming\StreamEvent;

/**
 * Event emitted when an error occurs during streaming.
 *
 * Contains error details and whether the error is recoverable.
 */
final readonly class ErrorEvent extends StreamEvent
{
    /**
     * @param  string  $id  Unique identifier for this event.
     * @param  int  $timestamp  Unix timestamp when the event was created.
     * @param  string  $errorType  The type of error that occurred.
     * @param  string  $message  Human-readable error message.
     * @param  bool  $recoverable  Whether the stream can continue after this error.
     */
    public function __construct(
        string $id,
        int $timestamp,
        public string $errorType,
        public string $message,
        public bool $recoverable = false,
    ) {
        parent::__construct($id, $timestamp);
    }

    public function type(): string
    {
        return 'error';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type(),
            'timestamp' => $this->timestamp,
            'error_type' => $this->errorType,
            'message' => $this->message,
            'recoverable' => $this->recoverable,
        ];
    }
}
