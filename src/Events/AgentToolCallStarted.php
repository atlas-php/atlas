<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Events\Concerns\BroadcastsOnOptionalChannel;
use Atlasphp\Atlas\Events\Concerns\CapsBroadcastPayload;
use Atlasphp\Atlas\Messages\ToolCall;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Dispatched before a tool is executed during an agent loop.
 */
class AgentToolCallStarted implements ShouldBroadcastNow
{
    use BroadcastsOnOptionalChannel;
    use CapsBroadcastPayload;

    public function __construct(
        public readonly ToolCall $toolCall,
        public readonly ?string $agentKey = null,
        public readonly ?int $stepNumber = null,
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly ?string $traceId = null,
        protected readonly ?Channel $channel = null,
    ) {}

    public function broadcastAs(): string
    {
        return 'AgentToolCallStarted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'agentKey' => $this->agentKey,
            'toolCallId' => $this->toolCall->id,
            'toolName' => $this->toolCall->name,
            'arguments' => $this->capArguments($this->toolCall->arguments),
            'stepNumber' => $this->stepNumber,
        ];
    }

    /**
     * Cap each argument value to the configured broadcast limit, so large
     * arguments — including a big nested structure — can't overflow the socket
     * transport.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function capArguments(array $arguments): array
    {
        $capped = [];

        foreach ($arguments as $key => $value) {
            $capped[$key] = $this->capBroadcastValue($value);
        }

        return $capped;
    }
}
