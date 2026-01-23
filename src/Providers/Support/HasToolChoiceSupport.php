<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Support;

use Atlasphp\Atlas\Tools\Enums\ToolChoice;

/**
 * Trait for configuring tool choice behavior.
 *
 * Provides fluent methods for controlling when and how tools
 * are used during agent execution.
 */
trait HasToolChoiceSupport
{
    /**
     * The tool choice mode or specific tool name.
     */
    private ToolChoice|string|null $toolChoice = null;

    /**
     * Set the tool choice mode for this request.
     *
     * @param  ToolChoice|string  $toolChoice  The tool choice mode or specific tool name.
     */
    public function withToolChoice(ToolChoice|string $toolChoice): static
    {
        $clone = clone $this;
        $clone->toolChoice = $toolChoice;

        return $clone;
    }

    /**
     * Require that the model uses at least one tool.
     *
     * Shorthand for withToolChoice(ToolChoice::Any).
     */
    public function requireTool(): static
    {
        return $this->withToolChoice(ToolChoice::Any);
    }

    /**
     * Prevent the model from using any tools.
     *
     * Shorthand for withToolChoice(ToolChoice::None).
     */
    public function disableTools(): static
    {
        return $this->withToolChoice(ToolChoice::None);
    }

    /**
     * Force the model to use a specific tool.
     *
     * @param  string  $name  The name of the tool to force.
     */
    public function forceTool(string $name): static
    {
        return $this->withToolChoice($name);
    }

    /**
     * Get the configured tool choice.
     */
    protected function getToolChoice(): ToolChoice|string|null
    {
        return $this->toolChoice;
    }
}
