<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Executor\ToolResult;
use Atlasphp\Atlas\Messages\ToolCall;

/**
 * Dispatched after a tool is successfully executed during an agent loop.
 */
class AgentToolCalled
{
    public function __construct(
        public readonly ToolCall $toolCall,
        public readonly ToolResult $result,
    ) {}
}
