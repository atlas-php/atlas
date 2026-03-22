<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Messages\ToolCall;

/**
 * Dispatched when a tool throws an exception during an agent loop.
 */
class AgentToolCallFailed
{
    public function __construct(
        public readonly ToolCall $toolCall,
        public readonly \Throwable $exception,
    ) {}
}
