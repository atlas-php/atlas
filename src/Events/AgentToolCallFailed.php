<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Events\Concerns\BroadcastsOnOptionalChannel;
use Atlasphp\Atlas\Messages\ToolCall;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Dispatched when a tool throws an exception during an agent loop.
 */
class AgentToolCallFailed implements ShouldBroadcastNow
{
    use BroadcastsOnOptionalChannel;

    public function __construct(
        public readonly ToolCall $toolCall,
        public readonly \Throwable $exception,
        public readonly ?string $agentKey = null,
        public readonly ?int $stepNumber = null,
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly ?string $traceId = null,
        protected readonly ?Channel $channel = null,
    ) {}

    public function broadcastAs(): string
    {
        return 'AgentToolCallFailed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'toolCallId' => $this->toolCall->id,
            'toolName' => $this->toolCall->name,
            'error' => $this->exception->getMessage(),
            'stepNumber' => $this->stepNumber,
        ];
    }
}
