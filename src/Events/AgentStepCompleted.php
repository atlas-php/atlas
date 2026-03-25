<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Events\Concerns\BroadcastsOnOptionalChannel;
use Atlasphp\Atlas\Responses\Usage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Dispatched after each step in the agent executor's tool call loop completes.
 */
class AgentStepCompleted implements ShouldBroadcastNow
{
    use BroadcastsOnOptionalChannel;

    public function __construct(
        public readonly int $stepNumber,
        public readonly FinishReason $finishReason,
        public readonly Usage $usage,
        public readonly ?string $agentKey = null,
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly ?string $traceId = null,
        protected readonly ?Channel $channel = null,
    ) {}

    public function broadcastAs(): string
    {
        return 'AgentStepCompleted';
    }
}
