<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Support;

/**
 * Trait for services that support variable configuration.
 *
 * Provides fluent methods for managing variables used in system prompt interpolation.
 * Uses the clone pattern for immutability.
 */
trait HasVariablesSupport
{
    /**
     * Variables for system prompt interpolation.
     *
     * @var array<string, mixed>
     */
    private array $variables = [];

    /**
     * Set variables for system prompt interpolation.
     *
     * Replaces any previously set variables entirely.
     *
     * @param  array<string, mixed>  $variables
     */
    public function withVariables(array $variables): static
    {
        $clone = clone $this;
        $clone->variables = $variables;

        return $clone;
    }

    /**
     * Merge variables with any previously set variables.
     *
     * Later calls override earlier values for the same keys.
     *
     * @param  array<string, mixed>  $variables
     */
    public function mergeVariables(array $variables): static
    {
        $clone = clone $this;
        $clone->variables = [...$clone->variables, ...$variables];

        return $clone;
    }

    /**
     * Clear all variables.
     */
    public function clearVariables(): static
    {
        $clone = clone $this;
        $clone->variables = [];

        return $clone;
    }

    /**
     * Get the configured variables.
     *
     * @return array<string, mixed>
     */
    protected function getVariables(): array
    {
        return $this->variables;
    }
}
