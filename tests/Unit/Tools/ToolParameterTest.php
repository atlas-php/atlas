<?php

declare(strict_types=1);

use Atlasphp\Atlas\Tools\Support\ToolParameter;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

test('it creates string schema', function () {
    $schema = ToolParameter::string('name', 'The name');

    expect($schema)->toBeInstanceOf(StringSchema::class);
    expect($schema->name)->toBe('name');
    expect($schema->description)->toBe('The name');
});

test('it creates optional string schema by default', function () {
    $schema = ToolParameter::string('name', 'The name');

    expect($schema)->toBeInstanceOf(StringSchema::class);
    expect($schema->nullable)->toBeTrue();
});

test('it creates required string schema', function () {
    $schema = ToolParameter::string('name', 'The name', required: true);

    expect($schema->nullable)->toBeFalse();
});

test('it creates number schema', function () {
    $schema = ToolParameter::number('count', 'The count');

    expect($schema)->toBeInstanceOf(NumberSchema::class);
    expect($schema->name)->toBe('count');
    expect($schema->description)->toBe('The count');
});

test('it creates integer schema as number', function () {
    $schema = ToolParameter::integer('age', 'The age');

    expect($schema)->toBeInstanceOf(NumberSchema::class);
    expect($schema->name)->toBe('age');
    expect($schema->description)->toBe('The age');
});

test('it creates boolean schema', function () {
    $schema = ToolParameter::boolean('enabled', 'Whether enabled');

    expect($schema)->toBeInstanceOf(BooleanSchema::class);
    expect($schema->name)->toBe('enabled');
    expect($schema->description)->toBe('Whether enabled');
});

test('it creates enum schema', function () {
    $schema = ToolParameter::enum('status', 'The status', ['active', 'inactive', 'pending']);

    expect($schema)->toBeInstanceOf(EnumSchema::class);
    expect($schema->name)->toBe('status');
    expect($schema->description)->toBe('The status');
});

test('it creates array schema', function () {
    $itemSchema = new StringSchema('item', 'An item');
    $schema = ToolParameter::array('items', 'The items', $itemSchema);

    expect($schema)->toBeInstanceOf(ArraySchema::class);
    expect($schema->name)->toBe('items');
    expect($schema->description)->toBe('The items');
});

test('it creates object schema', function () {
    $properties = [
        new StringSchema('name', 'The name'),
        new NumberSchema('age', 'The age'),
    ];

    $schema = ToolParameter::object(
        'person',
        'A person object',
        $properties,
        requiredFields: ['name'],
    );

    expect($schema)->toBeInstanceOf(ObjectSchema::class);
    expect($schema->name)->toBe('person');
    expect($schema->description)->toBe('A person object');
});

test('it creates object schema with additional properties allowed', function () {
    $properties = [
        new StringSchema('name', 'The name'),
    ];

    $schema = ToolParameter::object(
        'flexible',
        'A flexible object',
        $properties,
        allowAdditionalProperties: true,
    );

    expect($schema)->toBeInstanceOf(ObjectSchema::class);
});

test('it creates required enum schema', function () {
    $schema = ToolParameter::enum('status', 'The status', ['active', 'inactive'], required: true);

    expect($schema)->toBeInstanceOf(EnumSchema::class);
    expect($schema->nullable)->toBeFalse();
});

test('it creates optional enum schema by default', function () {
    $schema = ToolParameter::enum('status', 'The status', ['active', 'inactive']);

    expect($schema)->toBeInstanceOf(EnumSchema::class);
    expect($schema->nullable)->toBeTrue();
});

test('it creates required array schema', function () {
    $itemSchema = new StringSchema('item', 'An item');
    $schema = ToolParameter::array('items', 'The items', $itemSchema, required: true);

    expect($schema)->toBeInstanceOf(ArraySchema::class);
    expect($schema->nullable)->toBeFalse();
});

test('it creates optional array schema by default', function () {
    $itemSchema = new StringSchema('item', 'An item');
    $schema = ToolParameter::array('items', 'The items', $itemSchema);

    expect($schema)->toBeInstanceOf(ArraySchema::class);
    expect($schema->nullable)->toBeTrue();
});

test('it creates array schema with minItems and maxItems', function () {
    $itemSchema = new StringSchema('item', 'An item');
    $schema = ToolParameter::array('items', 'The items', $itemSchema, minItems: 1, maxItems: 10);

    expect($schema)->toBeInstanceOf(ArraySchema::class);
    expect($schema->minItems)->toBe(1);
    expect($schema->maxItems)->toBe(10);
});
