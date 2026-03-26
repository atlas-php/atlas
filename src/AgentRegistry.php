<?php

declare(strict_types=1);

namespace Atlasphp\Atlas;

use Atlasphp\Atlas\Exceptions\AgentNotFoundException;
use Illuminate\Contracts\Foundation\Application;

/**
 * Resolves agent keys to Agent instances.
 *
 * Supports auto-discovery from a configured directory and manual registration.
 * Agent classes are resolved from the container on each call to support DI.
 */
class AgentRegistry
{
    /** @var array<string, class-string<Agent>> */
    protected array $agents = [];

    public function __construct(
        protected readonly Application $app,
    ) {}

    /**
     * Register an agent class by resolving its key.
     *
     * @param  class-string<Agent>  $agentClass
     */
    public function register(string $agentClass): void
    {
        /** @var Agent $agent */
        $agent = $this->app->make($agentClass);
        $this->agents[$agent->key()] = $agentClass;
    }

    /**
     * Resolve an agent instance by key.
     *
     * @throws AgentNotFoundException If the agent is not registered.
     */
    public function resolve(string $key): Agent
    {
        if (! isset($this->agents[$key])) {
            throw new AgentNotFoundException($key);
        }

        return $this->app->make($this->agents[$key]);
    }

    /**
     * Check if an agent is registered by key.
     */
    public function has(string $key): bool
    {
        return isset($this->agents[$key]);
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
     * Discover agent classes from a directory.
     *
     * Scans PHP files in the given path and registers any class that
     * extends the Agent base class.
     */
    public function discover(string $path, string $namespace): void
    {
        if (! is_dir($path)) {
            return;
        }

        $files = glob($path.'/*.php');

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $class = $namespace.'\\'.pathinfo($file, PATHINFO_FILENAME);

            if (class_exists($class) && is_subclass_of($class, Agent::class)) {
                $this->register($class);
            }
        }
    }
}
