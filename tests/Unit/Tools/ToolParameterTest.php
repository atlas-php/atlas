<?php

declare(strict_types=1);

use Atlasphp\Atlas\Tools\Support\ToolParameter;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

test('it creates string parameter', function () {
    $param = ToolParameter::string('name', 'The name', true, 'default');

    expect($param->name)->toBe('name');
    expect($param->type)->toBe('string');
    expect($param->description)->toBe('The name');
    expect($param->required)->toBeTrue();
    expect($param->default)->toBe('default');
});

test('it creates integer parameter', function () {
    $param = ToolParameter::integer('count', 'The count', false, 10);

    expect($param->name)->toBe('count');
    expect($param->type)->toBe('integer');
    expect($param->required)->toBeFalse();
    expect($param->default)->toBe(10);
});

test('it creates number parameter', function () {
    $param = ToolParameter::number('price', 'The price');

    expect($param->type)->toBe('number');
});

test('it creates boolean parameter', function () {
    $param = ToolParameter::boolean('active', 'Is active', false, true);

    expect($param->type)->toBe('boolean');
    expect($param->default)->toBeTrue();
});

test('it creates enum parameter', function () {
    $param = ToolParameter::enum('status', 'The status', ['active', 'inactive']);

    expect($param->type)->toBe('string');
    expect($param->enum)->toBe(['active', 'inactive']);
});

test('it creates array parameter', function () {
    $items = ['type' => 'string'];
    $param = ToolParameter::array('tags', 'The tags', $items);

    expect($param->type)->toBe('array');
    expect($param->items)->toBe($items);
});

test('it creates object parameter', function () {
    $properties = [
        ToolParameter::string('name', 'The name'),
        ToolParameter::integer('age', 'The age'),
    ];
    $param = ToolParameter::object('user', 'The user', $properties);

    expect($param->type)->toBe('object');
    expect($param->properties)->toBe($properties);
});

test('it converts to schema array', function () {
    $param = ToolParameter::string('name', 'The name');

    $schema = $param->toSchema();

    expect($schema)->toBe([
        'type' => 'string',
        'description' => 'The name',
    ]);
});

test('it includes enum in schema', function () {
    $param = ToolParameter::enum('status', 'Status', ['a', 'b']);

    $schema = $param->toSchema();

    expect($schema['enum'])->toBe(['a', 'b']);
});

test('it includes default in schema', function () {
    $param = ToolParameter::string('name', 'Name', true, 'default');

    $schema = $param->toSchema();

    expect($schema['default'])->toBe('default');
});

test('it converts to Prism StringSchema', function () {
    $param = ToolParameter::string('name', 'The name');

    $schema = $param->toPrismSchema();

    expect($schema)->toBeInstanceOf(StringSchema::class);
    expect($schema->name())->toBe('name');
});

test('it converts to Prism NumberSchema for integer', function () {
    $param = ToolParameter::integer('count', 'The count');

    $schema = $param->toPrismSchema();

    expect($schema)->toBeInstanceOf(NumberSchema::class);
});

test('it converts to Prism NumberSchema for number', function () {
    $param = ToolParameter::number('price', 'The price');

    $schema = $param->toPrismSchema();

    expect($schema)->toBeInstanceOf(NumberSchema::class);
});

test('it converts to Prism BooleanSchema', function () {
    $param = ToolParameter::boolean('active', 'Is active');

    $schema = $param->toPrismSchema();

    expect($schema)->toBeInstanceOf(BooleanSchema::class);
});

test('it converts to Prism EnumSchema', function () {
    $param = ToolParameter::enum('status', 'Status', ['a', 'b']);

    $schema = $param->toPrismSchema();

    expect($schema)->toBeInstanceOf(EnumSchema::class);
});

test('it converts to Prism ArraySchema', function () {
    $param = ToolParameter::array('tags', 'Tags');

    $schema = $param->toPrismSchema();

    expect($schema)->toBeInstanceOf(ArraySchema::class);
});

test('it converts to Prism ObjectSchema', function () {
    $properties = [ToolParameter::string('name', 'Name')];
    $param = ToolParameter::object('user', 'User', $properties);

    $schema = $param->toPrismSchema();

    expect($schema)->toBeInstanceOf(ObjectSchema::class);
});
