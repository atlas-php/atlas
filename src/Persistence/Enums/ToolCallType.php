<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Enums;

/**
 * Classification of tool call origin.
 */
enum ToolCallType: string
{
    case Local = 'local';
    case Mcp = 'mcp';
    case Provider = 'provider';
}
