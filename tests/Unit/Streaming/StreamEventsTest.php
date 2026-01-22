<?php

declare(strict_types=1);

use Atlasphp\Atlas\Streaming\Events\ErrorEvent;
use Atlasphp\Atlas\Streaming\Events\StreamEndEvent;
use Atlasphp\Atlas\Streaming\Events\StreamStartEvent;
use Atlasphp\Atlas\Streaming\Events\TextDeltaEvent;
use Atlasphp\Atlas\Streaming\Events\ToolCallEndEvent;
use Atlasphp\Atlas\Streaming\Events\ToolCallStartEvent;

test('StreamStartEvent has correct type and properties', function () {
    $event = new StreamStartEvent(
        id: 'evt_123',
        timestamp: 1234567890,
        model: 'gpt-4',
        provider: 'openai',
    );

    expect($event->type())->toBe('stream.start');
    expect($event->id)->toBe('evt_123');
    expect($event->timestamp)->toBe(1234567890);
    expect($event->model)->toBe('gpt-4');
    expect($event->provider)->toBe('openai');
});

test('StreamStartEvent converts to array', function () {
    $event = new StreamStartEvent(
        id: 'evt_123',
        timestamp: 1234567890,
        model: 'gpt-4',
        provider: 'openai',
    );

    $array = $event->toArray();

    expect($array)->toBe([
        'id' => 'evt_123',
        'type' => 'stream.start',
        'timestamp' => 1234567890,
        'model' => 'gpt-4',
        'provider' => 'openai',
    ]);
});

test('TextDeltaEvent has correct type and properties', function () {
    $event = new TextDeltaEvent(
        id: 'evt_456',
        timestamp: 1234567891,
        text: 'Hello',
    );

    expect($event->type())->toBe('text.delta');
    expect($event->id)->toBe('evt_456');
    expect($event->timestamp)->toBe(1234567891);
    expect($event->text)->toBe('Hello');
});

test('TextDeltaEvent converts to array', function () {
    $event = new TextDeltaEvent(
        id: 'evt_456',
        timestamp: 1234567891,
        text: 'Hello',
    );

    expect($event->toArray())->toBe([
        'id' => 'evt_456',
        'type' => 'text.delta',
        'timestamp' => 1234567891,
        'text' => 'Hello',
    ]);
});

test('ToolCallStartEvent has correct type and properties', function () {
    $event = new ToolCallStartEvent(
        id: 'evt_789',
        timestamp: 1234567892,
        toolId: 'call_abc',
        toolName: 'search',
        arguments: ['query' => 'test'],
    );

    expect($event->type())->toBe('tool.call.start');
    expect($event->toolId)->toBe('call_abc');
    expect($event->toolName)->toBe('search');
    expect($event->arguments)->toBe(['query' => 'test']);
});

test('ToolCallStartEvent converts to array', function () {
    $event = new ToolCallStartEvent(
        id: 'evt_789',
        timestamp: 1234567892,
        toolId: 'call_abc',
        toolName: 'search',
        arguments: ['query' => 'test'],
    );

    expect($event->toArray())->toBe([
        'id' => 'evt_789',
        'type' => 'tool.call.start',
        'timestamp' => 1234567892,
        'tool_id' => 'call_abc',
        'tool_name' => 'search',
        'arguments' => ['query' => 'test'],
    ]);
});

test('ToolCallEndEvent has correct type and properties', function () {
    $event = new ToolCallEndEvent(
        id: 'evt_101',
        timestamp: 1234567893,
        toolId: 'call_abc',
        toolName: 'search',
        result: '{"results": []}',
        success: true,
    );

    expect($event->type())->toBe('tool.call.end');
    expect($event->toolId)->toBe('call_abc');
    expect($event->result)->toBe('{"results": []}');
    expect($event->success)->toBeTrue();
});

test('ToolCallEndEvent defaults success to true', function () {
    $event = new ToolCallEndEvent(
        id: 'evt_101',
        timestamp: 1234567893,
        toolId: 'call_abc',
        toolName: 'search',
    );

    expect($event->success)->toBeTrue();
});

test('ToolCallEndEvent converts to array', function () {
    $event = new ToolCallEndEvent(
        id: 'evt_101',
        timestamp: 1234567893,
        toolId: 'call_abc',
        toolName: 'search',
        result: '{"results": []}',
        success: true,
    );

    expect($event->toArray())->toBe([
        'id' => 'evt_101',
        'type' => 'tool.call.end',
        'timestamp' => 1234567893,
        'tool_id' => 'call_abc',
        'tool_name' => 'search',
        'result' => '{"results": []}',
        'success' => true,
    ]);
});

test('StreamEndEvent has correct type and properties', function () {
    $event = new StreamEndEvent(
        id: 'evt_999',
        timestamp: 1234567899,
        finishReason: 'stop',
        usage: [
            'prompt_tokens' => 10,
            'completion_tokens' => 20,
            'total_tokens' => 30,
        ],
    );

    expect($event->type())->toBe('stream.end');
    expect($event->finishReason)->toBe('stop');
    expect($event->usage)->toBe([
        'prompt_tokens' => 10,
        'completion_tokens' => 20,
        'total_tokens' => 30,
    ]);
});

test('StreamEndEvent provides token accessors', function () {
    $event = new StreamEndEvent(
        id: 'evt_999',
        timestamp: 1234567899,
        finishReason: 'stop',
        usage: [
            'prompt_tokens' => 10,
            'completion_tokens' => 20,
            'total_tokens' => 30,
        ],
    );

    expect($event->promptTokens())->toBe(10);
    expect($event->completionTokens())->toBe(20);
    expect($event->totalTokens())->toBe(30);
});

test('StreamEndEvent returns zero for missing usage', function () {
    $event = new StreamEndEvent(
        id: 'evt_999',
        timestamp: 1234567899,
    );

    expect($event->promptTokens())->toBe(0);
    expect($event->completionTokens())->toBe(0);
    expect($event->totalTokens())->toBe(0);
});

test('StreamEndEvent converts to array', function () {
    $event = new StreamEndEvent(
        id: 'evt_999',
        timestamp: 1234567899,
        finishReason: 'stop',
        usage: [
            'prompt_tokens' => 10,
            'completion_tokens' => 20,
            'total_tokens' => 30,
        ],
    );

    expect($event->toArray())->toBe([
        'id' => 'evt_999',
        'type' => 'stream.end',
        'timestamp' => 1234567899,
        'finish_reason' => 'stop',
        'usage' => [
            'prompt_tokens' => 10,
            'completion_tokens' => 20,
            'total_tokens' => 30,
        ],
    ]);
});

test('StreamEndEvent converts to array with null values', function () {
    $event = new StreamEndEvent(
        id: 'evt_999',
        timestamp: 1234567899,
    );

    expect($event->toArray())->toBe([
        'id' => 'evt_999',
        'type' => 'stream.end',
        'timestamp' => 1234567899,
        'finish_reason' => null,
        'usage' => [],
    ]);
});

test('ErrorEvent has correct type and properties', function () {
    $event = new ErrorEvent(
        id: 'err_123',
        timestamp: 1234567890,
        errorType: 'rate_limit',
        message: 'Rate limit exceeded',
        recoverable: true,
    );

    expect($event->type())->toBe('error');
    expect($event->errorType)->toBe('rate_limit');
    expect($event->message)->toBe('Rate limit exceeded');
    expect($event->recoverable)->toBeTrue();
});

test('ErrorEvent defaults recoverable to false', function () {
    $event = new ErrorEvent(
        id: 'err_123',
        timestamp: 1234567890,
        errorType: 'fatal',
        message: 'Something went wrong',
    );

    expect($event->recoverable)->toBeFalse();
});

test('ErrorEvent converts to array', function () {
    $event = new ErrorEvent(
        id: 'err_123',
        timestamp: 1234567890,
        errorType: 'rate_limit',
        message: 'Rate limit exceeded',
        recoverable: true,
    );

    expect($event->toArray())->toBe([
        'id' => 'err_123',
        'type' => 'error',
        'timestamp' => 1234567890,
        'error_type' => 'rate_limit',
        'message' => 'Rate limit exceeded',
        'recoverable' => true,
    ]);
});

test('events can be converted to SSE format', function () {
    $event = new TextDeltaEvent(
        id: 'evt_456',
        timestamp: 1234567891,
        text: 'Hello',
    );

    $sse = $event->toSse();

    expect($sse)->toContain('event: text.delta');
    expect($sse)->toContain('data: ');
    expect($sse)->toContain('"text":"Hello"');
});

test('events can be converted to JSON', function () {
    $event = new TextDeltaEvent(
        id: 'evt_456',
        timestamp: 1234567891,
        text: 'Hello',
    );

    $json = $event->toJson();

    expect(json_decode($json, true))->toBe([
        'id' => 'evt_456',
        'type' => 'text.delta',
        'timestamp' => 1234567891,
        'text' => 'Hello',
    ]);
});
