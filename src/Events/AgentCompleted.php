<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Executor\Step;

/**
 * Dispatched when the agent executor finishes all steps.
 */
class AgentCompleted
{
    /**
     * @param  array<int, Step>  $steps
     */
    public function __construct(
        public readonly array $steps,
    ) {}
}
