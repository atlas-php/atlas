<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Middleware;

use Atlasphp\Atlas\Executor\ExecutorResult;
use Atlasphp\Atlas\Middleware\AgentContext;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Services\ExecutionService;
use Closure;

/**
 * Class TrackExecution
 *
 * Agent-layer middleware that wraps the entire execution to create and track
 * an execution record. Creates the execution in pending state before the agent
 * runs, transitions through processing, and finalizes as completed or failed.
 */
class TrackExecution
{
    public function __construct(
        protected readonly ExecutionService $tracker,
    ) {}

    /**
     * @param  Closure(AgentContext): ExecutorResult  $next
     */
    public function handle(AgentContext $context, Closure $next): ExecutorResult
    {
        $agent = $context->agent;

        // Resolve provider/model from agent (may be null → config defaults)
        $provider = $agent !== null
            ? (string) ($agent->provider() ?? config('atlas.defaults.text.provider', 'unknown'))
            : (string) config('atlas.defaults.text.provider', 'unknown');
        $model = $agent?->model() ?? (string) config('atlas.defaults.text.model', 'unknown');

        // ── Resolve execution type from meta (set by pending request) ─
        $type = ExecutionType::tryFrom($context->meta['_execution_type'] ?? '')
            ?? ExecutionType::Text;

        // ── Create execution in pending state ────────────────────
        $execution = $this->tracker->createExecution(
            provider: $provider,
            model: $model,
            meta: $context->meta,
            agent: $agent?->key(),
            conversationId: $context->meta['conversation_id'] ?? null,
            messageId: $context->meta['trigger_message_id'] ?? null,
            type: $type,
        );

        $context->meta['execution_id'] = $execution->id;

        try {
            // ── Transition to processing and run ─────────────────
            $this->tracker->beginExecution();

            $result = $next($context);

            // ── Finalize on success ──────────────────────────────
            $this->tracker->completeExecution();
            $result->executionId = $execution->id;

            return $result;

        } catch (\Throwable $e) {
            $this->tracker->failExecution($e);
            throw $e;
        }
    }
}
