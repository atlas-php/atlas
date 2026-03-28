<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools;

use Atlasphp\Atlas\Schema\Fields\Field;

/**
 * Abstract base class for user-defined tools.
 *
 * Tools are resolved from the Laravel container, so constructor injection works naturally.
 * Define parameters using the Schema field system and implement handle() to execute.
 */
abstract class Tool
{
    /**
     * The tool name the model uses to call this tool.
     * Should be lowercase with underscores.
     */
    abstract public function name(): string;

    /**
     * Describes when and how the model should use this tool.
     */
    abstract public function description(): string;

    /**
     * Execute the tool.
     *
     * @param  array<string, mixed>  $args  Parameter values from the model
     * @param  array<string, mixed>  $context  Metadata from withMeta()
     * @return mixed Serialized automatically by ToolSerializer
     */
    abstract public function handle(array $args, array $context): mixed;

    /**
     * Parameters this tool accepts. Returns Field instances from the Schema system.
     *
     * @return array<int, Field>
     */
    public function parameters(): array
    {
        return [];
    }

    /**
     * Convert to a ToolDefinition for the driver layer.
     */
    public function toDefinition(): ToolDefinition
    {
        return new ToolDefinition(
            name: $this->name(),
            description: $this->description(),
            parameters: $this->buildParameterSchema(),
        );
    }

    /**
     * Build JSON Schema from the parameters() array.
     *
     * @return array<string, mixed>
     */
    protected function buildParameterSchema(): array
    {
        $fields = $this->parameters();

        if ($fields === []) {
            return [];
        }

        $properties = [];
        $required = [];

        foreach ($fields as $field) {
            $properties[$field->name()] = $field->toSchema();

            if ($field->isRequired()) {
                $required[] = $field->name();
            }
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
            'additionalProperties' => false,
        ];
    }
}
