<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Middleware;

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Executor\ExecutorResult;
use Atlasphp\Atlas\Middleware\AgentContext;
use Atlasphp\Atlas\Middleware\Contracts\AgentMiddleware;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Services\ExecutionService;
use Atlasphp\Atlas\Responses\Usage;
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
class TrackExecution implements AgentMiddleware
{
    public function __construct(
        protected readonly ExecutionService $executionService,
    ) {}

    /**
     * @param  Closure(AgentContext): mixed  $next
     */
    public function handle(AgentContext $context, Closure $next): mixed
    {
        $agent = $context->agent;

        // Resolve provider/model from agent (may be null → config defaults)
        $default = app(AtlasConfig::class)->defaultFor('text');
        $rawProvider = $agent !== null
            ? ($agent->provider() ?? $default['provider'] ?? 'unknown')
            : ($default['provider'] ?? 'unknown');

        $provider = $rawProvider instanceof Provider
            ? $rawProvider->value
            : (string) $rawProvider;
        $model = $agent?->model() ?? (string) ($default['model'] ?? 'unknown');

        // ── Resolve execution type from meta (set by pending request) ─
        $type = ExecutionType::tryFrom($context->meta['execution_type'] ?? '')
            ?? ExecutionType::Text;

        // ── Adopt pre-created execution or create new ─────────────
        // Queue dispatch pre-creates an execution record so the UI has
        // an ID immediately. Adopt it here instead of creating a duplicate.
        $preExistingId = $context->meta['execution_id'] ?? null;

        if ($preExistingId !== null) {
            $execution = $this->executionService->adoptExecution(
                id: (int) $preExistingId,
                provider: $provider,
                model: $model,
                agent: $agent?->key(),
                conversationId: $context->meta['conversation_id'] ?? null,
                type: $type,
            );
        } else {
            $execution = $this->executionService->createExecution(
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
            $this->executionService->beginExecution();

            $result = $next($context);

            // ── Finalize on success — extract usage from result ──
            $usage = isset($result->usage) && $result->usage instanceof Usage ? $result->usage : null;
            $this->executionService->completeExecution($usage);

            if ($result instanceof ExecutorResult) {
                $result->executionId = $execution->id;
            }

            return $result;

        } catch (\Throwable $e) {
            $this->executionService->failExecution($e);
            throw $e;
        }
    }
}
