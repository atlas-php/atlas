<?php

declare(strict_types=1);

use Atlasphp\Atlas\Tools\Support\ToolResult;

test('it creates with text and not error', function () {
    $result = new ToolResult('Some result');

    expect($result->text)->toBe('Some result');
    expect($result->isError)->toBeFalse();
});

test('it creates text result via factory', function () {
    $result = ToolResult::text('Hello');

    expect($result->text)->toBe('Hello');
    expect($result->isError)->toBeFalse();
});

test('it creates error result via factory', function () {
    $result = ToolResult::error('Something went wrong');

    expect($result->text)->toBe('Something went wrong');
    expect($result->isError)->toBeTrue();
});

test('it creates json result via factory', function () {
    $data = ['name' => 'John', 'age' => 30];
    $result = ToolResult::json($data);

    expect($result->text)->toBe(json_encode($data, JSON_PRETTY_PRINT));
    expect($result->isError)->toBeFalse();
});

test('it reports failed correctly', function () {
    $success = ToolResult::text('ok');
    $error = ToolResult::error('fail');

    expect($success->failed())->toBeFalse();
    expect($error->failed())->toBeTrue();
});

test('it reports succeeded correctly', function () {
    $success = ToolResult::text('ok');
    $error = ToolResult::error('fail');

    expect($success->succeeded())->toBeTrue();
    expect($error->succeeded())->toBeFalse();
});

test('it converts to array', function () {
    $result = ToolResult::text('Hello');

    expect($result->toArray())->toBe([
        'text' => 'Hello',
        'is_error' => false,
    ]);
});

test('it converts error to array', function () {
    $result = ToolResult::error('Failed');

    expect($result->toArray())->toBe([
        'text' => 'Failed',
        'is_error' => true,
    ]);
});

test('it returns error result when json encoding fails', function () {
    // Create data that cannot be JSON encoded (resource)
    $resource = fopen('php://memory', 'r');
    $data = ['resource' => $resource];

    $result = ToolResult::json($data);

    fclose($resource);

    expect($result->isError)->toBeTrue();
    expect($result->text)->toContain('Failed to encode tool result as JSON:');
});

test('it returns error result for circular reference in json', function () {
    // Create circular reference that cannot be JSON encoded
    $obj = new stdClass;
    $obj->self = $obj;

    // We need to use an array with the object
    $data = ['circular' => $obj];

    $result = ToolResult::json($data);

    expect($result->isError)->toBeTrue();
    expect($result->text)->toContain('Failed to encode tool result as JSON:');
});
