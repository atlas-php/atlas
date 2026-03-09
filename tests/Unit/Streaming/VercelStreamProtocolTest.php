<?php

declare(strict_types=1);

use Atlasphp\Atlas\Streaming\VercelStreamProtocol;
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
    $this->protocol = new VercelStreamProtocol;
});

test('formats TextDeltaEvent in Vercel wire format', function () {
    $event = new TextDeltaEvent(
        id: 'evt_1',
        timestamp: 1234567890,
        delta: 'Hello',
        messageId: 'msg_1',
    );

    $output = $this->protocol->format($event);

    expect($output)->toBe("0:\"Hello\"\n");
});

test('formats StreamEndEvent in Vercel wire format', function () {
    $event = new StreamEndEvent(
        id: 'evt_2',
        timestamp: 1234567890,
        finishReason: FinishReason::Stop,
        usage: new Usage(10, 5),
    );

    $output = $this->protocol->format($event);

    expect($output)->toContain('d:');
    expect($output)->toContain('"finishReason":"stop"');
    expect($output)->toContain('"promptTokens":10');
    expect($output)->toContain('"completionTokens":5');
});

test('returns null for StreamStartEvent (no Vercel equivalent)', function () {
    $event = new StreamStartEvent(
        id: 'evt_3',
        timestamp: 1234567890,
        model: 'gpt-4',
        provider: 'openai',
    );

    $output = $this->protocol->format($event);

    expect($output)->toBeNull();
});

test('escapes special characters in text delta', function () {
    $event = new TextDeltaEvent(
        id: 'evt_4',
        timestamp: 1234567890,
        delta: 'He said "hello" & goodbye',
        messageId: 'msg_1',
    );

    $output = $this->protocol->format($event);

    expect($output)->toBe("0:\"He said \\\"hello\\\" & goodbye\"\n");
});

test('formats ThinkingStartEvent as reasoning-start', function () {
    $event = new ThinkingStartEvent(
        id: 'evt_5',
        timestamp: 1234567890,
        reasoningId: 'reason_1',
    );

    $output = $this->protocol->format($event);

    expect($output)->toContain('g:');
    expect($output)->toContain('"type":"reasoning-start"');
    expect($output)->toContain('"reasoningId":"reason_1"');
});

test('formats ThinkingEvent as reasoning-delta', function () {
    $event = new ThinkingEvent(
        id: 'evt_6',
        timestamp: 1234567890,
        delta: 'Let me consider...',
        reasoningId: 'reason_1',
    );

    $output = $this->protocol->format($event);

    expect($output)->toContain('g:');
    expect($output)->toContain('"type":"reasoning-delta"');
    expect($output)->toContain('"delta":"Let me consider..."');
});

test('formats ThinkingCompleteEvent as reasoning-complete', function () {
    $event = new ThinkingCompleteEvent(
        id: 'evt_7',
        timestamp: 1234567890,
        reasoningId: 'reason_1',
    );

    $output = $this->protocol->format($event);

    expect($output)->toContain('g:');
    expect($output)->toContain('"type":"reasoning-complete"');
});

test('formats ToolCallEvent with Vercel 9 prefix', function () {
    $toolCall = new ToolCall(
        id: 'tc_1',
        name: 'search',
        arguments: ['query' => 'test'],
    );

    $event = new ToolCallEvent(
        id: 'evt_8',
        timestamp: 1234567890,
        toolCall: $toolCall,
        messageId: 'msg_1',
    );

    $output = $this->protocol->format($event);

    expect($output)->toContain('9:');
    expect($output)->toContain('"toolCallId":"tc_1"');
    expect($output)->toContain('"toolName":"search"');
    expect($output)->toContain('"args"');
});

test('formats ToolResultEvent with Vercel a prefix after tool call', function () {
    $toolCall = new ToolCall(
        id: 'tc_1',
        name: 'search',
        arguments: ['query' => 'test'],
    );

    $toolCallEvent = new ToolCallEvent(
        id: 'evt_8',
        timestamp: 1234567890,
        toolCall: $toolCall,
        messageId: 'msg_1',
    );

    // First send a tool call to set the flag
    $this->protocol->format($toolCallEvent);

    $toolResult = new ToolResult(
        toolCallId: 'tc_1',
        toolName: 'search',
        args: ['query' => 'test'],
        result: 'Found results',
    );

    $event = new ToolResultEvent(
        id: 'evt_9',
        timestamp: 1234567890,
        toolResult: $toolResult,
        messageId: 'msg_1',
    );

    $output = $this->protocol->format($event);

    expect($output)->toContain('a:');
    expect($output)->toContain('"toolCallId":"tc_1"');
    expect($output)->toContain('"result":"Found results"');
});

test('filters orphan ToolResultEvent without prior tool call', function () {
    $toolResult = new ToolResult(
        toolCallId: 'tc_1',
        toolName: 'search',
        args: ['query' => 'test'],
        result: 'Found results',
    );

    $event = new ToolResultEvent(
        id: 'evt_9',
        timestamp: 1234567890,
        toolResult: $toolResult,
        messageId: 'msg_1',
    );

    $output = $this->protocol->format($event);

    expect($output)->toBeNull();
});

test('formats ErrorEvent with Vercel 3 prefix', function () {
    $event = new ErrorEvent(
        id: 'evt_10',
        timestamp: 1234567890,
        errorType: 'rate_limit',
        message: 'Too many requests',
        recoverable: true,
    );

    $output = $this->protocol->format($event);

    expect($output)->toBe("3:\"Too many requests\"\n");
});

test('handles StreamEndEvent with null usage', function () {
    $event = new StreamEndEvent(
        id: 'evt_11',
        timestamp: 1234567890,
        finishReason: FinishReason::Stop,
        usage: null,
    );

    $output = $this->protocol->format($event);

    expect($output)->toContain('"promptTokens":0');
    expect($output)->toContain('"completionTokens":0');
});

test('headers() includes vercel stream header', function () {
    $headers = VercelStreamProtocol::headers();

    expect($headers)->toHaveKey('x-vercel-ai-ui-message-stream');
    expect($headers['x-vercel-ai-ui-message-stream'])->toBe('v1');
    expect($headers['Content-Type'])->toBe('text/plain; charset=utf-8');
});
