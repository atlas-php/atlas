<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\SseParser;
use GuzzleHttp\Psr7\Stream;

function makeSseStream(string $content): object
{
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $content);
    rewind($stream);

    $body = new Stream($stream);

    return new class($body)
    {
        public function __construct(private readonly Stream $body) {}

        public function getBody(): Stream
        {
            return $this->body;
        }
    };
}

// ─── Basic Parsing ──────────────────────────────────────────────────────

it('parses a single data-only SSE event', function () {
    $raw = makeSseStream("data: {\"text\":\"hello\"}\n\n");

    $events = iterator_to_array(SseParser::parse($raw));

    expect($events)->toHaveCount(1)
        ->and($events[0]['event'])->toBe('')
        ->and($events[0]['data'])->toBe(['text' => 'hello']);
});

it('parses multiple SSE events', function () {
    $raw = makeSseStream(
        "data: {\"text\":\"one\"}\n\n"
        ."data: {\"text\":\"two\"}\n\n"
        ."data: {\"text\":\"three\"}\n\n"
    );

    $events = iterator_to_array(SseParser::parse($raw));

    expect($events)->toHaveCount(3)
        ->and($events[0]['data']['text'])->toBe('one')
        ->and($events[1]['data']['text'])->toBe('two')
        ->and($events[2]['data']['text'])->toBe('three');
});

// ─── Named Events ───────────────────────────────────────────────────────

it('parses named events', function () {
    $raw = makeSseStream(
        "event: message_start\n"
        ."data: {\"type\":\"start\"}\n\n"
        ."event: content_block_delta\n"
        ."data: {\"type\":\"delta\",\"text\":\"hi\"}\n\n"
    );

    $events = iterator_to_array(SseParser::parse($raw));

    expect($events)->toHaveCount(2)
        ->and($events[0]['event'])->toBe('message_start')
        ->and($events[0]['data']['type'])->toBe('start')
        ->and($events[1]['event'])->toBe('content_block_delta')
        ->and($events[1]['data']['text'])->toBe('hi');
});

it('resets event name between blocks', function () {
    $raw = makeSseStream(
        "event: named\n"
        ."data: {\"a\":1}\n\n"
        ."data: {\"b\":2}\n\n"
    );

    $events = iterator_to_array(SseParser::parse($raw));

    expect($events)->toHaveCount(2)
        ->and($events[0]['event'])->toBe('named')
        ->and($events[1]['event'])->toBe('');
});

// ─── [DONE] Sentinel ────────────────────────────────────────────────────

it('stops at [DONE] sentinel', function () {
    $raw = makeSseStream(
        "data: {\"text\":\"before\"}\n\n"
        ."data: [DONE]\n\n"
        ."data: {\"text\":\"after\"}\n\n"
    );

    $events = iterator_to_array(SseParser::parse($raw));

    expect($events)->toHaveCount(1)
        ->and($events[0]['data']['text'])->toBe('before');
});

it('handles [DONE] as the only event', function () {
    $raw = makeSseStream("data: [DONE]\n\n");

    $events = iterator_to_array(SseParser::parse($raw));

    expect($events)->toHaveCount(0);
});

// ─── Invalid JSON ───────────────────────────────────────────────────────

it('skips lines with invalid JSON', function () {
    $raw = makeSseStream(
        "data: not-json\n\n"
        ."data: {\"valid\":true}\n\n"
    );

    $events = iterator_to_array(SseParser::parse($raw));

    expect($events)->toHaveCount(1)
        ->and($events[0]['data']['valid'])->toBeTrue();
});

it('skips null JSON decode results', function () {
    $raw = makeSseStream(
        "data: null\n\n"
        ."data: {\"ok\":1}\n\n"
    );

    $events = iterator_to_array(SseParser::parse($raw));

    expect($events)->toHaveCount(1);
});

// ─── Empty & Edge Cases ─────────────────────────────────────────────────

it('handles empty stream', function () {
    $raw = makeSseStream('');

    $events = iterator_to_array(SseParser::parse($raw));

    expect($events)->toHaveCount(0);
});

it('ignores non-event non-data lines', function () {
    $raw = makeSseStream(
        ": comment line\n"
        ."id: 123\n"
        ."retry: 5000\n"
        ."data: {\"text\":\"hello\"}\n\n"
    );

    $events = iterator_to_array(SseParser::parse($raw));

    expect($events)->toHaveCount(1)
        ->and($events[0]['data']['text'])->toBe('hello');
});

it('handles large payloads that span multiple buffer reads', function () {
    // Create a payload larger than 8192 bytes (the buffer size)
    $largeText = str_repeat('x', 10000);
    $raw = makeSseStream("data: {\"text\":\"{$largeText}\"}\n\n");

    $events = iterator_to_array(SseParser::parse($raw));

    expect($events)->toHaveCount(1)
        ->and(strlen($events[0]['data']['text']))->toBe(10000);
});

it('handles multiple events within a single buffer read', function () {
    // Small events that all fit in one 8192 byte buffer
    $content = '';
    for ($i = 0; $i < 50; $i++) {
        $content .= "data: {\"i\":{$i}}\n\n";
    }

    $raw = makeSseStream($content);
    $events = iterator_to_array(SseParser::parse($raw));

    expect($events)->toHaveCount(50)
        ->and($events[0]['data']['i'])->toBe(0)
        ->and($events[49]['data']['i'])->toBe(49);
});

it('handles event name with named event followed by [DONE]', function () {
    $raw = makeSseStream(
        "event: response.completed\n"
        ."data: {\"status\":\"done\"}\n\n"
        ."data: [DONE]\n\n"
    );

    $events = iterator_to_array(SseParser::parse($raw));

    expect($events)->toHaveCount(1)
        ->and($events[0]['event'])->toBe('response.completed')
        ->and($events[0]['data']['status'])->toBe('done');
});

it('handles complex nested JSON data', function () {
    $json = json_encode([
        'choices' => [
            ['delta' => ['content' => 'Hello', 'tool_calls' => [['id' => 'tc1']]]],
        ],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
    ]);

    $raw = makeSseStream("data: {$json}\n\n");

    $events = iterator_to_array(SseParser::parse($raw));

    expect($events)->toHaveCount(1)
        ->and($events[0]['data']['choices'][0]['delta']['content'])->toBe('Hello')
        ->and($events[0]['data']['usage']['prompt_tokens'])->toBe(10);
});
