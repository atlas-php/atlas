<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Services;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Agents\Exceptions\AgentException;
use Atlasphp\Atlas\Agents\Exceptions\InvalidAgentException;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Resolves agents from various sources.
 *
 * Handles resolution from registry keys, class names, or instance passthrough.
 * Provides a unified interface for obtaining agent instances.
 *
 * After resolution, applies any registered decorators from the extension registry.
 */
class AgentResolver
{
    public function __construct(
        protected AgentRegistryContract $registry,
        protected Container $container,
        protected ?AgentExtensionRegistry $extensionRegistry = null,
    ) {}

    /**
     * Resolve an agent from a key, class, or instance.
     *
     * Resolution order:
     * 1. Instance passthrough - if already an AgentContract, return as-is
     * 2. Registry lookup - check if it's a registered key
     * 3. Container instantiation - attempt to instantiate as a class
     *
     * After resolution, applies any registered decorators from the extension registry.
     *
     * @param  string|AgentContract  $agent  The agent key, class, or instance.
     *
     * @throws AgentException If the agent cannot be resolved.
     * @throws InvalidAgentException If the resolved class doesn't implement AgentContract.
     */
    public function resolve(string|AgentContract $agent): AgentContract
    {
        // Instance passthrough
        if ($agent instanceof AgentContract) {
            return $this->applyDecorators($agent);
        }

        // Registry lookup
        if ($this->registry->has($agent)) {
            return $this->applyDecorators($this->registry->get($agent));
        }

        // Container instantiation
        return $this->applyDecorators($this->resolveFromContainer($agent));
    }

    /**
     * Apply decorators from the extension registry.
     */
    protected function applyDecorators(AgentContract $agent): AgentContract
    {
        if ($this->extensionRegistry === null) {
            return $agent;
        }

        return $this->extensionRegistry->applyDecorators($agent);
    }

    /**
     * Resolve an agent from the container.
     *
     * @throws AgentException If the class cannot be instantiated.
     * @throws InvalidAgentException If the class doesn't implement AgentContract.
     */
    protected function resolveFromContainer(string $class): AgentContract
    {
        if (! class_exists($class)) {
            throw AgentException::resolutionFailed($class);
        }

        try {
            $instance = $this->container->make($class);
        } catch (Throwable $e) {
            throw AgentException::resolutionFailed("{$class}: {$e->getMessage()}");
        }

        if (! $instance instanceof AgentContract) {
            throw InvalidAgentException::doesNotImplementContract($class);
        }

        return $instance;
    }
}
