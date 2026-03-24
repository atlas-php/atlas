<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\VoiceTransport;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\WebSocketConnection;
use Atlasphp\Atlas\Providers\Xai\Handlers\Voice;
use Atlasphp\Atlas\Requests\VoiceRequest;
use Atlasphp\Atlas\Responses\VoiceSession;

function createXaiVoiceHandler(?HttpClient $http = null): Voice
{
    $config = new ProviderConfig(apiKey: 'xai-key', baseUrl: 'https://api.x.ai/v1');

    if ($http === null) {
        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')
            ->withArgs(fn (string $url) => str_contains($url, '/realtime/client_secrets'))
            ->andReturn(['client_secret' => 'eph_xai_token_123']);
    }

    return new Voice($config, $http);
}

it('creates a session with ephemeral token and WebSocket URL', function () {
    $handler = createXaiVoiceHandler();

    $session = $handler->createSession(new VoiceRequest(
        model: 'grok-3-fast-realtime',
        instructions: 'Be helpful',
        voice: null,
    ));

    expect($session->provider)->toBe('xai');
    expect($session->model)->toBe('grok-3-fast-realtime');
    expect($session->connectionUrl)->toBe('wss://api.x.ai/v1/realtime');
    expect($session->ephemeralToken)->toBe('eph_xai_token_123');
    expect($session->transport)->toBe(VoiceTransport::WebSocket);
    expect($session->sessionId)->toMatch('/^rt_xai_[0-9a-f]{32}$/');
});

it('stores session config with model and instructions', function () {
    $handler = createXaiVoiceHandler();

    $session = $handler->createSession(new VoiceRequest(
        model: 'grok-3-fast-realtime',
        instructions: 'Be helpful',
        voice: 'eve',
    ));

    expect($session->sessionConfig)->toHaveKey('model', 'grok-3-fast-realtime');
    expect($session->sessionConfig)->toHaveKey('instructions', 'Be helpful');
    expect($session->sessionConfig)->toHaveKey('voice', 'eve');
});

it('always uses WebSocket transport for xAI', function () {
    $handler = createXaiVoiceHandler();

    $session = $handler->createSession(new VoiceRequest(
        model: 'grok-3-fast-realtime',
        instructions: null,
        voice: null,
    ));

    expect($session->transport)->toBe(VoiceTransport::WebSocket);
});

it('connect creates WebSocketConnection with correct URL and session ID', function () {
    $handler = createXaiVoiceHandler();

    $session = new VoiceSession(
        sessionId: 'rt_xai_test123',
        provider: 'xai',
        model: 'grok-3-fast-realtime',
        transport: VoiceTransport::WebSocket,
        connectionUrl: 'wss://api.x.ai/v1/realtime',
    );

    $connection = $handler->connect($session);

    expect($connection)->toBeInstanceOf(WebSocketConnection::class);
    expect($connection->sessionId)->toBe('rt_xai_test123');
});
