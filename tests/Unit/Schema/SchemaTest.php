<?php

declare(strict_types=1);

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

it('returns provider format with name and schema', function () {
    $raw = ['type' => 'object'];
    $schema = new Schema('contact', 'Contact info', $raw);

    expect($schema->toProviderFormat())->toBe([
        'name' => 'contact',
        'schema' => ['type' => 'object'],
    ]);
});
