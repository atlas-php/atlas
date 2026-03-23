<?php

declare(strict_types=1);

use Atlasphp\Atlas\Events\RealtimeTranscriptDelta;
use Illuminate\Broadcasting\Channel;

it('constructs with all properties', function () {
    $event = new RealtimeTranscriptDelta(
        sessionId: 'rt_123',
        text: 'Hello',
        role: 'assistant',
        channelName: 'realtime.rt_123',
    );

    expect($event->sessionId)->toBe('rt_123');
    expect($event->text)->toBe('Hello');
    expect($event->role)->toBe('assistant');
    expect($event->channelName)->toBe('realtime.rt_123');
});

it('broadcasts on correct channel', function () {
    $event = new RealtimeTranscriptDelta(
        sessionId: 'rt_123',
        text: 'Hello',
        role: 'user',
        channelName: 'realtime.rt_123',
    );

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(Channel::class);
    expect($channels[0]->name)->toBe('realtime.rt_123');
});

it('broadcasts with correct event name', function () {
    $event = new RealtimeTranscriptDelta(
        sessionId: 'rt_123',
        text: 'Hello',
        role: 'assistant',
        channelName: 'realtime.rt_123',
    );

    expect($event->broadcastAs())->toBe('realtime.transcript.delta');
});
