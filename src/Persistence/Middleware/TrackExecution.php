<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Middleware;

use Atlasphp\Atlas\Executor\ExecutorResult;
use Atlasphp\Atlas\Middleware\AgentContext;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Services\ExecutionService;
use Closure;

/**
 * Class TrackExecution
 *
 * Agent-layer middleware that wraps the entire execution to create and track
 * an execution record. Creates the execution in pending state before the agent
 * runs, transitions through processing, and finalizes as completed or failed.
 *
 * When running from a queued dispatch, adopts the pre-created execution
 * record (via execution_id in meta) instead of creating a duplicate.
 */
class TrackExecution
{
    public function __construct(
        protected readonly ExecutionService $tracker,
    ) {}

    /**
     * @param  Closure(AgentContext): mixed  $next
     */
    public function handle(AgentContext $context, Closure $next): mixed
    {
        $agent = $context->agent;

        // Resolve provider/model from agent (may be null → config defaults)
        $provider = $agent !== null
            ? (string) ($agent->provider() ?? config('atlas.defaults.text.provider', 'unknown'))
            : (string) config('atlas.defaults.text.provider', 'unknown');
        $model = $agent?->model() ?? (string) config('atlas.defaults.text.model', 'unknown');

        // ── Resolve execution type from meta (set by pending request) ─
        $type = ExecutionType::tryFrom($context->meta['execution_type'] ?? '')
            ?? ExecutionType::Text;

        // ── Adopt pre-created execution or create new ─────────────
        // Queue dispatch pre-creates an execution record so the UI has
        // an ID immediately. Adopt it here instead of creating a duplicate.
        $preExistingId = $context->meta['execution_id'] ?? null;

        if ($preExistingId !== null) {
            $execution = $this->tracker->adoptExecution(
                id: (int) $preExistingId,
                provider: $provider,
                model: $model,
                agent: $agent?->key(),
                conversationId: $context->meta['conversation_id'] ?? null,
                type: $type,
            );
        } else {
            $execution = $this->tracker->createExecution(
                provider: $provider,
                model: $model,
                meta: $context->meta,
                agent: $agent?->key(),
                conversationId: $context->meta['conversation_id'] ?? null,
                messageId: $context->meta['trigger_message_id'] ?? null,
                type: $type,
            );
        }

        $context->meta['execution_id'] = $execution->id;

        try {
            // ── Transition to processing and run ─────────────────
            $this->tracker->beginExecution();

            $result = $next($context);

            // ── Finalize on success ──────────────────────────────
            $this->tracker->completeExecution();

            if ($result instanceof ExecutorResult) {
                $result->executionId = $execution->id;
            }

            return $result;

        } catch (\Throwable $e) {
            $this->tracker->failExecution($e);
            throw $e;
        }
    }
}
