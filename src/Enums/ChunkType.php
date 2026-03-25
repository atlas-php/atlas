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

    // Orchestration markers — emitted during tool-loop replay
    case StepStarted = 'step_started';
    case StepCompleted = 'step_completed';
    case ToolCallStarted = 'tool_call_started';
    case ToolCallCompleted = 'tool_call_completed';
    case ToolCallFailed = 'tool_call_failed';
}
