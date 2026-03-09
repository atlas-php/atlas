<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Support\AgentContext;
use Atlasphp\Atlas\Agents\Support\AgentStreamResponse;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Illuminate\Contracts\Support\Responsable;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\StreamStartEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\ValueObjects\Usage;
use Symfony\Component\HttpFoundation\StreamedResponse;

function createTestStream(string $text = 'Hello world'): Generator
{
    $timestamp = time();
    $eventId = 0;

    yield new StreamStartEvent(
        id: 'evt_'.($eventId++),
        timestamp: $timestamp,
        model: 'gpt-4',
        provider: 'openai',
    );

    $chunks = str_split($text, 5);
    foreach ($chunks as $chunk) {
        yield new TextDeltaEvent(
            id: 'evt_'.($eventId++),
            timestamp: $timestamp,
            delta: $chunk,
            messageId: 'msg_1',
        );
    }

    yield new StreamEndEvent(
        id: 'evt_'.($eventId++),
        timestamp: $timestamp,
        finishReason: FinishReason::Stop,
        usage: new Usage(10, 5),
    );
}

/**
 * Capture output from a StreamedResponse callback.
 *
 * Uses nested output buffering to prevent internal ob_flush()
 * calls from flushing our capture buffer.
 */
function captureStreamedOutput(StreamedResponse $response): string
{
    $captured = '';

    // Outer buffer catches content flushed from inner buffer
    ob_start(function (string $chunk) use (&$captured): string {
        $captured .= $chunk;

        return '';
    });

    // Inner buffer — the response's ob_flush() flushes into our outer callback
    ob_start();
    $response->sendContent();
    ob_end_flush(); // flush inner into outer's callback

    ob_end_clean(); // close outer

    return $captured;
}

test('AgentStreamResponse implements Responsable', function () {
    $response = new AgentStreamResponse(
        stream: createTestStream(),
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response)->toBeInstanceOf(Responsable::class);
});

test('toResponse returns StreamedResponse with SSE headers', function () {
    $response = new AgentStreamResponse(
        stream: createTestStream(),
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $httpResponse = $response->toResponse(request());

    expect($httpResponse)->toBeInstanceOf(StreamedResponse::class);
    expect($httpResponse->headers->get('Content-Type'))->toBe('text/event-stream');
    expect($httpResponse->headers->get('Cache-Control'))->toContain('no-cache');
});

test('asVercelStream returns StreamedResponse with Vercel headers', function () {
    $response = new AgentStreamResponse(
        stream: createTestStream(),
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $httpResponse = $response->asVercelStream()->toResponse(request());

    expect($httpResponse)->toBeInstanceOf(StreamedResponse::class);
    expect($httpResponse->headers->get('Content-Type'))->toBe('text/plain; charset=utf-8');
    expect($httpResponse->headers->get('x-vercel-ai-ui-message-stream'))->toBe('v1');
});

test('text() collects and returns full text from stream', function () {
    $response = new AgentStreamResponse(
        stream: createTestStream('Hello world'),
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response->text())->toBe('Hello world');
    expect($response->isConsumed())->toBeTrue();
});

test('then() callback is called after stream consumption', function () {
    $callbackCalled = false;

    $response = new AgentStreamResponse(
        stream: createTestStream('Test'),
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $response->then(function (AgentStreamResponse $r) use (&$callbackCalled) {
        $callbackCalled = true;
    });

    // Consume the stream
    foreach ($response as $event) {
        // consume
    }

    expect($callbackCalled)->toBeTrue();
});

test('SSE response body contains formatted stream events with eventKey', function () {
    $response = new AgentStreamResponse(
        stream: createTestStream('Hello'),
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $httpResponse = $response->toResponse(request());
    $output = captureStreamedOutput($httpResponse);

    // Verify SSE format uses eventKey (underscored)
    expect($output)->toContain('event: stream_start');
    expect($output)->toContain('event: text_delta');
    expect($output)->toContain('"delta":"Hello"');
    expect($output)->toContain('event: stream_end');
    expect($output)->toContain('event: done');
    expect($output)->toContain('[DONE]');
});

test('SSE response fires each() callback on every event', function () {
    $eachCount = 0;

    $response = new AgentStreamResponse(
        stream: createTestStream('Hi'),
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $response->each(function () use (&$eachCount) {
        $eachCount++;
    });

    $httpResponse = $response->toResponse(request());
    captureStreamedOutput($httpResponse);

    expect($eachCount)->toBe(3); // start, delta, end
});

test('SSE response marks stream as consumed and fires then callback', function () {
    $callbackFired = false;

    $response = new AgentStreamResponse(
        stream: createTestStream('Test'),
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $response->then(function () use (&$callbackFired) {
        $callbackFired = true;
    });

    $httpResponse = $response->toResponse(request());
    captureStreamedOutput($httpResponse);

    expect($response->isConsumed())->toBeTrue();
    expect($callbackFired)->toBeTrue();
});

test('SSE response collects events during streaming', function () {
    $response = new AgentStreamResponse(
        stream: createTestStream('Hi'),
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $httpResponse = $response->toResponse(request());
    captureStreamedOutput($httpResponse);

    $events = $response->events();
    expect($events)->not->toBeEmpty();
    expect($events[0])->toBeInstanceOf(StreamStartEvent::class);
});

test('Vercel response body contains Vercel AI SDK wire format', function () {
    $response = new AgentStreamResponse(
        stream: createTestStream('Hello'),
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $httpResponse = $response->asVercelStream()->toResponse(request());
    $output = captureStreamedOutput($httpResponse);

    // Vercel text delta format: 0:"text"\n
    expect($output)->toContain('0:"Hello"');
    // Vercel finish format: d:{"finishReason":"stop",...}\n
    expect($output)->toContain('d:{"finishReason":"stop"');
    expect($output)->toContain('"promptTokens":10');
    expect($output)->toContain('"completionTokens":5');
});

test('Vercel response marks stream as consumed and fires then callback', function () {
    $callbackFired = false;

    $response = new AgentStreamResponse(
        stream: createTestStream('Test'),
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $response->then(function () use (&$callbackFired) {
        $callbackFired = true;
    });

    $httpResponse = $response->asVercelStream()->toResponse(request());
    captureStreamedOutput($httpResponse);

    expect($response->isConsumed())->toBeTrue();
    expect($callbackFired)->toBeTrue();
});

test('Vercel response collects events during streaming', function () {
    $response = new AgentStreamResponse(
        stream: createTestStream('Hi'),
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $httpResponse = $response->asVercelStream()->toResponse(request());
    captureStreamedOutput($httpResponse);

    $events = $response->events();
    expect($events)->not->toBeEmpty();
    expect($events)->toHaveCount(3); // start + 1 text delta + end
});

test('Vercel response does not include stream_start events in output', function () {
    $response = new AgentStreamResponse(
        stream: createTestStream('Test'),
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $httpResponse = $response->asVercelStream()->toResponse(request());
    $output = captureStreamedOutput($httpResponse);

    // StreamStartEvent should not appear in Vercel format (returns null)
    expect($output)->not->toContain('stream_start');
    expect($output)->not->toContain('openai');
});

test('text() is idempotent after consumption', function () {
    $response = new AgentStreamResponse(
        stream: createTestStream('Test data'),
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $first = $response->text();
    $second = $response->text();

    expect($first)->toBe('Test data');
    expect($second)->toBe('Test data');
});

test('Vercel response fires each() callback on events', function () {
    $eachCount = 0;

    $response = new AgentStreamResponse(
        stream: createTestStream('Hi'),
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $response->each(function () use (&$eachCount) {
        $eachCount++;
    });

    $httpResponse = $response->asVercelStream()->toResponse(request());
    captureStreamedOutput($httpResponse);

    expect($eachCount)->toBe(3); // start, delta, end
});
