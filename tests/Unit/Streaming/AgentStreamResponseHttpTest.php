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

test('asVercelStream returns StreamedResponse with text/plain headers', function () {
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
