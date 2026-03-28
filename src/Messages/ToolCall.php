<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Messages;

/**
 * Represents a tool call requested by the model.
 */
class ToolCall
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $arguments,
    ) {}
}
