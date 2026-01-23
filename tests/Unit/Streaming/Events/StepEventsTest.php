<?php

declare(strict_types=1);

use Atlasphp\Atlas\Streaming\Events\StepFinishEvent;
use Atlasphp\Atlas\Streaming\Events\StepStartEvent;
use Atlasphp\Atlas\Streaming\StreamEvent;

test('StepStartEvent has correct type', function () {
    $event = new StepStartEvent(
        id: 'evt_123',
        timestamp: 1234567890,
    );

    expect($event)->toBeInstanceOf(StreamEvent::class);
    expect($event->type())->toBe('step.start');
});

test('StepStartEvent converts to array', function () {
    $event = new StepStartEvent(
        id: 'evt_123',
        timestamp: 1234567890,
    );

    expect($event->toArray())->toBe([
        'id' => 'evt_123',
        'type' => 'step.start',
        'timestamp' => 1234567890,
    ]);
});

test('StepFinishEvent has correct type', function () {
    $event = new StepFinishEvent(
        id: 'evt_456',
        timestamp: 1234567891,
    );

    expect($event)->toBeInstanceOf(StreamEvent::class);
    expect($event->type())->toBe('step.finish');
});

test('StepFinishEvent converts to array', function () {
    $event = new StepFinishEvent(
        id: 'evt_456',
        timestamp: 1234567891,
    );

    expect($event->toArray())->toBe([
        'id' => 'evt_456',
        'type' => 'step.finish',
        'timestamp' => 1234567891,
    ]);
});
