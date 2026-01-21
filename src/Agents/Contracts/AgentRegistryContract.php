<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Contracts;

/**
 * Contract for agent registry implementations.
 *
 * Defines the interface for registering and retrieving agent definitions.
 */
interface AgentRegistryContract
{
    /**
     * Register an agent class.
     *
     * @param  class-string<AgentContract>  $agentClass  The agent class to register.
     * @param  bool  $override  Whether to override if already registered.
     */
    public function register(string $agentClass, bool $override = false): void;

    /**
     * Register an agent instance directly.
     *
     * @param  AgentContract  $agent  The agent instance to register.
     * @param  bool  $override  Whether to override if already registered.
     */
    public function registerInstance(AgentContract $agent, bool $override = false): void;

    /**
     * Get an agent by its key.
     *
     * @param  string  $key  The agent key.
     */
    public function get(string $key): AgentContract;

    /**
     * Check if an agent is registered.
     *
     * @param  string  $key  The agent key.
     */
    public function has(string $key): bool;

    /**
     * Get all registered agents.
     *
     * @return array<string, AgentContract>
     */
    public function all(): array;
}
