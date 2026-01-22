<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Streaming\Events;

use Atlasphp\Atlas\Streaming\StreamEvent;

/**
 * Event emitted when a tool call begins.
 *
 * Contains information about the tool being called and its arguments.
 */
final readonly class ToolCallStartEvent extends StreamEvent
{
    /**
     * @param  string  $id  Unique identifier for this event.
     * @param  int  $timestamp  Unix timestamp when the event was created.
     * @param  string  $toolId  The unique identifier for this tool call.
     * @param  string  $toolName  The name of the tool being called.
     * @param  array<string, mixed>  $arguments  The arguments passed to the tool.
     */
    public function __construct(
        string $id,
        int $timestamp,
        public string $toolId,
        public string $toolName,
        public array $arguments = [],
    ) {
        parent::__construct($id, $timestamp);
    }

    public function type(): string
    {
        return 'tool.call.start';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type(),
            'timestamp' => $this->timestamp,
            'tool_id' => $this->toolId,
            'tool_name' => $this->toolName,
            'arguments' => $this->arguments,
        ];
    }
}
