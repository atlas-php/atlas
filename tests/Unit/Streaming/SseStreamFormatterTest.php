<?php

declare(strict_types=1);

use Atlasphp\Atlas\Streaming\SseStreamFormatter;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\StreamStartEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\ValueObjects\Usage;

beforeEach(function () {
    $this->formatter = new SseStreamFormatter;
});

test('formats StreamStartEvent as SSE', function () {
    $event = new StreamStartEvent(
        id: 'evt_1',
        timestamp: 1234567890,
        model: 'gpt-4',
        provider: 'openai',
    );

    $output = $this->formatter->format($event);

    expect($output)->toContain('event: stream-start');
    expect($output)->toContain('"model":"gpt-4"');
    expect($output)->toContain('"provider":"openai"');
});

test('formats TextDeltaEvent as SSE', function () {
    $event = new TextDeltaEvent(
        id: 'evt_2',
        timestamp: 1234567890,
        delta: 'Hello world',
        messageId: 'msg_1',
    );

    $output = $this->formatter->format($event);

    expect($output)->toContain('event: text-delta');
    expect($output)->toContain('"delta":"Hello world"');
});

test('formats StreamEndEvent as SSE', function () {
    $event = new StreamEndEvent(
        id: 'evt_3',
        timestamp: 1234567890,
        finishReason: FinishReason::Stop,
        usage: new Usage(10, 5),
    );

    $output = $this->formatter->format($event);

    expect($output)->toContain('event: stream-end');
    expect($output)->toContain('"finish_reason":"stop"');
    expect($output)->toContain('"prompt_tokens":10');
    expect($output)->toContain('"completion_tokens":5');
});

test('done() returns proper SSE done signal', function () {
    $output = $this->formatter->done();

    expect($output)->toBe("event: done\ndata: [DONE]\n\n");
});
