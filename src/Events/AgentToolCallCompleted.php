<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Events\Concerns\BroadcastsOnOptionalChannel;
use Atlasphp\Atlas\Executor\ToolResult;
use Atlasphp\Atlas\Messages\ToolCall;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Dispatched after a tool is successfully executed during an agent loop.
 */
class AgentToolCallCompleted implements ShouldBroadcastNow
{
    use BroadcastsOnOptionalChannel;

    public function __construct(
        public readonly ToolCall $toolCall,
        public readonly ToolResult $result,
        public readonly ?string $agentKey = null,
        public readonly ?int $stepNumber = null,
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly ?string $traceId = null,
        protected readonly ?Channel $channel = null,
    ) {}

    public function broadcastAs(): string
    {
        return 'AgentToolCallCompleted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'toolCallId' => $this->toolCall->id,
            'toolName' => $this->toolCall->name,
            'result' => mb_substr($this->result->content, 0, 500),
            'isError' => $this->result->isError,
            'stepNumber' => $this->stepNumber,
        ];
    }
}
