<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

/**
 * Dispatched when the agent executor begins execution.
 */
class AgentStarted
{
    public function __construct(
        public readonly ?string $agentKey,
        public readonly ?int $maxSteps,
        public readonly bool $concurrent,
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly ?string $traceId = null,
    ) {}
}
