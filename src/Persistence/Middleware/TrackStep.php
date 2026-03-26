<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Middleware;

use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Middleware\Contracts\StepMiddleware;
use Atlasphp\Atlas\Middleware\StepContext;
use Atlasphp\Atlas\Persistence\Enums\ToolCallType;
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
class TrackStep implements StepMiddleware
{
    public function __construct(
        protected readonly ExecutionService $executionService,
    ) {}

    public function handle(StepContext $context, Closure $next): TextResponse
    {
        // Only track if an execution is active
        if ($this->executionService->getExecution() === null) {
            return $next($context);
        }

        // ── Create step in pending, begin processing ─────────────
        $this->executionService->createStep($context->meta);
        $this->executionService->beginStep();

        try {
            // ── Provider call happens inside ──────────────────────────
            $response = $next($context);

            // ── Record response data from the TextResponse ───────────
            $finishReason = $response->finishReason->value;

            $this->executionService->currentStep()?->recordResponse(
                content: $response->text,
                reasoning: $response->reasoning,
                finishReason: $finishReason,
            );

            // ── Complete the step ────────────────────────────────────
            $this->executionService->completeStep();

            // ── Log provider tool calls (already executed server-side) ──
            $this->logProviderToolCalls($response);

            return $response;

        } catch (\Throwable $e) {
            // Mark the step failed directly so it doesn't stay stuck in
            // Processing if TrackExecution is not in the middleware stack.
            $this->executionService->currentStep()?->markFailed($e->getMessage(), null);
            throw $e;
        }
    }

    /**
     * Log provider-executed tool calls (web_search, code_interpreter, etc.)
     * as ExecutionToolCall records with type=Provider. Respects the provider's
     * reported status — failed provider tools are recorded as failed.
     */
    private function logProviderToolCalls(TextResponse $response): void
    {
        if ($response->providerToolCalls === [] || $this->executionService->currentStep() === null) {
            return;
        }

        foreach ($response->providerToolCalls as $providerTool) {
            try {
                $record = $this->executionService->createToolCall(
                    new ToolCall(
                        id: (string) ($providerTool['id'] ?? ''),
                        name: (string) ($providerTool['type'] ?? 'unknown'),
                        arguments: [],
                    ),
                    type: ToolCallType::Provider,
                );

                $startTime = $this->executionService->beginToolCall($record);
                $reportedStatus = (string) ($providerTool['status'] ?? 'completed');

                if ($reportedStatus === 'failed') {
                    $this->executionService->failToolCall($record, $startTime, $reportedStatus);
                } else {
                    $this->executionService->completeToolCall($record, $startTime, $reportedStatus);
                }
            } catch (\Throwable) {
                // Don't let provider tool logging failures break the response flow
                continue;
            }
        }
    }
}
