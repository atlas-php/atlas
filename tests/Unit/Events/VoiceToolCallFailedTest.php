<?php

declare(strict_types=1);

use Atlasphp\Atlas\Events\VoiceToolCallFailed;

it('constructs with all properties', function () {
    $event = new VoiceToolCallFailed(
        sessionId: 'rt_123',
        callId: 'call_456',
        name: 'get_weather',
        error: 'API timeout',
        durationMs: 5000,
    );

    expect($event->sessionId)->toBe('rt_123')
        ->and($event->callId)->toBe('call_456')
        ->and($event->name)->toBe('get_weather')
        ->and($event->error)->toBe('API timeout')
        ->and($event->durationMs)->toBe(5000);
});

it('stores error message as string', function () {
    $event = new VoiceToolCallFailed(
        sessionId: 'rt_abc',
        callId: 'call_789',
        name: 'search',
        error: 'Connection refused',
        durationMs: 3000,
    );

    expect($event->error)->toBe('Connection refused');
});

it('stores durationMs as integer', function () {
    $event = new VoiceToolCallFailed(
        sessionId: 'rt_def',
        callId: 'call_012',
        name: 'calculate',
        error: 'Division by zero',
        durationMs: 0,
    );

    expect($event->durationMs)->toBe(0);
});
