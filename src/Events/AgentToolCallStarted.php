<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Messages\ToolCall;

/**
 * Dispatched before a tool is executed during an agent loop.
 */
class AgentToolCallStarted
{
    public function __construct(
        public readonly ToolCall $toolCall,
        public readonly ?string $agentKey = null,
        public readonly ?int $stepNumber = null,
    ) {}
}
