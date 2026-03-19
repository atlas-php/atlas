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
});
