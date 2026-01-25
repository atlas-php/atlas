<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Support;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Contracts\AgentDecoratorContract;
use Atlasphp\Atlas\Agents\Enums\AgentType;
use Prism\Prism\Contracts\Schema;

/**
 * Base decorator that wraps an agent and delegates all methods.
 *
 * Extend this class and override specific methods to modify agent behavior.
 * The wrapped agent is accessible via the $agent property.
 *
 * Example:
 * ```php
 * class LoggingDecorator extends AgentDecorator
 * {
 *     public function appliesTo(AgentContract $agent): bool
 *     {
 *         return true; // Apply to all agents
 *     }
 *
 *     public function systemPrompt(): ?string
 *     {
 *         return $this->agent->systemPrompt() . "\n\nLog all interactions.";
 *     }
 * }
 * ```
 */
abstract class AgentDecorator implements AgentContract, AgentDecoratorContract
{
    protected AgentContract $agent;

    /**
     * Decorate the agent, storing the reference and returning this decorator.
     */
    public function decorate(AgentContract $agent): AgentContract
    {
        $clone = clone $this;
        $clone->agent = $agent;

        return $clone;
    }

    /**
     * Get the priority of this decorator.
     *
     * Override to change when this decorator is applied.
     */
    public function priority(): int
    {
        return 0;
    }

    // Delegate all AgentContract methods to the wrapped agent

    public function key(): string
    {
        return $this->agent->key();
    }

    public function name(): string
    {
        return $this->agent->name();
    }

    public function description(): ?string
    {
        return $this->agent->description();
    }

    public function provider(): ?string
    {
        return $this->agent->provider();
    }

    public function model(): ?string
    {
        return $this->agent->model();
    }

    public function type(): AgentType
    {
        return $this->agent->type();
    }

    public function systemPrompt(): ?string
    {
        return $this->agent->systemPrompt();
    }

    public function tools(): array
    {
        return $this->agent->tools();
    }

    public function providerTools(): array
    {
        return $this->agent->providerTools();
    }

    public function schema(): ?Schema
    {
        return $this->agent->schema();
    }

    public function temperature(): ?float
    {
        return $this->agent->temperature();
    }

    public function maxTokens(): ?int
    {
        return $this->agent->maxTokens();
    }

    public function maxSteps(): ?int
    {
        return $this->agent->maxSteps();
    }

    public function clientOptions(): array
    {
        return $this->agent->clientOptions();
    }

    public function providerOptions(): array
    {
        return $this->agent->providerOptions();
    }
}
