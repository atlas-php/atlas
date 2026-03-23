<?php

declare(strict_types=1);

use Atlasphp\Atlas\Events\RealtimeToolCallRequested;

it('constructs with all properties', function () {
    $event = new RealtimeToolCallRequested(
        sessionId: 'rt_123',
        callId: 'call_456',
        name: 'get_weather',
        arguments: '{"location": "NYC"}',
    );

    expect($event->sessionId)->toBe('rt_123');
    expect($event->callId)->toBe('call_456');
    expect($event->name)->toBe('get_weather');
    expect($event->arguments)->toBe('{"location": "NYC"}');
});
