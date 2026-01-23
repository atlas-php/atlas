<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Support;

/**
 * Trait for services that support variable configuration.
 *
 * Provides a fluent withVariables() method for passing variables to operations.
 * Variables are typically used for system prompt interpolation. Uses the clone
 * pattern for immutability.
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
     * @param  array<string, mixed>  $variables
     */
    public function withVariables(array $variables): static
    {
        $clone = clone $this;
        $clone->variables = $variables;

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
