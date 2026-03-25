<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Events\Concerns\BroadcastsOnOptionalChannel;
use Atlasphp\Atlas\Executor\Step;
use Atlasphp\Atlas\Responses\Usage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Dispatched when the agent executor finishes all steps.
 */
class AgentCompleted implements ShouldBroadcastNow
{
    use BroadcastsOnOptionalChannel;

    /**
     * @param  array<int, Step>  $steps
     */
    public function __construct(
        public readonly array $steps,
        public readonly Usage $usage,
        public readonly ?string $agentKey = null,
        public readonly ?FinishReason $finishReason = null,
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly ?string $traceId = null,
        protected readonly ?Channel $channel = null,
    ) {}

    public function broadcastAs(): string
    {
        return 'AgentCompleted';
    }
}
