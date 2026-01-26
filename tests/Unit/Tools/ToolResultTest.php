<?php

declare(strict_types=1);

use Atlasphp\Atlas\Tools\Support\ToolResult;

test('it creates with string data and not error', function () {
    $result = new ToolResult('Some result');

    expect($result->toText())->toBe('Some result');
    expect($result->isError)->toBeFalse();
});

test('it creates text result via factory', function () {
    $result = ToolResult::text('Hello');

    expect($result->toText())->toBe('Hello');
    expect($result->isError)->toBeFalse();
});

test('it creates error result via factory', function () {
    $result = ToolResult::error('Something went wrong');

    expect($result->toText())->toBe('Something went wrong');
    expect($result->isError)->toBeTrue();
});

test('it creates json result via factory', function () {
    $data = ['name' => 'John', 'age' => 30];
    $result = ToolResult::json($data);

    expect($result->toText())->toBe(json_encode($data, JSON_PRETTY_PRINT));
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

test('toArray returns data when stored as array', function () {
    $data = ['name' => 'John', 'age' => 30];
    $result = ToolResult::json($data);

    expect($result->toArray())->toBe($data);
});

test('toArray wraps text in array', function () {
    $result = ToolResult::text('Hello');

    expect($result->toArray())->toBe(['text' => 'Hello']);
});

test('toText returns json string for array data', function () {
    $data = ['status' => 'shipped'];
    $result = ToolResult::json($data);

    expect($result->toText())->toBe(json_encode($data, JSON_PRETTY_PRINT));
});

test('toText returns string directly for text data', function () {
    $result = ToolResult::text('Hello world');

    expect($result->toText())->toBe('Hello world');
});

test('toText throws JsonException for non-serializable data', function () {
    // Create data that cannot be JSON encoded (resource)
    $resource = fopen('php://memory', 'r');
    $result = ToolResult::json(['resource' => $resource]);
    fclose($resource);

    expect(fn () => $result->toText())->toThrow(JsonException::class);
});

test('toText throws JsonException for circular reference', function () {
    // Create circular reference that cannot be JSON encoded
    $obj = new stdClass;
    $obj->self = $obj;

    $result = ToolResult::json(['circular' => $obj]);

    expect(fn () => $result->toText())->toThrow(JsonException::class);
});

test('error result toArray wraps error message', function () {
    $result = ToolResult::error('Failed');

    expect($result->toArray())->toBe(['text' => 'Failed']);
});

test('it creates with array data via constructor', function () {
    $data = ['key' => 'value'];
    $result = new ToolResult($data);

    expect($result->toArray())->toBe($data);
    expect($result->toText())->toBe(json_encode($data, JSON_PRETTY_PRINT));
    expect($result->isError)->toBeFalse();
});

test('it creates with array data and error flag via constructor', function () {
    $data = ['error' => 'details'];
    $result = new ToolResult($data, true);

    expect($result->toArray())->toBe($data);
    expect($result->isError)->toBeTrue();
    expect($result->failed())->toBeTrue();
    expect($result->succeeded())->toBeFalse();
});

test('json result is never marked as error', function () {
    $result = ToolResult::json(['data' => 'value']);

    expect($result->isError)->toBeFalse();
    expect($result->succeeded())->toBeTrue();
});
