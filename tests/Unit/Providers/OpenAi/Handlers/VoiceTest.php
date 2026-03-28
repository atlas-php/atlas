<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\TurnDetectionMode;
use Atlasphp\Atlas\Enums\VoiceTransport;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\OpenAi\Handlers\Voice;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\WebSocketConnection;
use Atlasphp\Atlas\Requests\VoiceRequest;
use Atlasphp\Atlas\Responses\VoiceSession;

function createOpenAiVoiceHandler(?HttpClient $http = null): Voice
{
    $config = new ProviderConfig(apiKey: 'test-key', baseUrl: 'https://api.openai.com/v1');
    $http ??= Mockery::mock(HttpClient::class);

    return new Voice($config, $http);
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

    $handler = createOpenAiVoiceHandler($http);

    $session = $handler->createSession(new VoiceRequest(
        model: 'gpt-4o-realtime-preview',
        instructions: 'Be helpful',
        voice: 'alloy',
        transport: VoiceTransport::WebRtc,
    ));

    expect($session->sessionId)->toBe('sess_123');
    expect($session->provider)->toBe('openai');
    expect($session->transport)->toBe(VoiceTransport::WebRtc);
    expect($session->ephemeralToken)->toBe('eph_token_123');
    expect($session->clientSecret)->toBe('eph_token_123');
    expect($session->isExpired())->toBeFalse();
});

it('creates a WebSocket session with connection URL', function () {
    $handler = createOpenAiVoiceHandler();

    $session = $handler->createSession(new VoiceRequest(
        model: 'gpt-4o-realtime-preview',
        instructions: null,
        voice: null,
        transport: VoiceTransport::WebSocket,
    ));

    expect($session->transport)->toBe(VoiceTransport::WebSocket);
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

    $handler = createOpenAiVoiceHandler($http);

    $handler->createSession(new VoiceRequest(
        model: 'gpt-4o-realtime-preview',
        instructions: null,
        voice: null,
        transport: VoiceTransport::WebRtc,
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

    $handler = createOpenAiVoiceHandler($http);

    $handler->createSession(new VoiceRequest(
        model: 'gpt-4o-realtime-preview',
        instructions: null,
        voice: null,
        transport: VoiceTransport::WebRtc,
        tools: [['type' => 'function', 'name' => 'get_weather']],
    ));
});

it('includes input audio transcription in session body', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('post')
        ->once()
        ->withArgs(function (string $url, array $headers, array $body) {
            return isset($body['input_audio_transcription'])
                && $body['input_audio_transcription']['model'] === 'whisper-1';
        })
        ->andReturn(['id' => 'sess_transcribe']);

    $handler = createOpenAiVoiceHandler($http);

    $handler->createSession(new VoiceRequest(
        model: 'gpt-4o-realtime-preview',
        instructions: null,
        voice: null,
        transport: VoiceTransport::WebRtc,
        inputAudioTranscription: 'whisper-1',
    ));
});

it('omits input audio transcription when not set', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('post')
        ->once()
        ->withArgs(function (string $url, array $headers, array $body) {
            return ! isset($body['input_audio_transcription']);
        })
        ->andReturn(['id' => 'sess_no_transcribe']);

    $handler = createOpenAiVoiceHandler($http);

    $handler->createSession(new VoiceRequest(
        model: 'gpt-4o-realtime-preview',
        instructions: null,
        voice: null,
        transport: VoiceTransport::WebRtc,
    ));
});

it('connect creates WebSocketConnection with correct URL and session ID', function () {
    $handler = createOpenAiVoiceHandler();

    $session = new VoiceSession(
        sessionId: 'rt_openai_test456',
        provider: 'openai',
        model: 'gpt-4o-realtime-preview',
        transport: VoiceTransport::WebSocket,
        connectionUrl: 'wss://api.openai.com/v1/realtime?model=gpt-4o-realtime-preview',
    );

    $connection = $handler->connect($session);

    expect($connection)->toBeInstanceOf(WebSocketConnection::class);
    expect($connection->sessionId)->toBe('rt_openai_test456');
});

it('connect builds WebSocket URL from model when connectionUrl is null', function () {
    $handler = createOpenAiVoiceHandler();

    $session = new VoiceSession(
        sessionId: 'rt_openai_fallback',
        provider: 'openai',
        model: 'gpt-4o-realtime-preview',
        transport: VoiceTransport::WebRtc,
    );

    $connection = $handler->connect($session);

    expect($connection)->toBeInstanceOf(WebSocketConnection::class);
    expect($connection->sessionId)->toBe('rt_openai_fallback');
});
