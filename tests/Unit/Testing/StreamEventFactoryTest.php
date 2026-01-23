<?php

declare(strict_types=1);

use Atlasphp\Atlas\Streaming\Events\StreamEndEvent;
use Atlasphp\Atlas\Streaming\Events\StreamStartEvent;
use Atlasphp\Atlas\Streaming\Events\TextDeltaEvent;
use Atlasphp\Atlas\Streaming\Events\ToolCallEndEvent;
use Atlasphp\Atlas\Streaming\Events\ToolCallStartEvent;
use Atlasphp\Atlas\Streaming\StreamResponse;
use Atlasphp\Atlas\Testing\Support\StreamEventFactory;

test('fromText creates StreamResponse', function () {
    $response = StreamEventFactory::fromText('Hello World');

    expect($response)->toBeInstanceOf(StreamResponse::class);
});

test('fromText generates correct events', function () {
    $response = StreamEventFactory::fromText('Hello');
    $events = iterator_to_array($response);

    expect($events[0])->toBeInstanceOf(StreamStartEvent::class);
    expect($events[1])->toBeInstanceOf(TextDeltaEvent::class);
    expect($events[1]->text)->toBe('Hello');
    expect(end($events))->toBeInstanceOf(StreamEndEvent::class);
});

test('fromText chunks text correctly', function () {
    $response = StreamEventFactory::fromText('HelloWorld', 5);
    $events = iterator_to_array($response);

    // Start + 2 text deltas + End
    $textEvents = array_filter($events, fn ($e) => $e instanceof TextDeltaEvent);
    expect($textEvents)->toHaveCount(2);
});

test('withToolCall creates tool call events', function () {
    $response = StreamEventFactory::withToolCall(
        toolName: 'search',
        arguments: ['query' => 'test'],
        result: '{"found": true}',
    );

    $events = iterator_to_array($response);
    $types = array_map(fn ($e) => $e::class, $events);

    expect($types)->toContain(ToolCallStartEvent::class);
    expect($types)->toContain(ToolCallEndEvent::class);
});

test('withToolCall includes text after tool call', function () {
    $response = StreamEventFactory::withToolCall(
        toolName: 'search',
        arguments: [],
        result: 'result',
        textAfter: 'Based on the search...',
    );

    $events = iterator_to_array($response);
    $textEvents = array_filter($events, fn ($e) => $e instanceof TextDeltaEvent);

    expect($textEvents)->not->toBeEmpty();
});

test('fromDeltas creates events from string array', function () {
    $deltas = ['Hello', ' ', 'World'];
    $response = StreamEventFactory::fromDeltas($deltas);
    $events = iterator_to_array($response);

    $textEvents = array_filter($events, fn ($e) => $e instanceof TextDeltaEvent);
    expect($textEvents)->toHaveCount(3);
});

test('fromEvents uses provided events', function () {
    $customEvents = [
        new StreamStartEvent('id1', time(), 'model', 'provider'),
        new TextDeltaEvent('id2', time(), 'Custom text'),
        new StreamEndEvent('id3', time()),
    ];

    $response = StreamEventFactory::fromEvents($customEvents);
    $events = iterator_to_array($response);

    expect($events)->toHaveCount(3);
    expect($events[1]->text)->toBe('Custom text');
});
