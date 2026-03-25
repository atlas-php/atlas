<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Events\Concerns\BroadcastsOnOptionalChannel;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Dispatched before each step in the agent executor's tool call loop.
 */
class AgentStepStarted implements ShouldBroadcastNow
{
    use BroadcastsOnOptionalChannel;

    public function __construct(
        public readonly int $stepNumber,
        public readonly ?string $agentKey = null,
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly ?string $traceId = null,
        protected readonly ?Channel $channel = null,
    ) {}

    public function broadcastAs(): string
    {
        return 'AgentStepStarted';
    }
}
