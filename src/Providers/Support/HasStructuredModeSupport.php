<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Support;

use Prism\Prism\Enums\StructuredMode;

/**
 * Trait for services that support structured output mode configuration.
 *
 * Provides fluent methods for specifying how structured output should be handled.
 * Uses the clone pattern for immutability.
 *
 * Available modes:
 * - Auto (default) - Let Prism choose the best mode for the provider
 * - Structured - Use native JSON schema (OpenAI: requires all fields to be required)
 * - Json - Use JSON mode with schema in prompt (allows optional fields)
 */
trait HasStructuredModeSupport
{
    /**
     * The structured output mode.
     */
    private ?StructuredMode $structuredMode = null;

    /**
     * Set the structured output mode.
     *
     * @param  StructuredMode  $mode  The mode to use for structured output.
     */
    public function usingStructuredMode(StructuredMode $mode): static
    {
        $clone = clone $this;
        $clone->structuredMode = $mode;

        return $clone;
    }

    /**
     * Use JSON mode for structured output.
     *
     * JSON mode embeds the schema in the prompt, allowing optional fields
     * to work correctly. Use this when your schema has optional() fields.
     */
    public function usingJsonMode(): static
    {
        return $this->usingStructuredMode(StructuredMode::Json);
    }

    /**
     * Use native structured mode for structured output.
     *
     * Native mode uses the provider's JSON schema feature directly.
     * Note: OpenAI's native mode requires ALL fields to be required.
     */
    public function usingNativeMode(): static
    {
        return $this->usingStructuredMode(StructuredMode::Structured);
    }

    /**
     * Use auto mode (let Prism choose the best mode for the provider).
     *
     * This is the default behavior.
     */
    public function usingAutoMode(): static
    {
        return $this->usingStructuredMode(StructuredMode::Auto);
    }

    /**
     * Get the configured structured mode.
     */
    protected function getStructuredMode(): ?StructuredMode
    {
        return $this->structuredMode;
    }
}
