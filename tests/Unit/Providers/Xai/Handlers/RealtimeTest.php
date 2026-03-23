<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\RealtimeTransport;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\Xai\Handlers\Realtime;
use Atlasphp\Atlas\Requests\RealtimeRequest;

function createXaiRealtimeHandler(): Realtime
{
    $config = new ProviderConfig(apiKey: 'xai-key', baseUrl: 'https://api.x.ai/v1');
    $http = Mockery::mock(HttpClient::class);

    return new Realtime($config, $http);
}

it('creates a session with WebSocket connection URL', function () {
    $handler = createXaiRealtimeHandler();

    $session = $handler->createSession(new RealtimeRequest(
        model: 'grok-2-realtime',
        instructions: 'Be helpful',
        voice: null,
    ));

    expect($session->provider)->toBe('xai');
    expect($session->model)->toBe('grok-2-realtime');
    expect($session->connectionUrl)->toBe('wss://api.x.ai/v1/realtime');
    expect($session->sessionId)->toMatch('/^rt_xai_[0-9a-f]{32}$/');
});

it('stores session config with model and instructions', function () {
    $handler = createXaiRealtimeHandler();

    $session = $handler->createSession(new RealtimeRequest(
        model: 'grok-2-realtime',
        instructions: 'Be helpful',
        voice: 'aurora',
    ));

    expect($session->sessionConfig)->toHaveKey('model', 'grok-2-realtime');
    expect($session->sessionConfig)->toHaveKey('instructions', 'Be helpful');
    expect($session->sessionConfig)->toHaveKey('voice', 'aurora');
});

it('preserves transport from request', function () {
    $handler = createXaiRealtimeHandler();

    $session = $handler->createSession(new RealtimeRequest(
        model: 'grok-2-realtime',
        instructions: null,
        voice: null,
        transport: RealtimeTransport::WebSocket,
    ));

    expect($session->transport)->toBe(RealtimeTransport::WebSocket);
});
