<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Enums;

/**
 * Message role identifiers for conversation history.
 */
enum Role: string
{
    case System = 'system';
    case User = 'user';
    case Assistant = 'assistant';
    case Tool = 'tool';
}
