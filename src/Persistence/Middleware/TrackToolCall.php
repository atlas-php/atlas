<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Middleware;

use Atlasphp\Atlas\Executor\ToolResult;
use Atlasphp\Atlas\Middleware\ToolContext;
use Atlasphp\Atlas\Persistence\Services\ExecutionService;
use Closure;

/**
 * Class TrackToolCall
 *
 * Tool-layer middleware that wraps each individual tool execution. Creates a
 * tool call record before the tool runs, records the result or error after,
 * and tracks wall-clock duration for each call.
 */
class TrackToolCall
{
    public function __construct(
        protected readonly ExecutionService $executionService,
    ) {}

    public function handle(ToolContext $context, Closure $next): ToolResult
    {
        // Only track if an execution is active with a current step
        if ($this->executionService->getExecution() === null || $this->executionService->currentStep() === null) {
            return $next($context);
        }

        // ── Create tool call in pending, begin processing ────────
        $record = $this->executionService->createToolCall($context->toolCall, meta: $context->meta);
        $startTime = $this->executionService->beginToolCall($record);

        try {
            // ── Tool executes ────────────────────────────────────────
            $result = $next($context);

            // ── Record result ────────────────────────────────────────
            if ($result->isError) {
                $this->executionService->failToolCall($record, $startTime, $result->content);
            } else {
                $this->executionService->completeToolCall($record, $startTime, $result->content);
            }

            return $result;

        } catch (\Throwable $e) {
            $this->executionService->failToolCall($record, $startTime, $e->getMessage());
            throw $e;
        }
    }
}
