<?php

declare(strict_types=1);

use Atlasphp\Atlas\Executor\ToolResult;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Messages\ToolResultMessage;

it('stores toolCall, content, and isError', function () {
    $toolCall = new ToolCall('tc-1', 'search', ['query' => 'test']);
    $result = new ToolResult($toolCall, 'found 5 results', true);

    expect($result->toolCall)->toBe($toolCall);
    expect($result->content)->toBe('found 5 results');
    expect($result->isError)->toBeTrue();
});

it('defaults isError to false', function () {
    $toolCall = new ToolCall('tc-1', 'search', []);
    $result = new ToolResult($toolCall, 'ok');

    expect($result->isError)->toBeFalse();
});

it('converts to ToolResultMessage with correct properties', function () {
    $toolCall = new ToolCall('tc-42', 'calculator', ['a' => 1]);
    $result = new ToolResult($toolCall, '42');

    $message = $result->toMessage();

    expect($message)->toBeInstanceOf(ToolResultMessage::class);
    expect($message->toolCallId)->toBe('tc-42');
    expect($message->content)->toBe('42');
    expect($message->toolName)->toBe('calculator');
    expect($message->isError)->toBeFalse();
});

it('passes isError true to ToolResultMessage', function () {
    $toolCall = new ToolCall('tc-99', 'failing_tool', []);
    $result = new ToolResult($toolCall, 'Something went wrong', isError: true);

    $message = $result->toMessage();

    expect($message->isError)->toBeTrue();
});

it('passes isError false to ToolResultMessage by default', function () {
    $toolCall = new ToolCall('tc-100', 'working_tool', []);
    $result = new ToolResult($toolCall, 'success');

    $message = $result->toMessage();

    expect($message->isError)->toBeFalse();
});

it('stores original exception class name', function () {
    $toolCall = new ToolCall('tc-1', 'search', []);
    $result = new ToolResult($toolCall, 'Error occurred', isError: true, exceptionClass: InvalidArgumentException::class);

    expect($result->exceptionClass)->toBe(InvalidArgumentException::class);
});

it('defaults exceptionClass to null', function () {
    $toolCall = new ToolCall('tc-1', 'search', []);
    $result = new ToolResult($toolCall, 'ok');

    expect($result->exceptionClass)->toBeNull();
});
