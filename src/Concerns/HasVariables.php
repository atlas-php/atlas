<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Concerns;

use Atlasphp\Atlas\Support\VariableInterpolator;
use Atlasphp\Atlas\Support\VariableRegistry;

/**
 * Adds variable interpolation to pending request builders.
 *
 * Provides {VARIABLE} and {DOT.NOTATION} interpolation for instructions and
 * optionally message content. Resolves variables from three layers: config
 * (lowest) → global registry → per-call withVariables() (highest).
 */
trait HasVariables
{
    /** @var array<string, mixed> */
    protected array $variables = [];

    protected bool $interpolateMessages = false;

    /**
     * Set per-call variable overrides (highest priority).
     *
     * @param  array<string, mixed>  $variables
     */
    public function withVariables(array $variables): static
    {
        $this->variables = array_replace_recursive($this->variables, $variables);

        return $this;
    }

    /**
     * Enable variable interpolation in message content.
     *
     * By default, only instructions are interpolated. Use this when message
     * content is a template that should have variables resolved.
     */
    public function withMessageInterpolation(bool $enabled = true): static
    {
        $this->interpolateMessages = $enabled;

        return $this;
    }

    /**
     * Resolve and interpolate a template string with all variable layers.
     */
    protected function interpolate(?string $template): ?string
    {
        if ($template === null) {
            return null;
        }

        if (! VariableInterpolator::hasPlaceholders($template)) {
            return $template;
        }

        $resolved = $this->resolveVariables();

        return VariableInterpolator::interpolate($template, $resolved);
    }

    /**
     * Merge all variable layers: config → global registry → per-call.
     *
     * @return array<string, mixed>
     */
    protected function resolveVariables(): array
    {
        /** @var VariableRegistry $registry */
        $registry = app(VariableRegistry::class);

        /** @phpstan-ignore-next-line */
        $meta = property_exists($this, 'meta') ? $this->meta : [];

        return $registry->merge($this->variables, $meta);
    }
}
