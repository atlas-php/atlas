<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Schema\Fields;

/**
 * Lightweight builder for defining object schemas within array items.
 *
 * Used by ArrayField::ofObjects() to define the shape of each array element.
 * Separate from ObjectField because array item schemas omit the top-level
 * description and intentionally omit nested object/array or build() support.
 *
 * Note: optional() marks the most recently added child field as not required,
 * matching the ObjectField convention for fluent builder chains.
 */
class ObjectFieldBuilder
{
    /** @var array<int, Field> */
    protected array $fields = [];

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

    /**
     * Mark the last added field as not required.
     *
     * Safe to delegate to Field::optional() here since this builder
     * only holds scalar fields — no nested ObjectField recursion risk.
     */
    public function optional(): static
    {
        if ($this->fields !== []) {
            $last = array_pop($this->fields);
            $this->fields[] = $last->optional();
        }

        return $this;
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
            'properties' => $properties,
        ];

        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }
}
