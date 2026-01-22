<?php

declare(strict_types=1);

use Atlasphp\Atlas\Streaming\Events\ErrorEvent;
use Atlasphp\Atlas\Streaming\Events\StreamEndEvent;
use Atlasphp\Atlas\Streaming\Events\StreamStartEvent;
use Atlasphp\Atlas\Streaming\Events\TextDeltaEvent;
use Atlasphp\Atlas\Streaming\Events\ToolCallEndEvent;
use Atlasphp\Atlas\Streaming\Events\ToolCallStartEvent;
use Atlasphp\Atlas\Streaming\StreamResponse;

function createTestGenerator(array $events): Generator
{
    foreach ($events as $event) {
        yield $event;
    }
}

test('it iterates through events', function () {
    $events = [
        new StreamStartEvent('evt_1', time(), 'gpt-4', 'openai'),
        new TextDeltaEvent('evt_2', time(), 'Hello'),
        new TextDeltaEvent('evt_3', time(), ' World'),
        new StreamEndEvent('evt_4', time(), 'stop', []),
    ];

    $stream = new StreamResponse(createTestGenerator($events));
    $collected = [];

    foreach ($stream as $event) {
        $collected[] = $event;
    }

    expect($collected)->toHaveCount(4);
    expect($collected[0])->toBeInstanceOf(StreamStartEvent::class);
    expect($collected[1])->toBeInstanceOf(TextDeltaEvent::class);
});

test('it accumulates text from TextDeltaEvents', function () {
    $events = [
        new TextDeltaEvent('evt_1', time(), 'Hello'),
        new TextDeltaEvent('evt_2', time(), ' '),
        new TextDeltaEvent('evt_3', time(), 'World'),
        new StreamEndEvent('evt_4', time(), 'stop', []),
    ];

    $stream = new StreamResponse(createTestGenerator($events));
    iterator_to_array($stream);

    expect($stream->text())->toBe('Hello World');
});

test('it provides access to collected events after iteration', function () {
    $events = [
        new TextDeltaEvent('evt_1', time(), 'Test'),
        new StreamEndEvent('evt_2', time(), 'stop', []),
    ];

    $stream = new StreamResponse(createTestGenerator($events));
    iterator_to_array($stream);

    expect($stream->events())->toHaveCount(2);
});

test('it provides finish reason from StreamEndEvent', function () {
    $events = [
        new StreamEndEvent('evt_1', time(), 'stop', []),
    ];

    $stream = new StreamResponse(createTestGenerator($events));
    iterator_to_array($stream);

    expect($stream->finishReason())->toBe('stop');
});

test('it provides usage statistics from StreamEndEvent', function () {
    $usage = [
        'prompt_tokens' => 10,
        'completion_tokens' => 20,
        'total_tokens' => 30,
    ];

    $events = [
        new StreamEndEvent('evt_1', time(), 'stop', $usage),
    ];

    $stream = new StreamResponse(createTestGenerator($events));
    iterator_to_array($stream);

    expect($stream->usage())->toBe($usage);
    expect($stream->totalTokens())->toBe(30);
    expect($stream->promptTokens())->toBe(10);
    expect($stream->completionTokens())->toBe(20);
});

test('it returns empty usage when no StreamEndEvent', function () {
    $events = [
        new TextDeltaEvent('evt_1', time(), 'Test'),
    ];

    $stream = new StreamResponse(createTestGenerator($events));
    iterator_to_array($stream);

    expect($stream->usage())->toBe([]);
    expect($stream->totalTokens())->toBe(0);
});

test('it collects tool calls', function () {
    $events = [
        new ToolCallStartEvent('evt_1', time(), 'call_1', 'search', ['query' => 'test']),
        new ToolCallEndEvent('evt_2', time(), 'call_1', 'search', '{"results": []}', true),
        new StreamEndEvent('evt_3', time(), 'stop', []),
    ];

    $stream = new StreamResponse(createTestGenerator($events));
    iterator_to_array($stream);

    $toolCalls = $stream->toolCalls();

    expect($toolCalls)->toHaveCount(1);
    expect($toolCalls[0]['id'])->toBe('call_1');
    expect($toolCalls[0]['name'])->toBe('search');
    expect($toolCalls[0]['arguments'])->toBe(['query' => 'test']);
    expect($toolCalls[0]['result'])->toBe('{"results": []}');
});

test('it detects errors', function () {
    $events = [
        new ErrorEvent('err_1', time(), 'rate_limit', 'Rate limit exceeded', false),
        new StreamEndEvent('evt_2', time(), 'error', []),
    ];

    $stream = new StreamResponse(createTestGenerator($events));
    iterator_to_array($stream);

    expect($stream->hasErrors())->toBeTrue();
    expect($stream->errors())->toHaveCount(1);
    expect($stream->errors()[0]->errorType)->toBe('rate_limit');
});

test('it reports no errors when stream is clean', function () {
    $events = [
        new TextDeltaEvent('evt_1', time(), 'Test'),
        new StreamEndEvent('evt_2', time(), 'stop', []),
    ];

    $stream = new StreamResponse(createTestGenerator($events));
    iterator_to_array($stream);

    expect($stream->hasErrors())->toBeFalse();
    expect($stream->errors())->toBe([]);
});

test('collect method iterates and returns self', function () {
    $events = [
        new TextDeltaEvent('evt_1', time(), 'Test'),
        new StreamEndEvent('evt_2', time(), 'stop', []),
    ];

    $stream = new StreamResponse(createTestGenerator($events));
    $result = $stream->collect();

    expect($result)->toBe($stream);
    expect($stream->text())->toBe('Test');
});

test('onComplete callback is called after iteration', function () {
    $events = [
        new TextDeltaEvent('evt_1', time(), 'Test'),
        new StreamEndEvent('evt_2', time(), 'stop', []),
    ];

    $callbackCalled = false;
    $receivedStream = null;

    $stream = new StreamResponse(
        createTestGenerator($events),
        function ($s) use (&$callbackCalled, &$receivedStream) {
            $callbackCalled = true;
            $receivedStream = $s;
        }
    );

    iterator_to_array($stream);

    expect($callbackCalled)->toBeTrue();
    expect($receivedStream)->toBe($stream);
});

test('toResponse returns StreamedResponse', function () {
    $events = [
        new TextDeltaEvent('evt_1', time(), 'Test'),
        new StreamEndEvent('evt_2', time(), 'stop', []),
    ];

    $stream = new StreamResponse(createTestGenerator($events));
    $response = $stream->toResponse();

    expect($response)->toBeInstanceOf(Symfony\Component\HttpFoundation\StreamedResponse::class);
    expect($response->headers->get('Content-Type'))->toBe('text/event-stream');
});
