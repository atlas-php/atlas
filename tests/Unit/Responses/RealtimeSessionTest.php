<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\RealtimeTransport;
use Atlasphp\Atlas\Responses\RealtimeSession;
use DateTimeImmutable;

it('reports not expired when expiresAt is null', function () {
    $session = new RealtimeSession(
        sessionId: 'rt_123',
        provider: 'openai',
        model: 'gpt-4o-realtime-preview',
        transport: RealtimeTransport::WebRtc,
    );

    expect($session->isExpired())->toBeFalse();
});

it('reports not expired when expiresAt is in the future', function () {
    $session = new RealtimeSession(
        sessionId: 'rt_123',
        provider: 'openai',
        model: 'gpt-4o-realtime-preview',
        transport: RealtimeTransport::WebRtc,
        expiresAt: (new DateTimeImmutable)->modify('+1 hour'),
    );

    expect($session->isExpired())->toBeFalse();
});

it('reports expired when expiresAt is in the past', function () {
    $session = new RealtimeSession(
        sessionId: 'rt_123',
        provider: 'openai',
        model: 'gpt-4o-realtime-preview',
        transport: RealtimeTransport::WebRtc,
        expiresAt: (new DateTimeImmutable)->modify('-1 hour'),
    );

    expect($session->isExpired())->toBeTrue();
});

it('toClientPayload excludes clientSecret', function () {
    $session = new RealtimeSession(
        sessionId: 'rt_123',
        provider: 'openai',
        model: 'gpt-4o-realtime-preview',
        transport: RealtimeTransport::WebRtc,
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
    $session = new RealtimeSession(
        sessionId: 'rt_123',
        provider: 'openai',
        model: 'gpt-4o-realtime-preview',
        transport: RealtimeTransport::WebSocket,
    );

    $payload = $session->toClientPayload();

    expect($payload)->not->toHaveKey('ephemeral_token');
    expect($payload)->not->toHaveKey('connection_url');
    expect($payload)->not->toHaveKey('expires_at');
});
