<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Enums;

/**
 * Reasons a model may stop generating output.
 */
enum FinishReason: string
{
    case Stop = 'stop';
    case Length = 'length';
    case ToolCalls = 'tool_calls';
    case ContentFilter = 'content_filter';
}
