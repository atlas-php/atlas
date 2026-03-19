<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Schema\Fields;

use Closure;

/**
 * An array field that produces a JSON Schema array type with typed items.
 *
 * Use the static factories to create arrays of strings, numbers, or objects.
 */
class ArrayField extends Field
{
    /**
     * @param  array<string, mixed>  $items
     */
    public function __construct(
        string $name,
        string $description,
        protected readonly array $items,
    ) {
        parent::__construct($name, $description);
    }

    public static function ofStrings(string $name, string $description): self
    {
        return new self($name, $description, ['type' => 'string']);
    }

    public static function ofNumbers(string $name, string $description): self
    {
        return new self($name, $description, ['type' => 'number']);
    }

    /**
     * @param  Closure(ObjectFieldBuilder): ObjectFieldBuilder  $callback
     */
    public static function ofObjects(string $name, string $description, Closure $callback): self
    {
        $builder = new ObjectFieldBuilder;
        $callback($builder);

        return new self($name, $description, $builder->toSchema());
    }

    /**
     * @return array<string, mixed>
     */
    public function toSchema(): array
    {
        return [
            'type' => 'array',
            'description' => $this->description,
            'items' => $this->items,
        ];
    }
}
