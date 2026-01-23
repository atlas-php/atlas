<?php

declare(strict_types=1);

use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Schema\SchemaBuilder;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

test('string creates a string property', function () {
    $schema = Schema::object('test', 'Test')
        ->string('name', 'The name')
        ->build();

    expect($schema->properties)->toHaveCount(1);
    expect($schema->properties[0])->toBeInstanceOf(StringSchema::class);
    expect($schema->properties[0]->name)->toBe('name');
    expect($schema->properties[0]->description)->toBe('The name');
});

test('number creates a number property', function () {
    $schema = Schema::object('test', 'Test')
        ->number('amount', 'The amount')
        ->build();

    expect($schema->properties[0])->toBeInstanceOf(NumberSchema::class);
    expect($schema->properties[0]->name)->toBe('amount');
});

test('integer creates a number property', function () {
    $schema = Schema::object('test', 'Test')
        ->integer('count', 'The count')
        ->build();

    expect($schema->properties[0])->toBeInstanceOf(NumberSchema::class);
    expect($schema->properties[0]->name)->toBe('count');
});

test('boolean creates a boolean property', function () {
    $schema = Schema::object('test', 'Test')
        ->boolean('active', 'Is active')
        ->build();

    expect($schema->properties[0])->toBeInstanceOf(BooleanSchema::class);
    expect($schema->properties[0]->name)->toBe('active');
});

test('enum creates an enum property', function () {
    $schema = Schema::object('test', 'Test')
        ->enum('status', 'The status', ['pending', 'approved', 'rejected'])
        ->build();

    expect($schema->properties[0])->toBeInstanceOf(EnumSchema::class);
    expect($schema->properties[0]->name)->toBe('status');
    expect($schema->properties[0]->options)->toBe(['pending', 'approved', 'rejected']);
});

test('stringArray creates an array of strings', function () {
    $schema = Schema::object('test', 'Test')
        ->stringArray('tags', 'List of tags')
        ->build();

    expect($schema->properties[0])->toBeInstanceOf(ArraySchema::class);
    expect($schema->properties[0]->name)->toBe('tags');
    expect($schema->properties[0]->items)->toBeInstanceOf(StringSchema::class);
});

test('numberArray creates an array of numbers', function () {
    $schema = Schema::object('test', 'Test')
        ->numberArray('scores', 'List of scores')
        ->build();

    expect($schema->properties[0])->toBeInstanceOf(ArraySchema::class);
    expect($schema->properties[0]->name)->toBe('scores');
    expect($schema->properties[0]->items)->toBeInstanceOf(NumberSchema::class);
});

test('object creates a nested object schema', function () {
    $schema = Schema::object('test', 'Test')
        ->object('address', 'Address info', fn (SchemaBuilder $s) => $s
            ->string('street', 'Street')
            ->string('city', 'City')
        )
        ->build();

    expect($schema->properties[0])->toBeInstanceOf(ObjectSchema::class);
    expect($schema->properties[0]->name)->toBe('address');
    expect($schema->properties[0]->properties)->toHaveCount(2);
});

test('array creates an array of objects', function () {
    $schema = Schema::object('test', 'Test')
        ->array('items', 'Order items', fn (SchemaBuilder $s) => $s
            ->string('name', 'Item name')
            ->number('price', 'Item price')
        )
        ->build();

    expect($schema->properties[0])->toBeInstanceOf(ArraySchema::class);
    expect($schema->properties[0]->name)->toBe('items');
    expect($schema->properties[0]->items)->toBeInstanceOf(ObjectSchema::class);
    expect($schema->properties[0]->items->properties)->toHaveCount(2);
});

test('all properties are required by default', function () {
    $schema = Schema::object('test', 'Test')
        ->string('name', 'Name')
        ->number('age', 'Age')
        ->boolean('active', 'Active')
        ->build();

    expect($schema->requiredFields)->toBe(['name', 'age', 'active']);
});

test('optional removes field from required', function () {
    $schema = Schema::object('test', 'Test')
        ->string('name', 'Name')
        ->string('email', 'Email')->optional()
        ->build();

    expect($schema->requiredFields)->toBe(['name']);
});

test('multiple properties can be chained', function () {
    $schema = Schema::object('person', 'Person')
        ->string('name', 'Name')
        ->number('age', 'Age')
        ->string('email', 'Email')
        ->build();

    expect($schema->properties)->toHaveCount(3);
    expect($schema->requiredFields)->toHaveCount(3);
});

test('build returns a valid ObjectSchema', function () {
    $schema = Schema::object('test', 'Test schema')
        ->string('field', 'A field')
        ->build();

    expect($schema)->toBeInstanceOf(ObjectSchema::class);
    expect($schema->name)->toBe('test');
    expect($schema->description)->toBe('Test schema');
});

test('nested object properties are required in nested schema', function () {
    $schema = Schema::object('test', 'Test')
        ->object('nested', 'Nested', fn (SchemaBuilder $s) => $s
            ->string('required_field', 'Required')
            ->string('optional_field', 'Optional')->optional()
        )
        ->build();

    /** @var ObjectSchema $nestedSchema */
    $nestedSchema = $schema->properties[0];
    expect($nestedSchema->requiredFields)->toBe(['required_field']);
});

test('complex nested schema builds correctly', function () {
    $schema = Schema::object('order', 'Order')
        ->string('id', 'Order ID')
        ->object('customer', 'Customer', fn (SchemaBuilder $s) => $s
            ->string('name', 'Name')
            ->string('email', 'Email')->optional()
        )
        ->array('items', 'Items', fn (SchemaBuilder $s) => $s
            ->string('name', 'Item name')
            ->number('quantity', 'Quantity')
            ->number('price', 'Price')->optional()
        )
        ->build();

    expect($schema->properties)->toHaveCount(3);
    expect($schema->requiredFields)->toBe(['id', 'customer', 'items']);

    /** @var ObjectSchema $customer */
    $customer = $schema->properties[1];
    expect($customer->requiredFields)->toBe(['name']);

    /** @var ArraySchema $items */
    $items = $schema->properties[2];

    /** @var ObjectSchema $itemSchema */
    $itemSchema = $items->items;
    expect($itemSchema->requiredFields)->toBe(['name', 'quantity']);
});
