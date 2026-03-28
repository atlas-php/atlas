<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools;

/**
 * Normalized tool definition consumed by the driver layer.
 *
 * Drivers never see the Tool class — they receive ToolDefinition instances
 * containing the name, description, and JSON Schema parameters.
 */
class ToolDefinition
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $parameters,
    ) {}

    /**
     * Whether this tool definition has parameters defined.
     */
    public function hasParameters(): bool
    {
        return $this->parameters !== [];
    }
}
