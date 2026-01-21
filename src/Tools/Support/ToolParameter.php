<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Support;

use Prism\Prism\Contracts\Schema;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

/**
 * Definition of a tool parameter for AI function calling.
 *
 * Supports all JSON Schema types including primitives, enums,
 * arrays with item schemas, and nested objects.
 */
final readonly class ToolParameter
{
    /**
     * @param  string  $name  The parameter name.
     * @param  string  $type  The JSON Schema type (string, integer, number, boolean, array, object).
     * @param  string  $description  Human-readable description for the AI.
     * @param  bool  $required  Whether the parameter is required.
     * @param  mixed  $default  Default value if not provided.
     * @param  array<int, string|int>|null  $enum  Allowed values for enum types.
     * @param  array<string, mixed>|null  $items  Schema for array item types.
     * @param  array<string, ToolParameter>|null  $properties  Nested properties for object types.
     */
    public function __construct(
        public string $name,
        public string $type,
        public string $description,
        public bool $required = true,
        public mixed $default = null,
        public ?array $enum = null,
        public ?array $items = null,
        public ?array $properties = null,
    ) {}

    /**
     * Create a string parameter.
     *
     * @param  string  $name  The parameter name.
     * @param  string  $description  Parameter description.
     * @param  bool  $required  Whether required.
     * @param  string|null  $default  Default value.
     */
    public static function string(
        string $name,
        string $description,
        bool $required = true,
        ?string $default = null,
    ): self {
        return new self($name, 'string', $description, $required, $default);
    }

    /**
     * Create an integer parameter.
     *
     * @param  string  $name  The parameter name.
     * @param  string  $description  Parameter description.
     * @param  bool  $required  Whether required.
     * @param  int|null  $default  Default value.
     */
    public static function integer(
        string $name,
        string $description,
        bool $required = true,
        ?int $default = null,
    ): self {
        return new self($name, 'integer', $description, $required, $default);
    }

    /**
     * Create a number parameter (float).
     *
     * @param  string  $name  The parameter name.
     * @param  string  $description  Parameter description.
     * @param  bool  $required  Whether required.
     * @param  float|null  $default  Default value.
     */
    public static function number(
        string $name,
        string $description,
        bool $required = true,
        ?float $default = null,
    ): self {
        return new self($name, 'number', $description, $required, $default);
    }

    /**
     * Create a boolean parameter.
     *
     * @param  string  $name  The parameter name.
     * @param  string  $description  Parameter description.
     * @param  bool  $required  Whether required.
     * @param  bool|null  $default  Default value.
     */
    public static function boolean(
        string $name,
        string $description,
        bool $required = true,
        ?bool $default = null,
    ): self {
        return new self($name, 'boolean', $description, $required, $default);
    }

    /**
     * Create an enum parameter with allowed values.
     *
     * @param  string  $name  The parameter name.
     * @param  string  $description  Parameter description.
     * @param  array<int, string|int>  $values  Allowed values.
     * @param  bool  $required  Whether required.
     * @param  string|int|null  $default  Default value.
     */
    public static function enum(
        string $name,
        string $description,
        array $values,
        bool $required = true,
        string|int|null $default = null,
    ): self {
        return new self($name, 'string', $description, $required, $default, $values);
    }

    /**
     * Create an array parameter.
     *
     * @param  string  $name  The parameter name.
     * @param  string  $description  Parameter description.
     * @param  array<string, mixed>|null  $items  Schema for array items.
     * @param  bool  $required  Whether required.
     */
    public static function array(
        string $name,
        string $description,
        ?array $items = null,
        bool $required = true,
    ): self {
        return new self($name, 'array', $description, $required, null, null, $items);
    }

    /**
     * Create an object parameter with nested properties.
     *
     * @param  string  $name  The parameter name.
     * @param  string  $description  Parameter description.
     * @param  array<string, ToolParameter>  $properties  Nested properties.
     * @param  bool  $required  Whether required.
     */
    public static function object(
        string $name,
        string $description,
        array $properties,
        bool $required = true,
    ): self {
        return new self($name, 'object', $description, $required, null, null, null, $properties);
    }

    /**
     * Convert the parameter to a JSON Schema array.
     *
     * @return array<string, mixed>
     */
    public function toSchema(): array
    {
        $schema = [
            'type' => $this->type,
            'description' => $this->description,
        ];

        if ($this->enum !== null) {
            $schema['enum'] = $this->enum;
        }

        if ($this->default !== null) {
            $schema['default'] = $this->default;
        }

        if ($this->items !== null) {
            $schema['items'] = $this->items;
        }

        if ($this->properties !== null) {
            $schema['properties'] = [];
            $requiredProps = [];

            foreach ($this->properties as $prop) {
                $schema['properties'][$prop->name] = $prop->toSchema();
                if ($prop->required) {
                    $requiredProps[] = $prop->name;
                }
            }

            if ($requiredProps !== []) {
                $schema['required'] = $requiredProps;
            }
        }

        return $schema;
    }

    /**
     * Convert the parameter to a Prism Schema object.
     */
    public function toPrismSchema(): Schema
    {
        if ($this->enum !== null) {
            return new EnumSchema(
                name: $this->name,
                description: $this->description,
                options: $this->enum,
            );
        }

        return match ($this->type) {
            'string' => new StringSchema($this->name, $this->description),
            'integer', 'number' => new NumberSchema($this->name, $this->description),
            'boolean' => new BooleanSchema($this->name, $this->description),
            'array' => $this->buildArraySchema(),
            'object' => $this->buildObjectSchema(),
            default => new StringSchema($this->name, $this->description),
        };
    }

    /**
     * Build a Prism ArraySchema.
     */
    protected function buildArraySchema(): ArraySchema
    {
        // Default to string items if not specified
        $itemSchema = new StringSchema('item', 'Array item');

        if ($this->items !== null && isset($this->items['type'])) {
            $itemSchema = match ($this->items['type']) {
                'string' => new StringSchema('item', $this->items['description'] ?? 'String item'),
                'integer', 'number' => new NumberSchema('item', $this->items['description'] ?? 'Number item'),
                'boolean' => new BooleanSchema('item', $this->items['description'] ?? 'Boolean item'),
                default => new StringSchema('item', $this->items['description'] ?? 'Item'),
            };
        }

        return new ArraySchema($this->name, $this->description, $itemSchema);
    }

    /**
     * Build a Prism ObjectSchema.
     */
    protected function buildObjectSchema(): ObjectSchema
    {
        $prismProperties = [];
        $requiredFields = [];

        if ($this->properties !== null) {
            foreach ($this->properties as $prop) {
                $prismProperties[] = $prop->toPrismSchema();
                if ($prop->required) {
                    $requiredFields[] = $prop->name;
                }
            }
        }

        return new ObjectSchema(
            name: $this->name,
            description: $this->description,
            properties: $prismProperties,
            requiredFields: $requiredFields,
        );
    }
}
