<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Schema;

/**
 * Static factory for creating schema builders.
 *
 * Provides a fluent API for building Prism schemas without the verbosity
 * of constructing schema objects directly.
 *
 * @example
 * $schema = Schema::object('person', 'Person info')
 *     ->string('name', 'Full name')
 *     ->number('age', 'Age in years')
 *     ->build();
 */
class Schema
{
    /**
     * Create a new object schema builder.
     *
     * @param  string  $name  The schema name.
     * @param  string  $description  The schema description.
     */
    public static function object(string $name, string $description): SchemaBuilder
    {
        return new SchemaBuilder($name, $description);
    }
}
