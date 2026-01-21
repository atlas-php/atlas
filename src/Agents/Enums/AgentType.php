<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Enums;

/**
 * Agent execution types.
 *
 * Defines how an agent is executed:
 * - Api: Standard API-based execution (default)
 * - Cli: Command-line interface execution (reserved for future use)
 */
enum AgentType: string
{
    case Api = 'api';
    case Cli = 'cli';
}
