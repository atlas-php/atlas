<?php

declare(strict_types=1);

use Atlasphp\Atlas\Schema\Fields\ObjectField;
use Atlasphp\Atlas\Schema\Schema;

it('produces empty object schema', function () {
    expect((new ObjectField('obj', 'An object'))->toSchema())->toBe([
        'type' => 'object',
        'description' => 'An object',
        'properties' => [],
    ]);
});

it('adds fields with correct properties and required', function () {
    $field = (new ObjectField('obj', 'An object'))
        ->string('name', 'Name')
        ->integer('age', 'Age');

    $schema = $field->toSchema();

    expect($schema['properties'])->toBe([
        'name' => ['type' => 'string', 'description' => 'Name'],
        'age' => ['type' => 'integer', 'description' => 'Age'],
    ]);
    expect($schema['required'])->toBe(['name', 'age']);
});

it('marks last field optional in fluent chain', function () {
    $field = (new ObjectField('obj', 'An object'))
        ->string('name', 'Name')
        ->string('phone', 'Phone')->optional();

    $schema = $field->toSchema();

    expect($schema['required'])->toBe(['name']);
});

it('optional only affects the last field', function () {
    $field = (new ObjectField('obj', 'An object'))
        ->string('a', '...')
        ->string('b', '...')->optional()
        ->string('c', '...');

    $schema = $field->toSchema();

    expect($schema['required'])->toBe(['a', 'c']);
});

it('supports nested objects', function () {
    $field = (new ObjectField('obj', 'An object'))
        ->object('address', 'Address', fn ($s) => $s->string('city', 'City'));

    $schema = $field->toSchema();

    expect($schema['properties']['address'])->toBe([
        'type' => 'object',
        'description' => 'Address',
        'properties' => [
            'city' => ['type' => 'string', 'description' => 'City'],
        ],
        'required' => ['city'],
    ]);
});

it('supports nested string arrays', function () {
    $field = (new ObjectField('obj', 'An object'))
        ->stringArray('tags', 'Tags');

    $schema = $field->toSchema();

    expect($schema['properties']['tags'])->toBe([
        'type' => 'array',
        'description' => 'Tags',
        'items' => ['type' => 'string'],
    ]);
});

it('supports nested object arrays', function () {
    $field = (new ObjectField('obj', 'An object'))
        ->array('items', 'Items', fn ($s) => $s->string('name', 'N'));

    $schema = $field->toSchema();

    expect($schema['properties']['items'])->toBe([
        'type' => 'array',
        'description' => 'Items',
        'items' => [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'N'],
            ],
            'required' => ['name'],
        ],
    ]);
});

it('builds a Schema from ObjectField', function () {
    $schema = (new ObjectField('contact', 'Contact info'))
        ->string('name', 'Name')
        ->build();

    expect($schema)->toBeInstanceOf(Schema::class);
    expect($schema->name())->toBe('contact');
    expect($schema->description())->toBe('Contact info');
    expect($schema->toArray())->toBe([
        'type' => 'object',
        'description' => 'Contact info',
        'properties' => [
            'name' => ['type' => 'string', 'description' => 'Name'],
        ],
        'required' => ['name'],
    ]);
});

it('round-trips through Schema::object and build', function () {
    $schema = Schema::object('x', 'y')
        ->string('a', 'b')
        ->build();

    expect($schema->toArray())->toBe([
        'type' => 'object',
        'description' => 'y',
        'properties' => [
            'a' => ['type' => 'string', 'description' => 'b'],
        ],
        'required' => ['a'],
    ]);
});

it('returns fields array', function () {
    $field = (new ObjectField('obj', 'An object'))
        ->string('name', 'Name')
        ->integer('age', 'Age');

    expect($field->fields())->toHaveCount(2);
});

it('accepts callback in constructor', function () {
    $field = new ObjectField('x', 'y', fn ($o) => $o->string('a', 'b'));

    expect($field->fields())->toHaveCount(1);
    expect($field->fields()[0]->name())->toBe('a');
});

it('optional on nested object marks the object not required, not its children', function () {
    $field = (new ObjectField('root', 'Root'))
        ->object('address', 'Address', fn ($s) => $s->string('city', 'City'))
        ->optional();

    $schema = $field->toSchema();

    // 'address' should not be in required
    expect($schema)->not->toHaveKey('required');

    // 'city' inside address should still be required
    expect($schema['properties']['address']['required'])->toBe(['city']);
});

it('toProviderFormat works via builder round-trip', function () {
    $schema = Schema::object('contact', 'Contact')
        ->string('name', 'Name')
        ->build();

    expect($schema->toProviderFormat())->toBe([
        'name' => 'contact',
        'schema' => [
            'type' => 'object',
            'description' => 'Contact',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Name'],
            ],
            'required' => ['name'],
        ],
    ]);
});
