<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Middleware;

use Atlasphp\Atlas\Middleware\StepContext;
use Atlasphp\Atlas\Persistence\Services\ExecutionService;
use Atlasphp\Atlas\Responses\TextResponse;
use Closure;

/**
 * Class TrackStep
 *
 * Step-layer middleware that wraps each round trip in the executor's tool call
 * loop. Creates a step record before the provider call, records the response
 * data (text, reasoning, token usage, finish reason) after, and completes the step.
 */
class TrackStep
{
    public function __construct(
        protected readonly ExecutionService $tracker,
    ) {}

    public function handle(StepContext $context, Closure $next): TextResponse
    {
        // Only track if an execution is active
        if ($this->tracker->getExecution() === null) {
            return $next($context);
        }

        // ── Create step in pending, begin processing ─────────────
        $this->tracker->createStep($context->meta);
        $this->tracker->beginStep();

        try {
            // ── Provider call happens inside ──────────────────────────
            $response = $next($context);

            // ── Record response data from the TextResponse ───────────
            $finishReason = $response->finishReason->value;

            $this->tracker->currentStep()?->recordResponse(
                content: $response->text,
                reasoning: $response->reasoning,
                inputTokens: $response->usage->inputTokens,
                outputTokens: $response->usage->outputTokens,
                finishReason: $finishReason,
            );

            // ── Complete the step ────────────────────────────────────
            $this->tracker->completeStep();

            return $response;

        } catch (\Throwable $e) {
            // failExecution() will mark the in-flight step as failed
            throw $e;
        }
    }
}
