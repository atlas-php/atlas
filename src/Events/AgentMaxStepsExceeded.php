<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Events\Concerns\BroadcastsOnOptionalChannel;
use Atlasphp\Atlas\Executor\Step;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Dispatched when the agent executor exceeds the configured step limit.
 */
class AgentMaxStepsExceeded implements ShouldBroadcastNow
{
    use BroadcastsOnOptionalChannel;

    /**
     * @param  array<int, Step>  $steps
     */
    public function __construct(
        public readonly int $limit,
        public readonly array $steps,
        public readonly ?string $agentKey = null,
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly ?string $traceId = null,
        protected readonly ?Channel $channel = null,
    ) {}

    public function broadcastAs(): string
    {
        return 'AgentMaxStepsExceeded';
    }
}
