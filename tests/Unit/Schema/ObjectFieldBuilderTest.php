<?php

declare(strict_types=1);

use Atlasphp\Atlas\Schema\Fields\ObjectFieldBuilder;

it('builds empty object schema', function () {
    $builder = new ObjectFieldBuilder;

    expect($builder->toSchema())->toBe([
        'type' => 'object',
        'properties' => [],
    ]);
});

it('adds a string field', function () {
    $builder = (new ObjectFieldBuilder)->string('name', 'The name');

    expect($builder->toSchema()['properties']['name'])->toBe([
        'type' => 'string',
        'description' => 'The name',
    ]);
});

it('adds an integer field', function () {
    $builder = (new ObjectFieldBuilder)->integer('count', 'The count');

    expect($builder->toSchema()['properties']['count'])->toBe([
        'type' => 'integer',
        'description' => 'The count',
    ]);
});

it('adds a number field', function () {
    $builder = (new ObjectFieldBuilder)->number('price', 'The price');

    expect($builder->toSchema()['properties']['price'])->toBe([
        'type' => 'number',
        'description' => 'The price',
    ]);
});

it('adds a boolean field', function () {
    $builder = (new ObjectFieldBuilder)->boolean('active', 'Is active');

    expect($builder->toSchema()['properties']['active'])->toBe([
        'type' => 'boolean',
        'description' => 'Is active',
    ]);
});

it('adds an enum field', function () {
    $builder = (new ObjectFieldBuilder)->enum('status', 'The status', ['open', 'closed']);

    expect($builder->toSchema()['properties']['status'])->toBe([
        'type' => 'string',
        'description' => 'The status',
        'enum' => ['open', 'closed'],
    ]);
});

it('marks last field optional', function () {
    $builder = (new ObjectFieldBuilder)
        ->string('name', 'Name')
        ->string('phone', 'Phone')->optional();

    $schema = $builder->toSchema();

    expect($schema['required'])->toBe(['name']);
});

it('optional on empty builder is safe', function () {
    $builder = (new ObjectFieldBuilder)->optional();

    expect($builder->toSchema())->toBe([
        'type' => 'object',
        'properties' => [],
    ]);
});

it('chains multiple field types', function () {
    $builder = (new ObjectFieldBuilder)
        ->string('name', 'Name')
        ->integer('age', 'Age')
        ->number('score', 'Score')
        ->boolean('active', 'Active')
        ->enum('role', 'Role', ['admin', 'user']);

    $schema = $builder->toSchema();

    expect($schema['properties'])->toHaveCount(5);
    expect($schema['required'])->toBe(['name', 'age', 'score', 'active', 'role']);
});

it('all fields required by default', function () {
    $builder = (new ObjectFieldBuilder)
        ->string('a', 'A')
        ->string('b', 'B');

    expect($builder->toSchema()['required'])->toBe(['a', 'b']);
});

it('omits required array when all fields are optional', function () {
    $builder = (new ObjectFieldBuilder)
        ->string('a', 'A')->optional()
        ->string('b', 'B')->optional();

    expect($builder->toSchema())->not->toHaveKey('required');
});
