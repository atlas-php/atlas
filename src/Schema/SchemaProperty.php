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
 * Wrapper for a schema property with modifier methods.
 *
 * Allows marking properties as optional or nullable while maintaining
 * fluent method chaining back to the parent builder.
 *
 * @method SchemaProperty string(string $name, string $description) Add a string property.
 * @method SchemaProperty number(string $name, string $description) Add a number property.
 * @method SchemaProperty integer(string $name, string $description) Add an integer property.
 * @method SchemaProperty boolean(string $name, string $description) Add a boolean property.
 * @method SchemaProperty enum(string $name, string $description, array<int, string|int|float> $options) Add an enum property.
 * @method SchemaProperty stringArray(string $name, string $description) Add a string array property.
 * @method SchemaProperty numberArray(string $name, string $description) Add a number array property.
 * @method SchemaProperty object(string $name, string $description, callable $callback) Add a nested object property.
 * @method SchemaProperty array(string $name, string $description, callable $callback) Add an array of objects property.
 * @method ObjectSchema build() Build the final ObjectSchema.
 */
class SchemaProperty
{
    protected bool $isNullable = false;

    public function __construct(
        protected SchemaBuilder $builder,
        protected string $name,
        protected PrismSchema $schema,
    ) {}

    /**
     * Mark this property as optional (not required).
     */
    public function optional(): self
    {
        $this->builder->markOptional($this->name);

        return $this;
    }

    /**
     * Mark this property as nullable.
     *
     * Implies optional (nullable fields are automatically not required).
     */
    public function nullable(): self
    {
        $this->isNullable = true;
        $this->optional();
        $this->schema = $this->createNullableSchema($this->schema);
        $this->builder->updatePropertySchema($this->name, $this->schema);

        return $this;
    }

    /**
     * Get the property name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the schema (used when making nullable).
     */
    public function setSchema(PrismSchema $schema): void
    {
        $this->schema = $schema;
    }

    /**
     * Convert to the underlying Prism schema.
     */
    public function toPrismSchema(): PrismSchema
    {
        return $this->schema;
    }

    /**
     * Proxy method calls to the parent builder for fluent chaining.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        /** @var callable $callable */
        $callable = [$this->builder, $method];

        return $callable(...$arguments);
    }

    /**
     * Create a nullable version of a schema.
     */
    protected function createNullableSchema(PrismSchema $schema): PrismSchema
    {
        return match (true) {
            $schema instanceof StringSchema => new StringSchema(
                $schema->name,
                $schema->description,
                nullable: true,
            ),
            $schema instanceof NumberSchema => new NumberSchema(
                $schema->name,
                $schema->description,
                nullable: true,
            ),
            $schema instanceof BooleanSchema => new BooleanSchema(
                $schema->name,
                $schema->description,
                nullable: true,
            ),
            $schema instanceof EnumSchema => new EnumSchema(
                $schema->name,
                $schema->description,
                $schema->options,
                nullable: true,
            ),
            $schema instanceof ArraySchema => new ArraySchema(
                $schema->name,
                $schema->description,
                $schema->items,
                nullable: true,
            ),
            $schema instanceof ObjectSchema => new ObjectSchema(
                $schema->name,
                $schema->description,
                $schema->properties,
                $schema->requiredFields,
                $schema->allowAdditionalProperties,
                nullable: true,
            ),
            default => $schema,
        };
    }
}
