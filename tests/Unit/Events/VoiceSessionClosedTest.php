<?php

declare(strict_types=1);

use Atlasphp\Atlas\Events\VoiceSessionClosed;

it('constructs with all properties', function () {
    $event = new VoiceSessionClosed(
        provider: 'openai',
        sessionId: 'rt_123',
        durationMs: 5000,
    );

    expect($event->provider)->toBe('openai');
    expect($event->sessionId)->toBe('rt_123');
    expect($event->durationMs)->toBe(5000);
});

it('allows null duration', function () {
    $event = new VoiceSessionClosed(
        provider: 'openai',
        sessionId: 'rt_123',
    );

    expect($event->durationMs)->toBeNull();
});
