<?php

declare(strict_types=1);

use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Schema\SchemaBuilder;
use Atlasphp\Atlas\Schema\SchemaProperty;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

test('optional marks property as not required', function () {
    $schema = Schema::object('test', 'Test')
        ->string('required', 'Required field')
        ->string('optional', 'Optional field')->optional()
        ->build();

    expect($schema->requiredFields)->toBe(['required']);
});

test('nullable marks property as nullable and optional', function () {
    $schema = Schema::object('test', 'Test')
        ->string('name', 'Name')
        ->string('nickname', 'Nickname')->nullable()
        ->build();

    expect($schema->requiredFields)->toBe(['name']);
    expect($schema->properties[1]->nullable)->toBeTrue();
});

test('nullable sets nullable flag on string schema', function () {
    $schema = Schema::object('test', 'Test')
        ->string('name', 'Name')->nullable()
        ->build();

    expect($schema->properties[0])->toBeInstanceOf(StringSchema::class);
    expect($schema->properties[0]->nullable)->toBeTrue();
});

test('nullable sets nullable flag on number schema', function () {
    $schema = Schema::object('test', 'Test')
        ->number('amount', 'Amount')->nullable()
        ->build();

    expect($schema->properties[0]->nullable)->toBeTrue();
});

test('nullable sets nullable flag on boolean schema', function () {
    $schema = Schema::object('test', 'Test')
        ->boolean('active', 'Active')->nullable()
        ->build();

    expect($schema->properties[0]->nullable)->toBeTrue();
});

test('nullable sets nullable flag on enum schema', function () {
    $schema = Schema::object('test', 'Test')
        ->enum('status', 'Status', ['a', 'b'])->nullable()
        ->build();

    expect($schema->properties[0]->nullable)->toBeTrue();
});

test('nullable sets nullable flag on array schema', function () {
    $schema = Schema::object('test', 'Test')
        ->stringArray('tags', 'Tags')->nullable()
        ->build();

    expect($schema->properties[0]->nullable)->toBeTrue();
});

test('nullable sets nullable flag on object schema', function () {
    $schema = Schema::object('test', 'Test')
        ->object('nested', 'Nested', fn (SchemaBuilder $s) => $s
            ->string('field', 'Field')
        )->nullable()
        ->build();

    expect($schema->properties[0]->nullable)->toBeTrue();
});

test('chaining continues after optional', function () {
    $schema = Schema::object('test', 'Test')
        ->string('first', 'First')->optional()
        ->string('second', 'Second')
        ->build();

    expect($schema->properties)->toHaveCount(2);
    expect($schema->requiredFields)->toBe(['second']);
});

test('chaining continues after nullable', function () {
    $schema = Schema::object('test', 'Test')
        ->string('first', 'First')->nullable()
        ->string('second', 'Second')
        ->build();

    expect($schema->properties)->toHaveCount(2);
    expect($schema->requiredFields)->toBe(['second']);
});

test('property returns SchemaProperty for chaining', function () {
    $builder = Schema::object('test', 'Test');
    $property = $builder->string('name', 'Name');

    expect($property)->toBeInstanceOf(SchemaProperty::class);
});

test('property proxies build to builder', function () {
    $schema = Schema::object('test', 'Test')
        ->string('name', 'Name')
        ->build();

    expect($schema)->toBeInstanceOf(ObjectSchema::class);
});

test('property proxies type methods to builder', function () {
    $builder = Schema::object('test', 'Test');
    $property = $builder->string('first', 'First');
    $secondProperty = $property->string('second', 'Second');

    expect($secondProperty)->toBeInstanceOf(SchemaProperty::class);

    $schema = $secondProperty->build();
    expect($schema->properties)->toHaveCount(2);
});

test('multiple optional fields work correctly', function () {
    $schema = Schema::object('test', 'Test')
        ->string('required1', 'Required 1')
        ->string('optional1', 'Optional 1')->optional()
        ->string('required2', 'Required 2')
        ->string('optional2', 'Optional 2')->optional()
        ->build();

    expect($schema->requiredFields)->toBe(['required1', 'required2']);
});

test('optional and nullable can be combined but nullable takes precedence', function () {
    $schema = Schema::object('test', 'Test')
        ->string('field', 'Field')->optional()->nullable()
        ->build();

    expect($schema->requiredFields)->toBe([]);
    expect($schema->properties[0]->nullable)->toBeTrue();
});

test('getName returns the property name', function () {
    $builder = Schema::object('test', 'Test');
    $property = $builder->string('myField', 'My field');

    expect($property->getName())->toBe('myField');
});

test('toPrismSchema returns the underlying schema', function () {
    $builder = Schema::object('test', 'Test');
    $property = $builder->string('name', 'Name');

    $prismSchema = $property->toPrismSchema();

    expect($prismSchema)->toBeInstanceOf(StringSchema::class);
    expect($prismSchema->name)->toBe('name');
});
