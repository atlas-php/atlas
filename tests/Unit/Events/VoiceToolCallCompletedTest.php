<?php

declare(strict_types=1);

use Atlasphp\Atlas\Events\VoiceToolCallCompleted;

it('constructs with all properties', function () {
    $event = new VoiceToolCallCompleted(
        sessionId: 'rt_123',
        callId: 'call_456',
        name: 'get_weather',
        result: '{"temp": 72}',
        durationMs: 250,
    );

    expect($event->sessionId)->toBe('rt_123')
        ->and($event->callId)->toBe('call_456')
        ->and($event->name)->toBe('get_weather')
        ->and($event->result)->toBe('{"temp": 72}')
        ->and($event->durationMs)->toBe(250);
});

it('stores result as string', function () {
    $event = new VoiceToolCallCompleted(
        sessionId: 'rt_abc',
        callId: 'call_789',
        name: 'search',
        result: 'No results found',
        durationMs: 100,
    );

    expect($event->result)->toBe('No results found');
});

it('stores durationMs as integer', function () {
    $event = new VoiceToolCallCompleted(
        sessionId: 'rt_def',
        callId: 'call_012',
        name: 'calculate',
        result: '42',
        durationMs: 0,
    );

    expect($event->durationMs)->toBe(0);
});
