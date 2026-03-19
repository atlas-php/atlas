<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Enums;

/**
 * Types of chunks emitted during streaming responses.
 */
enum ChunkType: string
{
    case Text = 'text';
    case Thinking = 'thinking';
    case ToolCall = 'tool_call';
    case Done = 'done';
}
