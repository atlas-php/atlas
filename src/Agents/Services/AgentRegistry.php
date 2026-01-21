<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Services;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Agents\Exceptions\AgentException;
use Atlasphp\Atlas\Agents\Exceptions\AgentNotFoundException;
use Atlasphp\Atlas\Agents\Exceptions\InvalidAgentException;
use Illuminate\Contracts\Container\Container;

/**
 * Registry for managing agent definitions.
 *
 * Provides registration, retrieval, and querying of agents by key.
 * Supports both class registration and instance registration.
 */
class AgentRegistry implements AgentRegistryContract
{
    /**
     * Registered agents keyed by their key.
     *
     * @var array<string, AgentContract>
     */
    protected array $agents = [];

    public function __construct(
        protected Container $container,
    ) {}

    /**
     * Register an agent class.
     *
     * @param  class-string<AgentContract>  $agentClass
     *
     * @throws InvalidAgentException If the class doesn't implement AgentContract.
     * @throws AgentException If already registered and override is false.
     */
    public function register(string $agentClass, bool $override = false): void
    {
        /** @var AgentContract $agent */
        $agent = $this->container->make($agentClass);

        $this->registerInstance($agent, $override);
    }

    /**
     * Register an agent instance directly.
     *
     * @throws AgentException If already registered and override is false.
     */
    public function registerInstance(AgentContract $agent, bool $override = false): void
    {
        $key = $agent->key();

        if (! $override && isset($this->agents[$key])) {
            throw AgentException::duplicateRegistration($key);
        }

        $this->agents[$key] = $agent;
    }

    /**
     * Get an agent by its key.
     *
     * @throws AgentNotFoundException If the agent is not found.
     */
    public function get(string $key): AgentContract
    {
        if (! isset($this->agents[$key])) {
            throw AgentNotFoundException::forKey($key);
        }

        return $this->agents[$key];
    }

    /**
     * Check if an agent is registered.
     */
    public function has(string $key): bool
    {
        return isset($this->agents[$key]);
    }

    /**
     * Get all registered agents.
     *
     * @return array<string, AgentContract>
     */
    public function all(): array
    {
        return $this->agents;
    }

    /**
     * Get all registered agent keys.
     *
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->agents);
    }

    /**
     * Unregister an agent by its key.
     *
     * @return bool True if the agent was unregistered, false if not found.
     */
    public function unregister(string $key): bool
    {
        if (! isset($this->agents[$key])) {
            return false;
        }

        unset($this->agents[$key]);

        return true;
    }

    /**
     * Get the count of registered agents.
     */
    public function count(): int
    {
        return count($this->agents);
    }

    /**
     * Clear all registered agents.
     */
    public function clear(): void
    {
        $this->agents = [];
    }
}
