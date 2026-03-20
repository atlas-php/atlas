<?php

declare(strict_types=1);

use Atlasphp\Atlas\Schema\Fields\ObjectField;

it('adds a number field', function () {
    $field = (new ObjectField('obj', 'An object'))
        ->number('amount', 'The amount');

    $schema = $field->toSchema();

    expect($schema['properties']['amount'])->toBe([
        'type' => 'number',
        'description' => 'The amount',
    ]);
});

it('adds a boolean field', function () {
    $field = (new ObjectField('obj', 'An object'))
        ->boolean('active', 'Is active');

    $schema = $field->toSchema();

    expect($schema['properties']['active'])->toBe([
        'type' => 'boolean',
        'description' => 'Is active',
    ]);
});

it('adds an enum field', function () {
    $field = (new ObjectField('obj', 'An object'))
        ->enum('status', 'The status', ['open', 'closed']);

    $schema = $field->toSchema();

    expect($schema['properties']['status'])->toBe([
        'type' => 'string',
        'description' => 'The status',
        'enum' => ['open', 'closed'],
    ]);
});

it('adds a number array field', function () {
    $field = (new ObjectField('obj', 'An object'))
        ->numberArray('scores', 'The scores');

    $schema = $field->toSchema();

    expect($schema['properties']['scores'])->toBe([
        'type' => 'array',
        'description' => 'The scores',
        'items' => ['type' => 'number'],
    ]);
});

it('nested object without callback creates empty object', function () {
    $field = (new ObjectField('obj', 'An object'))
        ->object('nested', 'A nested object');

    $schema = $field->toSchema();

    expect($schema['properties']['nested'])->toBe([
        'type' => 'object',
        'description' => 'A nested object',
        'properties' => [],
    ]);
});

it('optional on empty fields is safe', function () {
    $field = (new ObjectField('obj', 'An object'))->optional();

    expect($field->toSchema())->toBe([
        'type' => 'object',
        'description' => 'An object',
        'properties' => [],
    ]);
});

it('omits required when all fields are optional', function () {
    $field = (new ObjectField('obj', 'An object'))
        ->string('a', 'A')->optional()
        ->string('b', 'B')->optional();

    expect($field->toSchema())->not->toHaveKey('required');
});
