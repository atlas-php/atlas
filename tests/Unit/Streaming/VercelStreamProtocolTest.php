<?php

declare(strict_types=1);

use Atlasphp\Atlas\Streaming\VercelStreamProtocol;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\StreamStartEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
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
