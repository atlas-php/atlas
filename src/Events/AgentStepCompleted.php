<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Responses\Usage;

/**
 * Dispatched after each step in the agent executor's tool call loop completes.
 */
class AgentStepCompleted
{
    public function __construct(
        public readonly int $stepNumber,
        public readonly FinishReason $finishReason,
        public readonly Usage $usage,
    ) {}
}
