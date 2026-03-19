<?php

declare(strict_types=1);

use Atlasphp\Atlas\Schema\Fields\ArrayField;

it('produces ofStrings schema', function () {
    expect(ArrayField::ofStrings('tags', 'Tags')->toSchema())->toBe([
        'type' => 'array',
        'description' => 'Tags',
        'items' => ['type' => 'string'],
    ]);
});

it('produces ofNumbers schema', function () {
    expect(ArrayField::ofNumbers('ids', 'IDs')->toSchema())->toBe([
        'type' => 'array',
        'description' => 'IDs',
        'items' => ['type' => 'number'],
    ]);
});

it('produces ofObjects schema', function () {
    $field = ArrayField::ofObjects('items', 'Line items', fn ($s) => $s
        ->string('name', 'Name')
        ->integer('qty', 'Qty')
    );

    expect($field->toSchema())->toBe([
        'type' => 'array',
        'description' => 'Line items',
        'items' => [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Name'],
                'qty' => ['type' => 'integer', 'description' => 'Qty'],
            ],
            'required' => ['name', 'qty'],
        ],
    ]);
});

it('produces ofObjects schema with optional fields', function () {
    $field = ArrayField::ofObjects('items', 'Line items', fn ($s) => $s
        ->string('name', 'Name')
        ->integer('qty', 'Qty')->optional()
    );

    $schema = $field->toSchema();

    expect($schema['items']['required'])->toBe(['name']);
});

it('supports optional array field', function () {
    expect(ArrayField::ofStrings('tags', 'Tags')->optional()->isRequired())->toBeFalse();
});
