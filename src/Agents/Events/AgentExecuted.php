<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Events;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Support\AgentContext;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after an agent completes execution successfully.
 *
 * Fired after pipelines have processed. Contains the full response.
 */
class AgentExecuted
{
    use Dispatchable;

    public function __construct(
        public readonly AgentContract $agent,
        public readonly string $input,
        public readonly AgentContext $context,
        public readonly AgentResponse $response,
    ) {}
}
