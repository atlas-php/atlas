<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Schema\Fields;

use Atlasphp\Atlas\Schema\Schema;
use Closure;

/**
 * An object field that produces a JSON Schema object type with nested properties.
 *
 * Supports fluent field definition and can be used as a builder via build().
 */
class ObjectField extends Field
{
    /** @var array<int, Field> */
    protected array $fields = [];

    /**
     * @param  (Closure(ObjectField): void)|null  $callback
     */
    public function __construct(
        string $name,
        string $description,
        ?Closure $callback = null,
    ) {
        parent::__construct($name, $description);

        if ($callback !== null) {
            $callback($this);
        }
    }

    public function string(string $name, string $description): static
    {
        $this->fields[] = new StringField($name, $description);

        return $this;
    }

    public function integer(string $name, string $description): static
    {
        $this->fields[] = new IntegerField($name, $description);

        return $this;
    }

    public function number(string $name, string $description): static
    {
        $this->fields[] = new NumberField($name, $description);

        return $this;
    }

    public function boolean(string $name, string $description): static
    {
        $this->fields[] = new BooleanField($name, $description);

        return $this;
    }

    /**
     * @param  array<int, string>  $options
     */
    public function enum(string $name, string $description, array $options): static
    {
        $this->fields[] = new EnumField($name, $description, $options);

        return $this;
    }

    public function stringArray(string $name, string $description): static
    {
        $this->fields[] = ArrayField::ofStrings($name, $description);

        return $this;
    }

    public function numberArray(string $name, string $description): static
    {
        $this->fields[] = ArrayField::ofNumbers($name, $description);

        return $this;
    }

    /**
     * @param  Closure(ObjectFieldBuilder): ObjectFieldBuilder  $callback
     */
    public function array(string $name, string $description, Closure $callback): static
    {
        $this->fields[] = ArrayField::ofObjects($name, $description, $callback);

        return $this;
    }

    /**
     * @param  (Closure(ObjectField): void)|null  $callback
     */
    public function object(string $name, string $description, ?Closure $callback = null): static
    {
        $this->fields[] = new ObjectField($name, $description, $callback);

        return $this;
    }

    /**
     * Mark the last added field as not required.
     *
     * In the fluent builder chain, this targets the most recently added child field
     * by cloning it with required=false. This avoids delegating to the child's own
     * optional() override, which would recurse incorrectly for nested ObjectFields.
     */
    public function optional(): static
    {
        if ($this->fields !== []) {
            $last = array_pop($this->fields);
            $clone = clone $last;
            $clone->required = false;
            $this->fields[] = $clone;
        }

        return $this;
    }

    /**
     * @return array<int, Field>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSchema(): array
    {
        $properties = [];
        $required = [];

        foreach ($this->fields as $field) {
            $properties[$field->name()] = $field->toSchema();

            if ($field->isRequired()) {
                $required[] = $field->name();
            }
        }

        $schema = [
            'type' => 'object',
            'description' => $this->description,
            'properties' => $properties,
        ];

        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * Terminal method — produce a Schema value object from this builder.
     */
    public function build(): Schema
    {
        return new Schema($this->name, $this->description, $this->toSchema());
    }
}
