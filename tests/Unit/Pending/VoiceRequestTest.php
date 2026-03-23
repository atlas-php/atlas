<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Enums\TurnDetectionMode;
use Atlasphp\Atlas\Enums\VoiceTransport;
use Atlasphp\Atlas\Events\ModalityCompleted;
use Atlasphp\Atlas\Events\VoiceSessionCreated;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Pending\VoiceRequest;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Responses\VoiceSession;
use Illuminate\Support\Facades\Event;

function createRealtimePending(?Driver $driver = null): VoiceRequest
{
    $driver ??= Mockery::mock(Driver::class);
    $registry = Mockery::mock(ProviderRegistryContract::class);
    $registry->shouldReceive('resolve')->with('openai')->andReturn($driver);

    return new VoiceRequest('openai', 'gpt-4o-realtime-preview', $registry);
}

it('returns $this from fluent methods', function () {
    $pending = createRealtimePending();

    expect($pending->instructions('hello'))->toBe($pending);
    expect($pending->withVoice('alloy'))->toBe($pending);
    expect($pending->viaWebRtc())->toBe($pending);
    expect($pending->viaWebSocket())->toBe($pending);
    expect($pending->withServerVad())->toBe($pending);
    expect($pending->withManualTurnDetection())->toBe($pending);
    expect($pending->withTools([]))->toBe($pending);
    expect($pending->withTemperature(0.7))->toBe($pending);
    expect($pending->withMaxResponseTokens(4096))->toBe($pending);
    expect($pending->withInputFormat('pcm16'))->toBe($pending);
    expect($pending->withOutputFormat('pcm16'))->toBe($pending);
    expect($pending->withProviderOptions([]))->toBe($pending);
    expect($pending->withInputTranscription('whisper-1'))->toBe($pending);
});

it('builds request DTO with correct values', function () {
    $request = createRealtimePending()
        ->instructions('Be helpful')
        ->withVoice('shimmer')
        ->viaWebSocket()
        ->withManualTurnDetection()
        ->withTemperature(0.9)
        ->withMaxResponseTokens(2048)
        ->withInputFormat('g711_ulaw')
        ->withOutputFormat('g711_alaw')
        ->withProviderOptions(['key' => 'val'])
        ->buildRequest();

    expect($request->model)->toBe('gpt-4o-realtime-preview');
    expect($request->instructions)->toBe('Be helpful');
    expect($request->voice)->toBe('shimmer');
    expect($request->transport)->toBe(VoiceTransport::WebSocket);
    expect($request->turnDetection)->toBe(TurnDetectionMode::Manual);
    expect($request->temperature)->toBe(0.9);
    expect($request->maxResponseTokens)->toBe(2048);
    expect($request->inputAudioFormat)->toBe('g711_ulaw');
    expect($request->outputAudioFormat)->toBe('g711_alaw');
    expect($request->providerOptions)->toBe(['key' => 'val']);
});

it('dispatches createSession to driver and fires VoiceSessionCreated', function () {
    Event::fake();

    $session = new VoiceSession(
        sessionId: 'rt_123',
        provider: 'openai',
        model: 'gpt-4o-realtime-preview',
        transport: VoiceTransport::WebRtc,
    );

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(voice: true));
    $driver->shouldReceive('createVoiceSession')->once()->andReturn($session);

    $result = createRealtimePending($driver)->createSession();

    expect($result)->toBe($session);

    Event::assertDispatched(
        VoiceSessionCreated::class,
        fn ($e) => $e->sessionId === 'rt_123' && $e->transport === VoiceTransport::WebRtc
    );
});

it('throws when realtime capability is unsupported', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities);
    $driver->shouldReceive('name')->andReturn('test');

    createRealtimePending($driver)->createSession();
})->throws(UnsupportedFeatureException::class);

it('fires ModalityCompleted on createSession error', function () {
    Event::fake();

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(voice: true));
    $driver->shouldReceive('createVoiceSession')->andThrow(new RuntimeException('fail'));

    try {
        createRealtimePending($driver)->createSession();
    } catch (RuntimeException) {
    }

    Event::assertDispatched(
        ModalityCompleted::class,
        fn ($e) => $e->modality === Modality::Voice && $e->usage === null
    );
});

it('withInputTranscription flows through to request DTO', function () {
    $request = createRealtimePending()
        ->withInputTranscription('gpt-4o-transcribe')
        ->buildRequest();

    expect($request->inputAudioTranscription)->toBe('gpt-4o-transcribe');
});

it('withInputTranscription defaults to whisper-1', function () {
    $request = createRealtimePending()
        ->withInputTranscription()
        ->buildRequest();

    expect($request->inputAudioTranscription)->toBe('whisper-1');
});

it('withServerVad sets threshold and silence duration', function () {
    $request = createRealtimePending()
        ->withServerVad(0.7, 300)
        ->buildRequest();

    expect($request->turnDetection)->toBe(TurnDetectionMode::ServerVad);
    expect($request->vadThreshold)->toBe(0.7);
    expect($request->vadSilenceDuration)->toBe(300);
});
