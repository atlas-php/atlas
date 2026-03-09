<?php

declare(strict_types=1);

use Atlasphp\Atlas\Streaming\SseStreamFormatter;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Streaming\Events\ErrorEvent;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\StreamStartEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Streaming\Events\ThinkingCompleteEvent;
use Prism\Prism\Streaming\Events\ThinkingEvent;
use Prism\Prism\Streaming\Events\ThinkingStartEvent;
use Prism\Prism\Streaming\Events\ToolCallEvent;
use Prism\Prism\Streaming\Events\ToolResultEvent;
use Prism\Prism\ValueObjects\ToolCall;
use Prism\Prism\ValueObjects\ToolResult;
use Prism\Prism\ValueObjects\Usage;

beforeEach(function () {
    $this->formatter = new SseStreamFormatter;
});

test('formats StreamStartEvent as SSE with eventKey', function () {
    $event = new StreamStartEvent(
        id: 'evt_1',
        timestamp: 1234567890,
        model: 'gpt-4',
        provider: 'openai',
    );

    $output = $this->formatter->format($event);

    expect($output)->toContain('event: stream_start');
    expect($output)->toContain('"model":"gpt-4"');
    expect($output)->toContain('"provider":"openai"');
});

test('formats TextDeltaEvent as SSE with eventKey', function () {
    $event = new TextDeltaEvent(
        id: 'evt_2',
        timestamp: 1234567890,
        delta: 'Hello world',
        messageId: 'msg_1',
    );

    $output = $this->formatter->format($event);

    expect($output)->toContain('event: text_delta');
    expect($output)->toContain('"delta":"Hello world"');
});

test('formats StreamEndEvent as SSE with eventKey', function () {
    $event = new StreamEndEvent(
        id: 'evt_3',
        timestamp: 1234567890,
        finishReason: FinishReason::Stop,
        usage: new Usage(10, 5),
    );

    $output = $this->formatter->format($event);

    expect($output)->toContain('event: stream_end');
    expect($output)->toContain('"finish_reason"');
    expect($output)->toContain('"prompt_tokens":10');
    expect($output)->toContain('"completion_tokens":5');
});

test('formats ThinkingStartEvent as SSE', function () {
    $event = new ThinkingStartEvent(
        id: 'evt_4',
        timestamp: 1234567890,
        reasoningId: 'reason_1',
    );

    $output = $this->formatter->format($event);

    expect($output)->toContain('event: thinking_start');
    expect($output)->toContain('"reasoning_id":"reason_1"');
});

test('formats ThinkingEvent as SSE', function () {
    $event = new ThinkingEvent(
        id: 'evt_5',
        timestamp: 1234567890,
        delta: 'Let me think...',
        reasoningId: 'reason_1',
    );

    $output = $this->formatter->format($event);

    expect($output)->toContain('event: thinking_delta');
    expect($output)->toContain('"delta":"Let me think..."');
    expect($output)->toContain('"reasoning_id":"reason_1"');
});

test('formats ThinkingCompleteEvent as SSE', function () {
    $event = new ThinkingCompleteEvent(
        id: 'evt_6',
        timestamp: 1234567890,
        reasoningId: 'reason_1',
    );

    $output = $this->formatter->format($event);

    expect($output)->toContain('event: thinking_complete');
    expect($output)->toContain('"reasoning_id":"reason_1"');
});

test('formats ToolCallEvent as SSE', function () {
    $toolCall = new ToolCall(
        id: 'tc_1',
        name: 'search',
        arguments: ['query' => 'test'],
    );

    $event = new ToolCallEvent(
        id: 'evt_7',
        timestamp: 1234567890,
        toolCall: $toolCall,
        messageId: 'msg_1',
    );

    $output = $this->formatter->format($event);

    expect($output)->toContain('event: tool_call');
    expect($output)->toContain('"tool_name":"search"');
});

test('formats ToolResultEvent as SSE', function () {
    $toolResult = new ToolResult(
        toolCallId: 'tc_1',
        toolName: 'search',
        args: ['query' => 'test'],
        result: 'Found 5 results',
    );

    $event = new ToolResultEvent(
        id: 'evt_8',
        timestamp: 1234567890,
        toolResult: $toolResult,
        messageId: 'msg_1',
    );

    $output = $this->formatter->format($event);

    expect($output)->toContain('event: tool_result');
    expect($output)->toContain('"result":"Found 5 results"');
});

test('formats ErrorEvent as SSE', function () {
    $event = new ErrorEvent(
        id: 'evt_9',
        timestamp: 1234567890,
        errorType: 'rate_limit',
        message: 'Too many requests',
        recoverable: true,
    );

    $output = $this->formatter->format($event);

    expect($output)->toContain('event: error');
    expect($output)->toContain('"error_type":"rate_limit"');
    expect($output)->toContain('"message":"Too many requests"');
    expect($output)->toContain('"recoverable":true');
});

test('uses toArray() for full event data', function () {
    $event = new StreamStartEvent(
        id: 'evt_1',
        timestamp: 1234567890,
        model: 'claude-4',
        provider: 'anthropic',
    );

    $output = $this->formatter->format($event);
    $dataLine = explode("\n", $output)[1];
    $json = json_decode(substr($dataLine, strlen('data: ')), true);

    expect($json)->toBe($event->toArray());
});

test('done() returns proper SSE done signal', function () {
    $output = $this->formatter->done();

    expect($output)->toBe("event: done\ndata: [DONE]\n\n");
});
