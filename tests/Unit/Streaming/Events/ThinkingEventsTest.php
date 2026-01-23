<?php

declare(strict_types=1);

use Atlasphp\Atlas\Streaming\Events\ThinkingCompleteEvent;
use Atlasphp\Atlas\Streaming\Events\ThinkingDeltaEvent;
use Atlasphp\Atlas\Streaming\Events\ThinkingStartEvent;
use Atlasphp\Atlas\Streaming\StreamEvent;

test('ThinkingStartEvent has correct type', function () {
    $event = new ThinkingStartEvent(
        id: 'evt_123',
        timestamp: 1234567890,
        reasoningId: 'reason_abc',
    );

    expect($event)->toBeInstanceOf(StreamEvent::class);
    expect($event->type())->toBe('thinking.start');
    expect($event->reasoningId)->toBe('reason_abc');
});

test('ThinkingStartEvent converts to array', function () {
    $event = new ThinkingStartEvent(
        id: 'evt_123',
        timestamp: 1234567890,
        reasoningId: 'reason_abc',
    );

    expect($event->toArray())->toBe([
        'id' => 'evt_123',
        'type' => 'thinking.start',
        'timestamp' => 1234567890,
        'reasoning_id' => 'reason_abc',
    ]);
});

test('ThinkingDeltaEvent has correct type', function () {
    $event = new ThinkingDeltaEvent(
        id: 'evt_456',
        timestamp: 1234567891,
        delta: 'Let me think about this...',
        reasoningId: 'reason_abc',
    );

    expect($event)->toBeInstanceOf(StreamEvent::class);
    expect($event->type())->toBe('thinking.delta');
    expect($event->delta)->toBe('Let me think about this...');
    expect($event->reasoningId)->toBe('reason_abc');
});

test('ThinkingDeltaEvent includes summary when provided', function () {
    $summary = ['key_points' => ['point1', 'point2']];
    $event = new ThinkingDeltaEvent(
        id: 'evt_456',
        timestamp: 1234567891,
        delta: 'Thinking...',
        reasoningId: 'reason_abc',
        summary: $summary,
    );

    expect($event->summary)->toBe($summary);
    expect($event->toArray()['summary'])->toBe($summary);
});

test('ThinkingCompleteEvent has correct type', function () {
    $event = new ThinkingCompleteEvent(
        id: 'evt_789',
        timestamp: 1234567892,
        reasoningId: 'reason_abc',
    );

    expect($event)->toBeInstanceOf(StreamEvent::class);
    expect($event->type())->toBe('thinking.complete');
    expect($event->reasoningId)->toBe('reason_abc');
});

test('ThinkingCompleteEvent includes summary when provided', function () {
    $summary = ['conclusion' => 'The answer is 42'];
    $event = new ThinkingCompleteEvent(
        id: 'evt_789',
        timestamp: 1234567892,
        reasoningId: 'reason_abc',
        summary: $summary,
    );

    expect($event->summary)->toBe($summary);
    expect($event->toArray()['summary'])->toBe($summary);
});
