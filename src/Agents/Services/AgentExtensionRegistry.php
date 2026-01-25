<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Services;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Contracts\AgentDecoratorContract;
use Atlasphp\Atlas\Concerns\AbstractExtensionRegistry;

/**
 * Registry for agent-related extensions.
 *
 * Manages agent decorators that can wrap and modify agent behavior.
 * Decorators are applied in priority order (highest first) when
 * resolving agents through the AgentResolver.
 *
 * Example:
 * ```php
 * $registry = app(AgentExtensionRegistry::class);
 *
 * $registry->registerDecorator(new LoggingDecorator());
 * $registry->registerDecorator(new FeatureFlagDecorator());
 * ```
 */
class AgentExtensionRegistry extends AbstractExtensionRegistry
{
    /**
     * Registered decorators.
     *
     * @var array<int, AgentDecoratorContract>
     */
    protected array $decorators = [];

    /**
     * Register an agent decorator.
     *
     * Decorators wrap agents to add or modify behavior.
     */
    public function registerDecorator(AgentDecoratorContract $decorator): static
    {
        $this->decorators[] = $decorator;

        return $this;
    }

    /**
     * Apply all applicable decorators to an agent.
     *
     * Decorators are applied in priority order (highest first).
     */
    public function applyDecorators(AgentContract $agent): AgentContract
    {
        $decorators = $this->getApplicableDecorators($agent);

        foreach ($decorators as $decorator) {
            $agent = $decorator->decorate($agent);
        }

        return $agent;
    }

    /**
     * Get decorators that apply to the given agent, sorted by priority.
     *
     * @return array<int, AgentDecoratorContract>
     */
    protected function getApplicableDecorators(AgentContract $agent): array
    {
        $applicable = array_filter(
            $this->decorators,
            fn (AgentDecoratorContract $decorator): bool => $decorator->appliesTo($agent)
        );

        // Sort by priority (highest first)
        usort(
            $applicable,
            fn (AgentDecoratorContract $a, AgentDecoratorContract $b): int => $b->priority() <=> $a->priority()
        );

        return $applicable;
    }

    /**
     * Check if any decorators are registered.
     */
    public function hasDecorators(): bool
    {
        return $this->decorators !== [];
    }

    /**
     * Get the count of registered decorators.
     */
    public function decoratorCount(): int
    {
        return count($this->decorators);
    }

    /**
     * Clear all registered decorators.
     */
    public function clearDecorators(): static
    {
        $this->decorators = [];

        return $this;
    }
}
