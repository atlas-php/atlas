<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Enums;

/**
 * Enum representing tool usage modes for agent execution.
 *
 * Controls when and how tools are used during agent execution.
 */
enum ToolChoice: string
{
    /**
     * Model decides whether to use tools (default behavior).
     */
    case Auto = 'auto';

    /**
     * Model must use at least one tool.
     */
    case Any = 'any';

    /**
     * Model cannot use any tools.
     */
    case None = 'none';
}
