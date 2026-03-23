<?php

declare(strict_types=1);

use Atlasphp\Atlas\Events\VoiceTranscriptDelta;
use Illuminate\Broadcasting\Channel;

it('constructs with all properties', function () {
    $event = new VoiceTranscriptDelta(
        sessionId: 'rt_123',
        text: 'Hello',
        role: 'assistant',
        channelName: 'voice.rt_123',
    );

    expect($event->sessionId)->toBe('rt_123');
    expect($event->text)->toBe('Hello');
    expect($event->role)->toBe('assistant');
    expect($event->channelName)->toBe('voice.rt_123');
});

it('broadcasts on correct channel', function () {
    $event = new VoiceTranscriptDelta(
        sessionId: 'rt_123',
        text: 'Hello',
        role: 'user',
        channelName: 'voice.rt_123',
    );

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(Channel::class);
    expect($channels[0]->name)->toBe('voice.rt_123');
});

it('broadcasts with correct event name', function () {
    $event = new VoiceTranscriptDelta(
        sessionId: 'rt_123',
        text: 'Hello',
        role: 'assistant',
        channelName: 'voice.rt_123',
    );

    expect($event->broadcastAs())->toBe('voice.transcript.delta');
});
