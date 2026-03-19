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
     * @param  array<string, mixed>  $meta  From the executor's $meta parameter. Separate from ProviderContext::$meta (which comes from $request->meta). In agent loops, set meta on both the TextRequest and the execute() call if both layers need it.
     */
    public function __construct(
        public readonly int $stepNumber,
        public TextRequest $request,
        public readonly Usage $accumulatedUsage,
        public readonly array $previousSteps = [],
        public array $meta = [],
    ) {}
}
