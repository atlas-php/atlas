<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools;

use Atlasphp\Atlas\Tools\Contracts\ToolContract;
use Atlasphp\Atlas\Tools\Support\PrismParameterConverter;
use Atlasphp\Atlas\Tools\Support\ToolParameter;
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
     * @return array<int, ToolParameter>
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

        foreach ($this->parameters() as $param) {
            PrismParameterConverter::addParameter($tool, $param);
        }

        $tool->using($handler);

        return $tool;
    }

    /**
     * Build the JSON schema for all parameters.
     *
     * @return array<string, mixed>
     */
    public function buildParameterSchema(): array
    {
        $properties = [];
        $required = [];

        foreach ($this->parameters() as $param) {
            $properties[$param->name] = $param->toSchema();
            if ($param->required) {
                $required[] = $param->name;
            }
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }
}
