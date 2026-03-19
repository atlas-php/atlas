<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Middleware;

use Atlasphp\Atlas\Executor\Step;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\Usage;

/**
 * Context for step-layer middleware.
 *
 * Wraps each round trip in the executor's tool call loop.
 */
class StepContext
{
    /**
     * @param  Usage  $accumulatedUsage  Token usage from all completed prior steps (does not include the current step).
     * @param  array<int, Step>  $previousSteps
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly int $stepNumber,
        public TextRequest $request,
        public readonly Usage $accumulatedUsage,
        public readonly array $previousSteps = [],
        public array $meta = [],
    ) {}
}
