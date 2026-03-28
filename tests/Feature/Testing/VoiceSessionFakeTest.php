<?php

declare(strict_types=1);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\VoiceTransport;
use Atlasphp\Atlas\Responses\VoiceSession;
use Atlasphp\Atlas\Testing\VoiceSessionFake;

it('returns fake session from Atlas::fake()', function () {
    Atlas::fake([
        VoiceSessionFake::make()
            ->withSessionId('test-session')
            ->withProvider('openai')
            ->withModel('gpt-4o-realtime-preview'),
    ]);

    $session = Atlas::voice('openai', 'gpt-4o-realtime-preview')
        ->instructions('Hello')
        ->createSession();

    expect($session)->toBeInstanceOf(VoiceSession::class);
    expect($session->sessionId)->toBe('test-session');
    expect($session->provider)->toBe('openai');
});

it('records voice requests', function () {
    $fake = Atlas::fake([
        VoiceSessionFake::make(),
    ]);

    Atlas::voice('openai', 'gpt-4o-realtime-preview')
        ->instructions('Test')
        ->createSession();

    $fake->assertSent();
    $fake->assertMethodCalled('voice');
});

it('VoiceSessionFake produces valid VoiceSession', function () {
    $session = VoiceSessionFake::make()
        ->withSessionId('custom-id')
        ->withTransport(VoiceTransport::WebSocket)
        ->withConnectionUrl('wss://example.com/realtime')
        ->toResponse();

    expect($session)->toBeInstanceOf(VoiceSession::class);
    expect($session->sessionId)->toBe('custom-id');
    expect($session->transport)->toBe(VoiceTransport::WebSocket);
    expect($session->connectionUrl)->toBe('wss://example.com/realtime');
});
