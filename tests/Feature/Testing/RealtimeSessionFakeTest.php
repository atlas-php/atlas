<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\RealtimeTransport;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Responses\RealtimeSession;
use Atlasphp\Atlas\Testing\RealtimeSessionFake;

it('returns fake session from Atlas::fake()', function () {
    Atlas::fake([
        RealtimeSessionFake::make()
            ->withSessionId('test-session')
            ->withProvider('openai')
            ->withModel('gpt-4o-realtime-preview'),
    ]);

    $session = Atlas::realtime('openai', 'gpt-4o-realtime-preview')
        ->instructions('Hello')
        ->createSession();

    expect($session)->toBeInstanceOf(RealtimeSession::class);
    expect($session->sessionId)->toBe('test-session');
    expect($session->provider)->toBe('openai');
});

it('records realtime requests', function () {
    $fake = Atlas::fake([
        RealtimeSessionFake::make(),
    ]);

    Atlas::realtime('openai', 'gpt-4o-realtime-preview')
        ->instructions('Test')
        ->createSession();

    $fake->assertSent();
    $fake->assertMethodCalled('realtime');
});

it('RealtimeSessionFake produces valid RealtimeSession', function () {
    $session = RealtimeSessionFake::make()
        ->withSessionId('custom-id')
        ->withTransport(RealtimeTransport::WebSocket)
        ->withConnectionUrl('wss://example.com/realtime')
        ->toResponse();

    expect($session)->toBeInstanceOf(RealtimeSession::class);
    expect($session->sessionId)->toBe('custom-id');
    expect($session->transport)->toBe(RealtimeTransport::WebSocket);
    expect($session->connectionUrl)->toBe('wss://example.com/realtime');
});
