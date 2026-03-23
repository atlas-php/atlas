<?php

declare(strict_types=1);

use Atlasphp\Atlas\Events\RealtimeAudioDelta;
use Illuminate\Broadcasting\Channel;

it('constructs with all properties', function () {
    $event = new RealtimeAudioDelta(
        sessionId: 'rt_123',
        audioData: 'base64audio',
        channelName: 'realtime.rt_123',
    );

    expect($event->sessionId)->toBe('rt_123');
    expect($event->audioData)->toBe('base64audio');
    expect($event->channelName)->toBe('realtime.rt_123');
});

it('broadcasts on correct channel', function () {
    $event = new RealtimeAudioDelta(
        sessionId: 'rt_123',
        audioData: 'base64audio',
        channelName: 'realtime.rt_123',
    );

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(Channel::class);
    expect($channels[0]->name)->toBe('realtime.rt_123');
});

it('broadcasts with correct event name', function () {
    $event = new RealtimeAudioDelta(
        sessionId: 'rt_123',
        audioData: 'base64audio',
        channelName: 'realtime.rt_123',
    );

    expect($event->broadcastAs())->toBe('realtime.audio.delta');
});
