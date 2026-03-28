<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\VoiceTransport;
use Atlasphp\Atlas\Responses\VoiceSession;
use DateTimeImmutable;

it('reports not expired when expiresAt is null', function () {
    $session = new VoiceSession(
        sessionId: 'rt_123',
        provider: 'openai',
        model: 'gpt-4o-realtime-preview',
        transport: VoiceTransport::WebRtc,
    );

    expect($session->isExpired())->toBeFalse();
});

it('reports not expired when expiresAt is in the future', function () {
    $session = new VoiceSession(
        sessionId: 'rt_123',
        provider: 'openai',
        model: 'gpt-4o-realtime-preview',
        transport: VoiceTransport::WebRtc,
        expiresAt: (new DateTimeImmutable)->modify('+1 hour'),
    );

    expect($session->isExpired())->toBeFalse();
});

it('reports expired when expiresAt is in the past', function () {
    $session = new VoiceSession(
        sessionId: 'rt_123',
        provider: 'openai',
        model: 'gpt-4o-realtime-preview',
        transport: VoiceTransport::WebRtc,
        expiresAt: (new DateTimeImmutable)->modify('-1 hour'),
    );

    expect($session->isExpired())->toBeTrue();
});

it('toClientPayload excludes clientSecret', function () {
    $session = new VoiceSession(
        sessionId: 'rt_123',
        provider: 'openai',
        model: 'gpt-4o-realtime-preview',
        transport: VoiceTransport::WebRtc,
        ephemeralToken: 'eph_token',
        clientSecret: 'secret_value',
        expiresAt: new DateTimeImmutable('2025-01-01T00:00:00Z'),
    );

    $payload = $session->toClientPayload();

    expect($payload)->toHaveKey('session_id', 'rt_123');
    expect($payload)->toHaveKey('provider', 'openai');
    expect($payload)->toHaveKey('model', 'gpt-4o-realtime-preview');
    expect($payload)->toHaveKey('transport', 'webrtc');
    expect($payload)->toHaveKey('ephemeral_token', 'eph_token');
    expect($payload)->toHaveKey('expires_at');
    expect($payload)->not->toHaveKey('client_secret');
    expect($payload)->not->toHaveKey('clientSecret');
});

it('toClientPayload omits null values', function () {
    $session = new VoiceSession(
        sessionId: 'rt_123',
        provider: 'openai',
        model: 'gpt-4o-realtime-preview',
        transport: VoiceTransport::WebSocket,
    );

    $payload = $session->toClientPayload();

    expect($payload)->not->toHaveKey('ephemeral_token');
    expect($payload)->not->toHaveKey('connection_url');
    expect($payload)->not->toHaveKey('expires_at');
});

it('withEndpoints returns new instance with endpoints set', function () {
    $session = new VoiceSession(
        sessionId: 'rt_123',
        provider: 'openai',
        model: 'gpt-4o-realtime-preview',
        transport: VoiceTransport::WebRtc,
        ephemeralToken: 'eph_token',
        clientSecret: 'secret',
    );

    $updated = $session->withEndpoints('/tool', '/transcript');

    // New instance
    expect($updated)->not->toBe($session);
    // Endpoints set
    expect($updated->toolEndpoint)->toBe('/tool');
    expect($updated->transcriptEndpoint)->toBe('/transcript');
    // Original fields preserved
    expect($updated->sessionId)->toBe('rt_123');
    expect($updated->provider)->toBe('openai');
    expect($updated->ephemeralToken)->toBe('eph_token');
    expect($updated->clientSecret)->toBe('secret');
    expect($updated->transport)->toBe(VoiceTransport::WebRtc);
});

it('withEndpoints accepts null endpoints', function () {
    $session = new VoiceSession(
        sessionId: 'rt_456',
        provider: 'xai',
        model: 'grok-3-fast-realtime',
        transport: VoiceTransport::WebSocket,
    );

    $updated = $session->withEndpoints(null, null);

    expect($updated->toolEndpoint)->toBeNull();
    expect($updated->transcriptEndpoint)->toBeNull();
    expect($updated->sessionId)->toBe('rt_456');
});

it('toClientPayload includes tool and transcript endpoints', function () {
    $session = new VoiceSession(
        sessionId: 'rt_789',
        provider: 'xai',
        model: 'grok-3-fast-realtime',
        transport: VoiceTransport::WebSocket,
        toolEndpoint: '/atlas/voice/rt_789/tool',
        transcriptEndpoint: '/atlas/voice/rt_789/transcript',
    );

    $payload = $session->toClientPayload();

    expect($payload)->toHaveKey('tool_endpoint', '/atlas/voice/rt_789/tool');
    expect($payload)->toHaveKey('transcript_endpoint', '/atlas/voice/rt_789/transcript');
});

it('toClientPayload includes session_config when non-empty', function () {
    $session = new VoiceSession(
        sessionId: 'rt_cfg',
        provider: 'xai',
        model: 'grok-3-fast-realtime',
        transport: VoiceTransport::WebSocket,
        sessionConfig: ['voice' => 'eve', 'modalities' => ['text', 'audio']],
    );

    $payload = $session->toClientPayload();

    expect($payload)->toHaveKey('session_config');
    expect($payload['session_config']['voice'])->toBe('eve');
});

it('toClientPayload excludes empty session_config', function () {
    $session = new VoiceSession(
        sessionId: 'rt_empty',
        provider: 'openai',
        model: 'gpt-4o-realtime-preview',
        transport: VoiceTransport::WebRtc,
    );

    $payload = $session->toClientPayload();

    expect($payload)->not->toHaveKey('session_config');
});
