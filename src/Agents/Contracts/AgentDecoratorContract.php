<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Contracts;

/**
 * Contract for agent decorators.
 *
 * Decorators wrap agents to add or modify behavior without changing
 * the original agent class. They receive the original agent and can
 * delegate to it or override its methods.
 *
 * Use cases:
 * - Adding tools dynamically based on context
 * - Modifying system prompts at runtime
 * - Adding logging or monitoring to specific agents
 * - Feature flags for agent capabilities
 */
interface AgentDecoratorContract
{
    /**
     * Decorate an agent, returning a modified version.
     *
     * The decorator can wrap the agent, modify its behavior,
     * or return a completely different agent instance.
     *
     * @param  AgentContract  $agent  The agent to decorate.
     * @return AgentContract The decorated agent.
     */
    public function decorate(AgentContract $agent): AgentContract;

    /**
     * Check if this decorator should apply to the given agent.
     *
     * @param  AgentContract  $agent  The agent to check.
     * @return bool True if this decorator applies.
     */
    public function appliesTo(AgentContract $agent): bool;

    /**
     * Get the priority of this decorator.
     *
     * Higher priority decorators are applied first.
     *
     * @return int The priority (default: 0).
     */
    public function priority(): int;
}
