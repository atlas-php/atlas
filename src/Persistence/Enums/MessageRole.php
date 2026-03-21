<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Enums;

/**
 * Message role in the conversation — maps to the wire format role.
 */
enum MessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
    case System = 'system';
}
