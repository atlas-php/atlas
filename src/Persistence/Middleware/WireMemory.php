<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Middleware;

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Executor\ExecutorResult;
use Atlasphp\Atlas\Middleware\AgentContext;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;
use Atlasphp\Atlas\Persistence\Concerns\HasMemory;
use Atlasphp\Atlas\Persistence\Memory\MemoryService;
use Atlasphp\Atlas\Persistence\Memory\Tools\MemoryRecall;
use Atlasphp\Atlas\Persistence\Memory\Tools\MemorySearch;
use Atlasphp\Atlas\Persistence\Memory\Tools\RememberMemory;
use Atlasphp\Atlas\Persistence\Models\Memory;
use Atlasphp\Atlas\Support\VariableRegistry;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Class WireMemory
 *
 * Agent-layer middleware that wires memory tools, variables, and owner meta
 * onto the agent context. Skips agents that don't use the HasMemory trait.
 */
class WireMemory
{
    public function __construct(
        protected readonly MemoryService $service,
        protected readonly VariableRegistry $variables,
    ) {}

    /**
     * @param  Closure(AgentContext): ExecutorResult  $next
     */
    public function handle(AgentContext $context, Closure $next): ExecutorResult
    {
        $agent = $context->agent;

        if ($agent === null || ! in_array(HasMemory::class, class_uses_recursive($agent), true)) {
            return $next($context);
        }

        /** @var Agent&HasMemory $agent */
        $config = $agent->memory();
        $owner = $this->resolveOwner($agent);
        $agentKey = $agent->key();

        // ── Register variable documents ─────────────────────────
        foreach ($config->getVariableDocuments() as $type) {
            $doc = $this->recallWithFallback($owner, $type, $agentKey);
            $this->variables->register(strtoupper($type), $doc !== null ? $doc->content : '');
        }

        // ── Register memory tools ───────────────────────────────
        if ($config->hasSearchTool()) {
            $context->tools[] = app(MemorySearch::class);
        }

        if ($config->hasRecallTool()) {
            $context->tools[] = app(MemoryRecall::class);
        }

        if ($config->hasRememberTool()) {
            $context->tools[] = app(RememberMemory::class);
        }

        // ── Pass owner context through meta for tools ───────────
        if ($owner !== null) {
            $context->meta['memory_owner_type'] = $owner->getMorphClass();
            $context->meta['memory_owner_id'] = $owner->getKey();
        }

        $context->meta['memory_agent'] = $agentKey;

        return $next($context);
    }

    /**
     * Try agent-specific memory first, fall back to agent-agnostic.
     */
    protected function recallWithFallback(?Model $owner, string $type, ?string $agentKey): ?Memory
    {
        if ($agentKey !== null) {
            $agentSpecific = $this->service->recall($owner, $type, agent: $agentKey);

            if ($agentSpecific !== null) {
                return $agentSpecific;
            }
        }

        return $this->service->recall($owner, $type, agent: null);
    }

    /**
     * Resolve the memory owner from the agent's conversation setup.
     */
    protected function resolveOwner(Agent $agent): ?Model
    {
        if (! in_array(HasConversations::class, class_uses_recursive($agent), true)) {
            return null;
        }

        /** @var Agent&HasConversations $agent */
        $author = $agent->resolveAuthor();

        if ($author !== null) {
            return $author;
        }

        $conversation = $agent->resolveConversation();

        return $conversation?->owner;
    }
}
