<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools;

use Atlasphp\Atlas\Tools\Contracts\ToolContract;
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
            $this->addParameterToTool($tool, $param);
        }

        $tool->using($handler);

        return $tool;
    }

    /**
     * Add a parameter to the Prism tool.
     */
    protected function addParameterToTool(PrismTool $tool, ToolParameter $param): void
    {
        $method = match ($param->type) {
            'string' => 'withStringParameter',
            'integer' => 'withNumberParameter',
            'number' => 'withNumberParameter',
            'boolean' => 'withBooleanParameter',
            'array' => 'withArrayParameter',
            'object' => 'withObjectParameter',
            default => 'withStringParameter',
        };

        if ($param->enum !== null) {
            $tool->withEnumParameter(
                $param->name,
                $param->description,
                $param->enum,
                $param->required,
            );

            return;
        }

        if ($method === 'withArrayParameter') {
            $schema = $param->toPrismSchema();
            $tool->withParameter($schema, $param->required);

            return;
        }

        if ($method === 'withObjectParameter') {
            $schema = $param->toPrismSchema();
            $tool->withParameter($schema, $param->required);

            return;
        }

        $tool->{$method}($param->name, $param->description, $param->required);
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
