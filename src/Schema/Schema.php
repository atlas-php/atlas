<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Schema;

/**
 * Schema value object and static entry point for building JSON Schema structures.
 *
 * As a value object, it holds a name, description, and schema array.
 * As a SchemaBuilder subclass, it exposes static factories (string, object, etc.)
 * for creating field instances used in tool parameters and structured output.
 */
class Schema extends SchemaBuilder
{
    /**
     * @param  array<string, mixed>  $schemaData
     */
    public function __construct(
        protected readonly string $schemaName,
        protected readonly string $schemaDescription,
        protected readonly array $schemaData,
    ) {}

    public function name(): string
    {
        return $this->schemaName;
    }

    public function description(): string
    {
        return $this->schemaDescription;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->schemaData;
    }

    /**
     * @return array{name: string, schema: array<string, mixed>}
     */
    public function toProviderFormat(): array
    {
        return [
            'name' => $this->schemaName,
            'schema' => $this->schemaData,
        ];
    }
}
