<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Support;

use Prism\Prism\Contracts\Schema;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

/**
 * Factory for creating Prism Schema objects.
 *
 * Provides a convenient API for defining tool parameters
 * that directly produce Prism-compatible schemas.
 *
 * By default, parameters are optional. Use required: true to mark required parameters:
 *
 *   ToolParameter::string('query', 'The search query', required: true)  // Required
 *   ToolParameter::string('notes', 'Optional notes')                    // Optional
 */
final class ToolParameter
{
    /**
     * Create a string parameter.
     */
    public static function string(
        string $name,
        string $description,
        bool $required = false,
    ): StringSchema {
        return new StringSchema($name, $description, nullable: ! $required);
    }

    /**
     * Create a number parameter.
     */
    public static function number(
        string $name,
        string $description,
        bool $required = false,
    ): NumberSchema {
        return new NumberSchema($name, $description, nullable: ! $required);
    }

    /**
     * Create an integer parameter (alias for number).
     */
    public static function integer(
        string $name,
        string $description,
        bool $required = false,
    ): NumberSchema {
        return new NumberSchema($name, $description, nullable: ! $required);
    }

    /**
     * Create a boolean parameter.
     */
    public static function boolean(
        string $name,
        string $description,
        bool $required = false,
    ): BooleanSchema {
        return new BooleanSchema($name, $description, nullable: ! $required);
    }

    /**
     * Create an enum parameter.
     *
     * @param  array<int, string|int|float>  $options
     */
    public static function enum(
        string $name,
        string $description,
        array $options,
        bool $required = false,
    ): EnumSchema {
        return new EnumSchema($name, $description, $options, nullable: ! $required);
    }

    /**
     * Create an array parameter.
     */
    public static function array(
        string $name,
        string $description,
        Schema $items,
        bool $required = false,
        ?int $minItems = null,
        ?int $maxItems = null,
    ): ArraySchema {
        return new ArraySchema(
            $name,
            $description,
            $items,
            nullable: ! $required,
            minItems: $minItems,
            maxItems: $maxItems,
        );
    }

    /**
     * Create an object parameter.
     *
     * @param  array<int, Schema>  $properties
     * @param  array<int, string>  $requiredFields
     */
    public static function object(
        string $name,
        string $description,
        array $properties,
        array $requiredFields = [],
        bool $allowAdditionalProperties = false,
    ): ObjectSchema {
        return new ObjectSchema(
            $name,
            $description,
            $properties,
            $requiredFields,
            $allowAdditionalProperties,
        );
    }
}
