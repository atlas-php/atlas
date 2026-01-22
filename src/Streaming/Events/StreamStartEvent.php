<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Streaming\Events;

use Atlasphp\Atlas\Streaming\StreamEvent;

/**
 * Event emitted when a stream begins.
 *
 * Contains metadata about the stream including the provider and model being used.
 */
final readonly class StreamStartEvent extends StreamEvent
{
    /**
     * @param  string  $id  Unique identifier for this event.
     * @param  int  $timestamp  Unix timestamp when the event was created.
     * @param  string  $model  The model being used.
     * @param  string  $provider  The provider being used.
     */
    public function __construct(
        string $id,
        int $timestamp,
        public string $model,
        public string $provider,
    ) {
        parent::__construct($id, $timestamp);
    }

    public function type(): string
    {
        return 'stream.start';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type(),
            'timestamp' => $this->timestamp,
            'model' => $this->model,
            'provider' => $this->provider,
        ];
    }
}
