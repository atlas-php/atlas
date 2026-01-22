<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Streaming;

/**
 * Base class for all streaming events.
 *
 * Provides common properties and methods for stream events,
 * enabling typed event handling in stream consumers.
 */
abstract readonly class StreamEvent
{
    /**
     * @param  string  $id  Unique identifier for this event.
     * @param  int  $timestamp  Unix timestamp when the event was created.
     */
    public function __construct(
        public string $id,
        public int $timestamp,
    ) {}

    /**
     * Get the event type identifier.
     */
    abstract public function type(): string;

    /**
     * Convert the event to an array representation.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    /**
     * Convert the event to JSON for SSE transmission.
     */
    public function toJson(): string
    {
        return (string) json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Format the event as an SSE message.
     */
    public function toSse(): string
    {
        return sprintf("event: %s\ndata: %s\n\n", $this->type(), $this->toJson());
    }
}
