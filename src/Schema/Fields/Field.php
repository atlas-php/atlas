<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Schema\Fields;

/**
 * Abstract base for all schema field types.
 *
 * Scalar fields are immutable value objects — optional() returns a new clone.
 * ObjectField overrides optional() for its fluent builder chain.
 */
abstract class Field
{
    protected bool $required = true;

    public function __construct(
        protected readonly string $name,
        protected readonly string $description,
    ) {}

    /**
     * Return a new instance marked as optional (not required).
     */
    public function optional(): static
    {
        $clone = clone $this;
        $clone->required = false;

        return $clone;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function toSchema(): array;
}
