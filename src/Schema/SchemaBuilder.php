<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Schema;

use Atlasphp\Atlas\Schema\Fields\ArrayField;
use Atlasphp\Atlas\Schema\Fields\BooleanField;
use Atlasphp\Atlas\Schema\Fields\EnumField;
use Atlasphp\Atlas\Schema\Fields\IntegerField;
use Atlasphp\Atlas\Schema\Fields\NumberField;
use Atlasphp\Atlas\Schema\Fields\ObjectField;
use Atlasphp\Atlas\Schema\Fields\ObjectFieldBuilder;
use Atlasphp\Atlas\Schema\Fields\StringField;
use Closure;

/**
 * Static factory for creating schema field instances.
 *
 * Provides a clean entry point for building JSON Schema structures from PHP.
 * Extended by Schema to expose these factories as Schema::string(), Schema::object(), etc.
 */
abstract class SchemaBuilder
{
    public static function string(string $name, string $description): StringField
    {
        return new StringField($name, $description);
    }

    public static function integer(string $name, string $description): IntegerField
    {
        return new IntegerField($name, $description);
    }

    public static function number(string $name, string $description): NumberField
    {
        return new NumberField($name, $description);
    }

    public static function boolean(string $name, string $description): BooleanField
    {
        return new BooleanField($name, $description);
    }

    /**
     * @param  array<int, string>  $options
     */
    public static function enum(string $name, string $description, array $options): EnumField
    {
        return new EnumField($name, $description, $options);
    }

    public static function stringArray(string $name, string $description): ArrayField
    {
        return ArrayField::ofStrings($name, $description);
    }

    public static function numberArray(string $name, string $description): ArrayField
    {
        return ArrayField::ofNumbers($name, $description);
    }

    /**
     * @param  Closure(ObjectFieldBuilder): void  $callback
     */
    public static function array(string $name, string $description, Closure $callback): ArrayField
    {
        return ArrayField::ofObjects($name, $description, $callback);
    }

    public static function object(string $name, string $description): ObjectField
    {
        return new ObjectField($name, $description);
    }
}
