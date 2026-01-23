<?php

declare(strict_types=1);

use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Schema\SchemaBuilder;

test('object returns a schema builder', function () {
    $builder = Schema::object('test', 'Test description');

    expect($builder)->toBeInstanceOf(SchemaBuilder::class);
});

test('object passes name and description to builder', function () {
    $schema = Schema::object('person', 'Person information')
        ->string('name', 'Full name')
        ->build();

    expect($schema->name)->toBe('person');
    expect($schema->description)->toBe('Person information');
});
