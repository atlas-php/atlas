<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Events;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Support\AgentContext;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched before an agent begins execution.
 *
 * Fired after pipelines have processed (pipelines modify, events observe).
 * Use this event for logging, metrics, or audit trails.
 */
class AgentExecuting
{
    use Dispatchable;

    public function __construct(
        public readonly AgentContract $agent,
        public readonly string $input,
        public readonly AgentContext $context,
    ) {}
}
