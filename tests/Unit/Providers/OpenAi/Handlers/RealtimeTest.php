<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\RealtimeTransport;
use Atlasphp\Atlas\Enums\TurnDetectionMode;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\OpenAi\Handlers\Realtime;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\RealtimeRequest;

function createOpenAiRealtimeHandler(?HttpClient $http = null): Realtime
{
    $config = new ProviderConfig(apiKey: 'test-key', baseUrl: 'https://api.openai.com/v1');
    $http ??= Mockery::mock(HttpClient::class);

    return new Realtime($config, $http);
}

it('creates a WebRTC session via POST to /realtime/sessions', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('post')
        ->once()
        ->withArgs(function (string $url, array $headers, array $body) {
            return str_contains($url, '/realtime/sessions')
                && $body['model'] === 'gpt-4o-realtime-preview'
                && $body['voice'] === 'alloy';
        })
        ->andReturn([
            'id' => 'sess_123',
            'client_secret' => [
                'value' => 'eph_token_123',
                'expires_at' => time() + 60,
            ],
        ]);

    $handler = createOpenAiRealtimeHandler($http);

    $session = $handler->createSession(new RealtimeRequest(
        model: 'gpt-4o-realtime-preview',
        instructions: 'Be helpful',
        voice: 'alloy',
        transport: RealtimeTransport::WebRtc,
    ));

    expect($session->sessionId)->toBe('sess_123');
    expect($session->provider)->toBe('openai');
    expect($session->transport)->toBe(RealtimeTransport::WebRtc);
    expect($session->ephemeralToken)->toBe('eph_token_123');
    expect($session->clientSecret)->toBe('eph_token_123');
    expect($session->isExpired())->toBeFalse();
});

it('creates a WebSocket session with connection URL', function () {
    $handler = createOpenAiRealtimeHandler();

    $session = $handler->createSession(new RealtimeRequest(
        model: 'gpt-4o-realtime-preview',
        instructions: null,
        voice: null,
        transport: RealtimeTransport::WebSocket,
    ));

    expect($session->transport)->toBe(RealtimeTransport::WebSocket);
    expect($session->connectionUrl)->toBe('wss://api.openai.com/v1/realtime?model=gpt-4o-realtime-preview');
    expect($session->ephemeralToken)->toBeNull();
});

it('includes turn detection config in session body', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('post')
        ->once()
        ->withArgs(function (string $url, array $headers, array $body) {
            return $body['turn_detection']['type'] === 'server_vad'
                && $body['turn_detection']['threshold'] === 0.7
                && $body['turn_detection']['silence_duration_ms'] === 300;
        })
        ->andReturn(['id' => 'sess_456']);

    $handler = createOpenAiRealtimeHandler($http);

    $handler->createSession(new RealtimeRequest(
        model: 'gpt-4o-realtime-preview',
        instructions: null,
        voice: null,
        transport: RealtimeTransport::WebRtc,
        turnDetection: TurnDetectionMode::ServerVad,
        vadThreshold: 0.7,
        vadSilenceDuration: 300,
    ));
});

it('includes tools in session body', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('post')
        ->once()
        ->withArgs(function (string $url, array $headers, array $body) {
            return isset($body['tools']) && count($body['tools']) === 1;
        })
        ->andReturn(['id' => 'sess_789']);

    $handler = createOpenAiRealtimeHandler($http);

    $handler->createSession(new RealtimeRequest(
        model: 'gpt-4o-realtime-preview',
        instructions: null,
        voice: null,
        tools: [['type' => 'function', 'name' => 'get_weather']],
    ));
});
