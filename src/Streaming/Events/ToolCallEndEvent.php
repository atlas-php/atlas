<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Streaming\Events;

use Atlasphp\Atlas\Streaming\StreamEvent;

/**
 * Event emitted when a tool call completes.
 *
 * Contains the result of the tool execution.
 */
final readonly class ToolCallEndEvent extends StreamEvent
{
    /**
     * @param  string  $id  Unique identifier for this event.
     * @param  int  $timestamp  Unix timestamp when the event was created.
     * @param  string  $toolId  The unique identifier for this tool call.
     * @param  string  $toolName  The name of the tool that was called.
     * @param  string|null  $result  The result of the tool execution.
     * @param  bool  $success  Whether the tool call succeeded.
     */
    public function __construct(
        string $id,
        int $timestamp,
        public string $toolId,
        public string $toolName,
        public ?string $result = null,
        public bool $success = true,
    ) {
        parent::__construct($id, $timestamp);
    }

    public function type(): string
    {
        return 'tool.call.end';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type(),
            'timestamp' => $this->timestamp,
            'tool_id' => $this->toolId,
            'tool_name' => $this->toolName,
            'result' => $this->result,
            'success' => $this->success,
        ];
    }
}
