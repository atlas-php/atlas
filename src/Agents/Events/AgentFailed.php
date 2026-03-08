<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Events;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Support\AgentContext;
use Illuminate\Foundation\Events\Dispatchable;
use Throwable;

/**
 * Dispatched when an agent execution fails.
 *
 * Fired after the error pipeline has processed.
 */
class AgentFailed
{
    use Dispatchable;

    public function __construct(
        public readonly AgentContract $agent,
        public readonly string $input,
        public readonly AgentContext $context,
        public readonly Throwable $exception,
    ) {}
}
