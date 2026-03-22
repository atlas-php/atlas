<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Executor\Step;

/**
 * Dispatched when the agent executor exceeds the configured step limit.
 */
class AgentMaxStepsExceeded
{
    /**
     * @param  array<int, Step>  $steps
     */
    public function __construct(
        public readonly int $limit,
        public readonly array $steps,
    ) {}
}
