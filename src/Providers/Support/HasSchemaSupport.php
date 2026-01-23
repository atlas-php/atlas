<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Support;

use Atlasphp\Atlas\Schema\SchemaBuilder;
use Atlasphp\Atlas\Schema\SchemaProperty;
use Prism\Prism\Contracts\Schema;

/**
 * Trait for services that support schema configuration.
 *
 * Provides a fluent withSchema() method for attaching a schema to operations.
 * Schema is used for structured output generation. Uses the clone pattern
 * for immutability.
 */
trait HasSchemaSupport
{
    /**
     * Schema for structured output.
     */
    private ?Schema $schema = null;

    /**
     * Set the schema for structured output.
     *
     * Accepts either a built Schema, a SchemaBuilder, or a SchemaProperty
     * (which will be automatically built).
     *
     * @param  Schema|SchemaBuilder|SchemaProperty  $schema  The schema or builder defining the expected response structure.
     */
    public function withSchema(Schema|SchemaBuilder|SchemaProperty $schema): static
    {
        $clone = clone $this;

        if ($schema instanceof SchemaBuilder) {
            $clone->schema = $schema->build();
        } elseif ($schema instanceof SchemaProperty) {
            $clone->schema = $schema->build();
        } else {
            $clone->schema = $schema;
        }

        return $clone;
    }

    /**
     * Get the configured schema.
     */
    protected function getSchema(): ?Schema
    {
        return $this->schema;
    }
}
