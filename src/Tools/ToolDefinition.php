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
     * Configure the Prism Tool with additional options.
     *
     * Override this method to access the full Prism Tool API:
     * - `$tool->failed($handler)` - Custom error handling
     * - `$tool->withErrorHandling()` - Enable error handling
     * - `$tool->withoutErrorHandling()` - Disable error handling
     * - `$tool->withProviderOptions($options)` - Provider-specific config
     *
     * @param  PrismTool  $tool  The fully-built Prism Tool.
     * @return PrismTool The configured tool (can chain methods).
     */
    protected function configurePrismTool(PrismTool $tool): PrismTool
    {
        return $tool;
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

        return $this->configurePrismTool($tool);
    }
}
