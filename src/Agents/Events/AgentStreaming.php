<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Events;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Support\AgentContext;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when an agent begins streaming.
 *
 * Fired after pipelines have processed, before the first stream event is yielded.
 */
class AgentStreaming
{
    use Dispatchable;

    public function __construct(
        public readonly AgentContract $agent,
        public readonly string $input,
        public readonly AgentContext $context,
    ) {}
}
