<?php

declare(strict_types=1);

use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Schema\StrictSchema;

it('adds additionalProperties:false and requires all keys on a flat object', function () {
    $schema = Schema::object('person', 'A person')
        ->string('name', 'Name')
        ->integer('age', 'Age')
        ->build()
        ->toArray();

    $normalized = StrictSchema::normalize($schema);

    expect($normalized['additionalProperties'])->toBeFalse();
    expect($normalized['required'])->toBe(['name', 'age']);
});

it('makes optional fields nullable but still required', function () {
    $schema = Schema::object('contact', 'Contact')
        ->string('name', 'Name')
        ->string('phone', 'Phone')->optional()
        ->build()
        ->toArray();

    $normalized = StrictSchema::normalize($schema);

    expect($normalized['required'])->toBe(['name', 'phone']);
    expect($normalized['properties']['name']['type'])->toBe('string');
    expect($normalized['properties']['phone']['type'])->toBe(['string', 'null']);
});

it('normalizes nested objects recursively', function () {
    $schema = Schema::object('order', 'Order')
        ->string('id', 'ID')
        ->object('customer', 'Customer', function ($obj) {
            $obj->string('name', 'Name')
                ->string('email', 'Email')->optional();
        })
        ->build()
        ->toArray();

    $normalized = StrictSchema::normalize($schema);
    $customer = $normalized['properties']['customer'];

    expect($customer['additionalProperties'])->toBeFalse();
    expect($customer['required'])->toBe(['name', 'email']);
    expect($customer['properties']['email']['type'])->toBe(['string', 'null']);
});

it('normalizes object items inside arrays', function () {
    $schema = Schema::object('cart', 'Cart')
        ->array('items', 'Line items', function ($builder) {
            $builder->string('sku', 'SKU')
                ->integer('qty', 'Quantity');
        })
        ->build()
        ->toArray();

    $normalized = StrictSchema::normalize($schema);
    $items = $normalized['properties']['items']['items'];

    expect($items['additionalProperties'])->toBeFalse();
    expect($items['required'])->toBe(['sku', 'qty']);
});

it('leaves scalar arrays untouched aside from requiring the parent key', function () {
    $schema = Schema::object('post', 'Post')
        ->stringArray('tags', 'Tags')
        ->build()
        ->toArray();

    $normalized = StrictSchema::normalize($schema);

    expect($normalized['required'])->toBe(['tags']);
    expect($normalized['properties']['tags']['type'])->toBe('array');
    expect($normalized['properties']['tags']['items']['type'])->toBe('string');
});

it('is idempotent on an already-strict schema', function () {
    $raw = [
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'name' => ['type' => 'string'],
            'phone' => ['type' => ['string', 'null']],
        ],
        'required' => ['name', 'phone'],
    ];

    $once = StrictSchema::normalize($raw);
    $twice = StrictSchema::normalize($once);

    expect($once)->toBe($twice);
    expect($once['properties']['phone']['type'])->toBe(['string', 'null']);
    expect($once['required'])->toBe(['name', 'phone']);
});

it('does not duplicate null when an optional field is already nullable', function () {
    $raw = [
        'type' => 'object',
        'properties' => [
            'phone' => ['type' => ['string', 'null']],
        ],
    ];

    $normalized = StrictSchema::normalize($raw);

    expect($normalized['properties']['phone']['type'])->toBe(['string', 'null']);
});

it('leaves a scalar node without type:object or properties untouched', function () {
    $raw = ['type' => 'string', 'description' => 'just a string'];

    expect(StrictSchema::normalize($raw))->toBe($raw);
});

it('treats a node with properties but no type key as an object', function () {
    $raw = ['properties' => ['x' => ['type' => 'string']]];

    $normalized = StrictSchema::normalize($raw);

    expect($normalized['additionalProperties'])->toBeFalse();
    expect($normalized['required'])->toBe(['x']);
});

it('handles an object node that has no properties key', function () {
    $raw = ['type' => 'object'];

    $normalized = StrictSchema::normalize($raw);

    expect($normalized['additionalProperties'])->toBeFalse();
    expect($normalized['required'])->toBe([]);
    expect($normalized['properties'])->toBe([]);
});

it('ignores a non-array required value', function () {
    $raw = [
        'type' => 'object',
        'properties' => ['x' => ['type' => 'string']],
        'required' => 'not-an-array',
    ];

    $normalized = StrictSchema::normalize($raw);

    // required treated as empty -> x is optional -> nullable, and required rebuilt.
    expect($normalized['required'])->toBe(['x']);
    expect($normalized['properties']['x']['type'])->toBe(['string', 'null']);
});

it('skips non-array property entries while still requiring their key', function () {
    $raw = [
        'type' => 'object',
        'properties' => [
            'bad' => 'not-an-array',
            'good' => ['type' => 'string'],
        ],
    ];

    $normalized = StrictSchema::normalize($raw);

    expect($normalized['properties']['bad'])->toBe('not-an-array');
    expect($normalized['properties']['good']['type'])->toBe(['string', 'null']);
    expect($normalized['required'])->toBe(['bad', 'good']);
});

it('leaves a non-array items value untouched', function () {
    $raw = ['type' => 'array', 'items' => 'not-an-array'];

    expect(StrictSchema::normalize($raw))->toBe($raw);
});

it('appends null to an optional field whose type is already a union', function () {
    $raw = [
        'type' => 'object',
        'properties' => [
            'x' => ['type' => ['string', 'integer']],
        ],
    ];

    $normalized = StrictSchema::normalize($raw);

    expect($normalized['properties']['x']['type'])->toBe(['string', 'integer', 'null']);
});

it('leaves an optional field with no type key unchanged aside from requiring it', function () {
    $raw = [
        'type' => 'object',
        'properties' => [
            'x' => ['description' => 'no type'],
        ],
    ];

    $normalized = StrictSchema::normalize($raw);

    expect($normalized['properties']['x'])->toBe(['description' => 'no type']);
    expect($normalized['required'])->toBe(['x']);
});

it('does not alter an optional field already typed as the null string', function () {
    $raw = [
        'type' => 'object',
        'properties' => [
            'x' => ['type' => 'null'],
        ],
    ];

    $normalized = StrictSchema::normalize($raw);

    expect($normalized['properties']['x']['type'])->toBe('null');
    expect($normalized['required'])->toBe(['x']);
});
