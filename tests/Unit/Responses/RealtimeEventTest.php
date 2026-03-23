<?php

declare(strict_types=1);

use Atlasphp\Atlas\Responses\RealtimeEvent;

it('constructs with type only', function () {
    $event = new RealtimeEvent(type: 'session.created');

    expect($event->type)->toBe('session.created');
    expect($event->eventId)->toBeNull();
    expect($event->data)->toBe([]);
});

it('constructs with all properties', function () {
    $event = new RealtimeEvent(
        type: 'response.audio.delta',
        eventId: 'evt_123',
        data: ['delta' => 'base64audio'],
    );

    expect($event->type)->toBe('response.audio.delta');
    expect($event->eventId)->toBe('evt_123');
    expect($event->data)->toBe(['delta' => 'base64audio']);
});
