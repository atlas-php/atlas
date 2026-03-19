<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Schema;

/**
 * Minimal schema value object for structured output and tool parameters.
 *
 * This is a stub that Phase 3 replaces with the full fluent builder.
 */
class Schema
{
    /**
     * @param  array<string, mixed>  $schema
     */
    public function __construct(
        protected readonly string $name,
        protected readonly string $description,
        protected readonly array $schema,
    ) {}

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
    public function toArray(): array
    {
        return $this->schema;
    }

    /**
     * @return array{name: string, schema: array<string, mixed>}
     */
    public function toProviderFormat(): array
    {
        return [
            'name' => $this->name,
            'schema' => $this->schema,
        ];
    }
}
