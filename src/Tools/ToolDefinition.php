<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools;

use Atlasphp\Atlas\Tools\Contracts\ToolContract;
use Prism\Prism\Contracts\Schema;
use Prism\Prism\Tool as PrismTool;

/**
 * Base class for tool definitions.
 *
 * Provides conversion to Prism Tool format and sensible defaults.
 * Extend this class to create custom tools with minimal boilerplate.
 */
abstract class ToolDefinition implements ToolContract
{
    /**
     * Get the parameters this tool accepts.
     *
     * @return array<int, Schema>
     */
    public function parameters(): array
    {
        return [];
    }

    /**
     * Convert this tool to a Prism Tool instance.
     *
     * @param  callable  $handler  The handler function to execute the tool.
     */
    public function toPrismTool(callable $handler): PrismTool
    {
        $tool = new PrismTool;
        $tool->as($this->name());
        $tool->for($this->description());

        foreach ($this->parameters() as $schema) {
            $tool->withParameter($schema);
        }

        $tool->using($handler);

        return $tool;
    }
}
