<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Executor;

use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Messages\ToolResultMessage;

/**
 * The result of executing a single tool call.
 *
 * Wraps the original tool call with the serialized output content
 * and an error flag for failed executions.
 */
class ToolResult
{
    /**
     * @param  class-string<\Throwable>|null  $exceptionClass  Original exception class for error results (serialization-safe across fork boundaries).
     */
    public function __construct(
        public readonly ToolCall $toolCall,
        public readonly string $content,
        public readonly bool $isError = false,
        public readonly ?string $exceptionClass = null,
    ) {}

    /**
     * Convert to a ToolResultMessage for appending to the conversation.
     */
    public function toMessage(): ToolResultMessage
    {
        return new ToolResultMessage(
            toolCallId: $this->toolCall->id,
            content: $this->content,
            toolName: $this->toolCall->name,
            isError: $this->isError,
        );
    }
}
