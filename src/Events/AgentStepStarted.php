<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

/**
 * Dispatched before each step in the agent executor's tool call loop.
 */
class AgentStepStarted
{
    public function __construct(
        public readonly int $stepNumber,
        public readonly ?string $agentKey = null,
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly ?string $traceId = null,
    ) {}
}
