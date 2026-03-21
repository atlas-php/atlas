<?php

declare(strict_types=1);

use Atlasphp\Atlas\Schema\Fields\ObjectField;
use Atlasphp\Atlas\Schema\Fields\StringField;
use Atlasphp\Atlas\Schema\Schema;

it('stores name, description, and schema', function () {
    $schema = new Schema('contact', 'Contact info', ['type' => 'object']);

    expect($schema->name())->toBe('contact');
    expect($schema->description())->toBe('Contact info');
});

it('returns the raw schema from toArray', function () {
    $raw = ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]];
    $schema = new Schema('contact', 'Contact info', $raw);

    expect($schema->toArray())->toBe($raw);
});

it('returns provider format with name and schema without description', function () {
    $raw = ['type' => 'object'];
    $schema = new Schema('contact', 'Contact info', $raw);

    $format = $schema->toProviderFormat();

    expect($format)->toBe([
        'name' => 'contact',
        'schema' => ['type' => 'object'],
    ]);
    expect($format)->not->toHaveKey('description');
});

it('inherits static factory methods from SchemaBuilder', function () {
    expect(Schema::string('x', 'y'))->toBeInstanceOf(StringField::class);
    expect(Schema::object('x', 'y'))->toBeInstanceOf(ObjectField::class);
});

it('supports tool parameters pattern', function () {
    $params = [
        Schema::string('query', 'The search query'),
        Schema::integer('limit', 'Max results')->optional(),
    ];

    expect($params[0]->isRequired())->toBeTrue();
    expect($params[0]->toSchema())->toBe(['type' => 'string', 'description' => 'The search query']);
    expect($params[1]->isRequired())->toBeFalse();
    expect($params[1]->toSchema())->toBe(['type' => 'integer', 'description' => 'Max results']);
});

it('supports structured output pattern', function () {
    $schema = Schema::object('contact', 'Contact details')
        ->string('name', 'Full name')
        ->string('email', 'Email address')
        ->string('phone', 'Phone number')->optional()
        ->build();

    expect($schema)->toBeInstanceOf(Schema::class);
    expect($schema->name())->toBe('contact');

    $data = $schema->toArray();

    expect($data['properties'])->toHaveCount(3);
    expect($data['required'])->toBe(['name', 'email']);
});
