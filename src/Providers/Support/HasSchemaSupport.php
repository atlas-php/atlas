<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Support;

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
     * @param  Schema  $schema  The schema defining the expected response structure.
     */
    public function withSchema(Schema $schema): static
    {
        $clone = clone $this;
        $clone->schema = $schema;

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
