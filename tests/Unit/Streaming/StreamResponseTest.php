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

test('toResponse includes proper SSE headers', function () {
    $events = [
        new StreamEndEvent('evt_1', time(), 'stop', []),
    ];

    $stream = new StreamResponse(createTestGenerator($events));
    $response = $stream->toResponse();

    expect($response->headers->get('Content-Type'))->toBe('text/event-stream');
    expect($response->headers->get('Cache-Control'))->toContain('no-cache');
    expect($response->headers->get('Connection'))->toBe('keep-alive');
    expect($response->headers->get('X-Accel-Buffering'))->toBe('no');
});

test('toResponse accepts custom headers', function () {
    $events = [
        new StreamEndEvent('evt_1', time(), 'stop', []),
    ];

    $stream = new StreamResponse(createTestGenerator($events));
    $response = $stream->toResponse(headers: ['X-Custom-Header' => 'custom-value']);

    expect($response->headers->get('X-Custom-Header'))->toBe('custom-value');
    expect($response->headers->get('Content-Type'))->toBe('text/event-stream');
});

test('it handles multiple tool calls with matching results', function () {
    $events = [
        new ToolCallStartEvent('evt_1', time(), 'call_1', 'search', ['query' => 'test']),
        new ToolCallStartEvent('evt_2', time(), 'call_2', 'calculate', ['a' => 5]),
        new ToolCallEndEvent('evt_3', time(), 'call_1', 'search', '{"results": ["a", "b"]}', true),
        new ToolCallEndEvent('evt_4', time(), 'call_2', 'calculate', '10', true),
        new StreamEndEvent('evt_5', time(), 'stop', []),
    ];

    $stream = new StreamResponse(createTestGenerator($events));
    iterator_to_array($stream);

    $toolCalls = $stream->toolCalls();

    expect($toolCalls)->toHaveCount(2);
    expect($toolCalls[0]['id'])->toBe('call_1');
    expect($toolCalls[0]['name'])->toBe('search');
    expect($toolCalls[0]['result'])->toBe('{"results": ["a", "b"]}');
    expect($toolCalls[1]['id'])->toBe('call_2');
    expect($toolCalls[1]['name'])->toBe('calculate');
    expect($toolCalls[1]['result'])->toBe('10');
});

test('it returns null finish reason when no StreamEndEvent', function () {
    $events = [
        new TextDeltaEvent('evt_1', time(), 'Test'),
    ];

    $stream = new StreamResponse(createTestGenerator($events));
    iterator_to_array($stream);

    expect($stream->finishReason())->toBeNull();
});

test('it returns zero for prompt and completion tokens when no StreamEndEvent', function () {
    $events = [
        new TextDeltaEvent('evt_1', time(), 'Test'),
    ];

    $stream = new StreamResponse(createTestGenerator($events));
    iterator_to_array($stream);

    expect($stream->promptTokens())->toBe(0);
    expect($stream->completionTokens())->toBe(0);
    expect($stream->totalTokens())->toBe(0);
});

test('collect only iterates once', function () {
    $iterationCount = 0;
    $events = [
        new TextDeltaEvent('evt_1', time(), 'Test'),
        new StreamEndEvent('evt_2', time(), 'stop', []),
    ];

    $generator = function () use ($events, &$iterationCount) {
        foreach ($events as $event) {
            $iterationCount++;
            yield $event;
        }
    };

    $stream = new StreamResponse($generator());
    $stream->collect();
    $stream->collect(); // Second call should not re-iterate

    expect($iterationCount)->toBe(2); // Only 2 events yielded once
});

test('it handles multiple errors', function () {
    $events = [
        new ErrorEvent('err_1', time(), 'rate_limit', 'Rate limit', true),
        new ErrorEvent('err_2', time(), 'timeout', 'Request timeout', false),
        new StreamEndEvent('evt_3', time(), 'error', []),
    ];

    $stream = new StreamResponse(createTestGenerator($events));
    iterator_to_array($stream);

    expect($stream->hasErrors())->toBeTrue();
    expect($stream->errors())->toHaveCount(2);
    expect($stream->errors()[0]->errorType)->toBe('rate_limit');
    expect($stream->errors()[1]->errorType)->toBe('timeout');
});

test('it tracks tool call without matching end event', function () {
    $events = [
        new ToolCallStartEvent('evt_1', time(), 'call_1', 'search', ['query' => 'test']),
        new StreamEndEvent('evt_2', time(), 'stop', []),
    ];

    $stream = new StreamResponse(createTestGenerator($events));
    iterator_to_array($stream);

    $toolCalls = $stream->toolCalls();

    expect($toolCalls)->toHaveCount(1);
    expect($toolCalls[0]['id'])->toBe('call_1');
    expect($toolCalls[0]['result'])->toBeNull();
});

test('it accumulates text from multiple deltas in order', function () {
    $events = [
        new TextDeltaEvent('evt_1', time(), 'Hello'),
        new TextDeltaEvent('evt_2', time(), ', '),
        new TextDeltaEvent('evt_3', time(), 'World'),
        new TextDeltaEvent('evt_4', time(), '!'),
        new StreamEndEvent('evt_5', time(), 'stop', []),
    ];

    $stream = new StreamResponse(createTestGenerator($events));
    iterator_to_array($stream);

    expect($stream->text())->toBe('Hello, World!');
});

test('toResponse iterates all events during streaming', function () {
    $events = [
        new TextDeltaEvent('evt_1', 1234567890, 'Hello'),
        new TextDeltaEvent('evt_2', 1234567891, ' World'),
        new StreamEndEvent('evt_3', 1234567892, 'stop', ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15]),
    ];

    $stream = new StreamResponse(createTestGenerator($events));
    $response = $stream->toResponse();

    // Execute the streaming callback (output goes to stdout due to flush())
    ob_start();
    $response->sendContent();
    ob_end_clean();

    // Verify all events were processed
    expect($stream->events())->toHaveCount(3);
    expect($stream->text())->toBe('Hello World');
    expect($stream->finishReason())->toBe('stop');
    expect($stream->totalTokens())->toBe(15);
});

test('toResponse calls onComplete callback after streaming', function () {
    $events = [
        new TextDeltaEvent('evt_1', time(), 'Test'),
        new StreamEndEvent('evt_2', time(), 'stop', []),
    ];

    $callbackCalled = false;
    $receivedStream = null;

    $stream = new StreamResponse(createTestGenerator($events));
    $response = $stream->toResponse(function ($s) use (&$callbackCalled, &$receivedStream) {
        $callbackCalled = true;
        $receivedStream = $s;
    });

    // Capture and discard output
    ob_start();
    $response->sendContent();
    ob_end_clean();

    expect($callbackCalled)->toBeTrue();
    expect($receivedStream)->toBe($stream);
    expect($receivedStream->text())->toBe('Test');
});

test('toResponse onComplete receives fully populated stream', function () {
    $events = [
        new TextDeltaEvent('evt_1', time(), 'Hello '),
        new TextDeltaEvent('evt_2', time(), 'World'),
        new ToolCallStartEvent('evt_3', time(), 'call_1', 'search', ['q' => 'test']),
        new ToolCallEndEvent('evt_4', time(), 'call_1', 'search', '{"found": true}', true),
        new StreamEndEvent('evt_5', time(), 'stop', ['prompt_tokens' => 10, 'completion_tokens' => 20, 'total_tokens' => 30]),
    ];

    $capturedText = null;
    $capturedToolCalls = null;
    $capturedTokens = null;

    $stream = new StreamResponse(createTestGenerator($events));
    $response = $stream->toResponse(function ($s) use (&$capturedText, &$capturedToolCalls, &$capturedTokens) {
        $capturedText = $s->text();
        $capturedToolCalls = $s->toolCalls();
        $capturedTokens = $s->totalTokens();
    });

    ob_start();
    $response->sendContent();
    ob_end_clean();

    expect($capturedText)->toBe('Hello World');
    expect($capturedToolCalls)->toHaveCount(1);
    expect($capturedToolCalls[0]['result'])->toBe('{"found": true}');
    expect($capturedTokens)->toBe(30);
});

test('toResponse works without onComplete callback', function () {
    $events = [
        new TextDeltaEvent('evt_1', time(), 'Test'),
        new StreamEndEvent('evt_2', time(), 'stop', []),
    ];

    $stream = new StreamResponse(createTestGenerator($events));
    $response = $stream->toResponse();

    ob_start();
    $response->sendContent();
    ob_end_clean();

    // Verify stream was fully processed
    expect($stream->events())->toHaveCount(2);
    expect($stream->text())->toBe('Test');
});

test('individual events produce valid SSE format', function () {
    // Test that each event type produces valid SSE
    $textEvent = new TextDeltaEvent('evt_1', 1234567890, 'Test');
    $endEvent = new StreamEndEvent('evt_2', 1234567891, 'stop', []);

    $textSse = $textEvent->toSse();
    $endSse = $endEvent->toSse();

    // Verify SSE format: event line, data line, blank line
    expect($textSse)->toContain('event: text.delta');
    expect($textSse)->toContain('data: ');
    expect($textSse)->toContain('"text":"Test"');

    expect($endSse)->toContain('event: stream.end');
    expect($endSse)->toContain('data: ');
    expect($endSse)->toContain('"finish_reason":"stop"');

    // Verify data is valid JSON
    preg_match('/data: (.+)/', $textSse, $matches);
    $decoded = json_decode($matches[1], true);
    expect($decoded)->toBeArray();
    expect($decoded['type'])->toBe('text.delta');
});
