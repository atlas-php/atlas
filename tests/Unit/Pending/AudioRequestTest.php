<?php

declare(strict_types=1);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Events\ModalityCompleted;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Pending\AudioRequest;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Queue\PendingExecution;
use Atlasphp\Atlas\Responses\AudioResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;
use Atlasphp\Atlas\Testing\AudioResponseFake;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

function createAudioPending(?Driver $driver = null): AudioRequest
{
    $driver ??= Mockery::mock(Driver::class);
    $registry = Mockery::mock(ProviderRegistryContract::class);
    $registry->shouldReceive('resolve')->with('openai')->andReturn($driver);

    return new AudioRequest('openai', 'tts-1', $registry);
}

it('returns $this from fluent methods', function () {
    $pending = createAudioPending();

    expect($pending->instructions('speak'))->toBe($pending);
    expect($pending->withMedia([]))->toBe($pending);
    expect($pending->withVoice('alloy'))->toBe($pending);
    expect($pending->withVoiceClone([]))->toBe($pending);
    expect($pending->withSpeed(1.5))->toBe($pending);
    expect($pending->withLanguage('en'))->toBe($pending);
    expect($pending->withDuration(30))->toBe($pending);
    expect($pending->withFormat('mp3'))->toBe($pending);
    expect($pending->withProviderOptions([]))->toBe($pending);
});

it('dispatches asAudio to driver', function () {
    $response = new AudioResponse('base64data');
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(audio: true));
    $driver->shouldReceive('audio')->once()->andReturn($response);

    expect(createAudioPending($driver)->asAudio())->toBe($response);
});

it('dispatches asText to driver audioToText', function () {
    $response = new TextResponse('transcribed text', new Usage(10, 5), FinishReason::Stop);
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(audioToText: true));
    $driver->shouldReceive('audioToText')->once()->andReturn($response);

    expect(createAudioPending($driver)->asText())->toBe($response);
});

it('throws when audio capability is unsupported', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities);
    $driver->shouldReceive('name')->andReturn('test');

    createAudioPending($driver)->asAudio();
})->throws(UnsupportedFeatureException::class);

it('builds request with correct values', function () {
    $request = createAudioPending()
        ->instructions('Read aloud')
        ->withVoice('alloy')
        ->withSpeed(1.5)
        ->withLanguage('en')
        ->withDuration(30)
        ->withFormat('mp3')
        ->withProviderOptions(['key' => 'val'])
        ->buildRequest();

    expect($request->model)->toBe('tts-1');
    expect($request->instructions)->toBe('Read aloud');
    expect($request->voice)->toBe('alloy');
    expect($request->speed)->toBe(1.5);
    expect($request->language)->toBe('en');
    expect($request->duration)->toBe(30);
    expect($request->format)->toBe('mp3');
    expect($request->providerOptions)->toBe(['key' => 'val']);
});

it('fires ModalityCompleted on asAudio error', function () {
    Event::fake();

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(audio: true));
    $driver->shouldReceive('audio')->andThrow(new RuntimeException('fail'));

    try {
        createAudioPending($driver)->instructions('test')->asAudio();
    } catch (RuntimeException) {
    }

    Event::assertDispatched(
        ModalityCompleted::class,
        fn ($e) => $e->modality === Modality::Audio && $e->usage === null
    );
});

it('fires ModalityCompleted on asText error', function () {
    Event::fake();

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(audioToText: true));
    $driver->shouldReceive('audioToText')->andThrow(new RuntimeException('fail'));

    try {
        createAudioPending($driver)->instructions('test')->asText();
    } catch (RuntimeException) {
    }

    Event::assertDispatched(
        ModalityCompleted::class,
        fn ($e) => $e->modality === Modality::AudioToText && $e->usage === null
    );
});

it('queued asAudio returns PendingExecution', function () {
    Queue::fake();

    $result = createAudioPending()->instructions('test')->queue()->asAudio();

    expect($result)->toBeInstanceOf(PendingExecution::class);
});

it('serializes to queue payload', function () {
    $payload = createAudioPending()
        ->instructions('Hello')
        ->withVoice('alloy')
        ->withFormat('mp3')
        ->toQueuePayload();

    expect($payload['provider'])->toBe('openai')
        ->and($payload['instructions'])->toBe('Hello')
        ->and($payload['voice'])->toBe('alloy')
        ->and($payload['format'])->toBe('mp3');
});

it('executeFromPayload rebuilds and executes', function () {
    Atlas::fake([
        AudioResponseFake::make(),
    ]);

    $result = AudioRequest::executeFromPayload(
        payload: ['provider' => 'openai', 'model' => 'tts-1', 'instructions' => 'hello', 'media' => [], 'voice' => null, 'voiceClone' => null, 'speed' => null, 'language' => null, 'duration' => null, 'format' => null, 'providerOptions' => [], 'meta' => [], 'variables' => [], 'interpolate_messages' => false],
        terminal: 'asAudio',
    );

    expect($result)->toBeInstanceOf(AudioResponse::class);
});
