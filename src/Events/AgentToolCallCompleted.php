<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Executor\ToolResult;
use Atlasphp\Atlas\Messages\ToolCall;

/**
 * Dispatched after a tool is successfully executed during an agent loop.
 */
class AgentToolCallCompleted
{
    public function __construct(
        public readonly ToolCall $toolCall,
        public readonly ToolResult $result,
        public readonly ?string $agentKey = null,
        public readonly ?int $stepNumber = null,
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly ?string $traceId = null,
    ) {}
}
