<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\RealtimeTransport;
use Atlasphp\Atlas\Events\RealtimeSessionCreated;

it('constructs with all properties', function () {
    $event = new RealtimeSessionCreated(
        provider: 'openai',
        model: 'gpt-4o-realtime-preview',
        sessionId: 'rt_123',
        transport: RealtimeTransport::WebRtc,
    );

    expect($event->provider)->toBe('openai');
    expect($event->model)->toBe('gpt-4o-realtime-preview');
    expect($event->sessionId)->toBe('rt_123');
    expect($event->transport)->toBe(RealtimeTransport::WebRtc);
});
