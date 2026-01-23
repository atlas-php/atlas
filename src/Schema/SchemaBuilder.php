<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Schema;

use Prism\Prism\Contracts\Schema as PrismSchema;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

/**
 * Fluent builder for creating Prism object schemas.
 *
 * Provides a clean API for defining schema properties with automatic
 * tracking of required fields. All fields are required by default.
 *
 * @example
 * $schema = Schema::object('order', 'Order details')
 *     ->string('id', 'Order ID')
 *     ->number('total', 'Total amount')
 *     ->string('notes', 'Notes')->optional()
 *     ->build();
 */
class SchemaBuilder
{
    /**
     * @var array<int, SchemaProperty>
     */
    protected array $properties = [];

    /**
     * @var array<int, string>
     */
    protected array $requiredFields = [];

    public function __construct(
        protected string $name,
        protected string $description,
    ) {}

    /**
     * Add a string property to the schema.
     *
     * @param  string  $name  The property name.
     * @param  string  $description  The property description.
     */
    public function string(string $name, string $description): SchemaProperty
    {
        $schema = new StringSchema($name, $description);

        return $this->addProperty($name, $schema);
    }

    /**
     * Add a number property to the schema.
     *
     * @param  string  $name  The property name.
     * @param  string  $description  The property description.
     */
    public function number(string $name, string $description): SchemaProperty
    {
        $schema = new NumberSchema($name, $description);

        return $this->addProperty($name, $schema);
    }

    /**
     * Add an integer property to the schema.
     *
     * Note: Prism uses NumberSchema for both integers and floats.
     *
     * @param  string  $name  The property name.
     * @param  string  $description  The property description.
     */
    public function integer(string $name, string $description): SchemaProperty
    {
        $schema = new NumberSchema($name, $description);

        return $this->addProperty($name, $schema);
    }

    /**
     * Add a boolean property to the schema.
     *
     * @param  string  $name  The property name.
     * @param  string  $description  The property description.
     */
    public function boolean(string $name, string $description): SchemaProperty
    {
        $schema = new BooleanSchema($name, $description);

        return $this->addProperty($name, $schema);
    }

    /**
     * Add an enum property to the schema.
     *
     * @param  string  $name  The property name.
     * @param  string  $description  The property description.
     * @param  array<int, string|int|float>  $options  The allowed values.
     */
    public function enum(string $name, string $description, array $options): SchemaProperty
    {
        $schema = new EnumSchema($name, $description, $options);

        return $this->addProperty($name, $schema);
    }

    /**
     * Add a string array property to the schema.
     *
     * @param  string  $name  The property name.
     * @param  string  $description  The property description.
     */
    public function stringArray(string $name, string $description): SchemaProperty
    {
        $itemSchema = new StringSchema('item', 'Array item');
        $schema = new ArraySchema($name, $description, $itemSchema);

        return $this->addProperty($name, $schema);
    }

    /**
     * Add a number array property to the schema.
     *
     * @param  string  $name  The property name.
     * @param  string  $description  The property description.
     */
    public function numberArray(string $name, string $description): SchemaProperty
    {
        $itemSchema = new NumberSchema('item', 'Array item');
        $schema = new ArraySchema($name, $description, $itemSchema);

        return $this->addProperty($name, $schema);
    }

    /**
     * Add a nested object property to the schema.
     *
     * @param  string  $name  The property name.
     * @param  string  $description  The property description.
     * @param  callable(SchemaBuilder): SchemaBuilder  $callback  Builder callback.
     */
    public function object(string $name, string $description, callable $callback): SchemaProperty
    {
        $nestedBuilder = new self($name, $description);
        $callback($nestedBuilder);
        $schema = $nestedBuilder->build();

        return $this->addProperty($name, $schema);
    }

    /**
     * Add an array of objects property to the schema.
     *
     * @param  string  $name  The property name.
     * @param  string  $description  The property description.
     * @param  callable(SchemaBuilder): SchemaBuilder  $callback  Builder callback for item schema.
     */
    public function array(string $name, string $description, callable $callback): SchemaProperty
    {
        $itemBuilder = new self('item', 'Array item');
        $callback($itemBuilder);
        $itemSchema = $itemBuilder->build();
        $schema = new ArraySchema($name, $description, $itemSchema);

        return $this->addProperty($name, $schema);
    }

    /**
     * Build the final ObjectSchema.
     */
    public function build(): ObjectSchema
    {
        $prismSchemas = [];

        foreach ($this->properties as $property) {
            $prismSchemas[] = $property->toPrismSchema();
        }

        return new ObjectSchema(
            name: $this->name,
            description: $this->description,
            properties: $prismSchemas,
            requiredFields: $this->requiredFields,
        );
    }

    /**
     * Add a property and track as required by default.
     */
    protected function addProperty(string $name, PrismSchema $schema): SchemaProperty
    {
        $property = new SchemaProperty($this, $name, $schema);
        $this->properties[] = $property;
        $this->requiredFields[] = $name;

        return $property;
    }

    /**
     * Remove a field from the required fields list.
     *
     * @internal Used by SchemaProperty::optional()
     */
    public function markOptional(string $name): void
    {
        $this->requiredFields = array_values(
            array_filter($this->requiredFields, fn (string $field): bool => $field !== $name)
        );
    }

    /**
     * Update a property's schema with a nullable version.
     *
     * @internal Used by SchemaProperty::nullable()
     */
    public function updatePropertySchema(string $name, PrismSchema $schema): void
    {
        foreach ($this->properties as $property) {
            if ($property->getName() === $name) {
                $property->setSchema($schema);

                return;
            }
        }
    }
}
