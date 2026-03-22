<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Middleware;

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Middleware\AgentContext;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;
use Atlasphp\Atlas\Persistence\Concerns\HasMemory;
use Atlasphp\Atlas\Persistence\Memory\MemoryContext;
use Atlasphp\Atlas\Persistence\Memory\MemoryModelService;
use Atlasphp\Atlas\Persistence\Memory\Tools\MemoryRecall;
use Atlasphp\Atlas\Persistence\Memory\Tools\MemorySearch;
use Atlasphp\Atlas\Persistence\Memory\Tools\RememberMemory;
use Atlasphp\Atlas\Persistence\Models\Memory;
use Atlasphp\Atlas\Support\VariableRegistry;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;

/**
 * Class WireMemory
 *
 * Agent-layer middleware that wires memory tools, variables, and context
 * onto the agent. Skips agents that don't use the HasMemory trait.
 */
class WireMemory
{
    public function __construct(
        protected readonly MemoryModelService $service,
        protected readonly MemoryContext $memoryContext,
        protected readonly VariableRegistry $variables,
        protected readonly Application $app,
    ) {}

    /**
     * @param  Closure(AgentContext): mixed  $next
     */
    public function handle(AgentContext $context, Closure $next): mixed
    {
        $agent = $context->agent;

        if ($agent === null || ! in_array(HasMemory::class, class_uses_recursive($agent), true)) {
            return $next($context);
        }

        /** @var Agent&HasMemory $agent */
        $config = $agent->memory();
        $owner = $this->resolveOwner($agent);
        $agentKey = $agent->key();

        // ── Configure scoped memory context ─────────────────────
        $this->memoryContext->configure($owner, $agentKey);

        // ── Register variable documents ─────────────────────────
        $registeredKeys = [];

        foreach ($config->getVariableDocuments() as $type) {
            $key = strtoupper($type);
            $registeredKeys[] = $key;
            $doc = $this->recallWithFallback($owner, $type, $agentKey);
            $this->variables->register($key, $doc !== null ? $doc->content : '');
        }

        // ── Register memory tools ───────────────────────────────
        if ($config->hasSearchTool()) {
            $context->tools[] = $this->app->make(MemorySearch::class);
        }

        if ($config->hasRecallTool()) {
            $context->tools[] = $this->app->make(MemoryRecall::class);
        }

        if ($config->hasRememberTool()) {
            $context->tools[] = $this->app->make(RememberMemory::class);
        }

        try {
            return $next($context);
        } finally {
            // Clean up registered variables to prevent cross-request
            // state contamination in long-running processes (Octane, queue).
            foreach ($registeredKeys as $key) {
                $this->variables->unregister($key);
            }

            $this->memoryContext->reset();
        }
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
