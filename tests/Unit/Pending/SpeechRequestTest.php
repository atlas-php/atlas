<?php

declare(strict_types=1);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Events\ModalityCompleted;
use Atlasphp\Atlas\Events\ModalityStarted;
use Atlasphp\Atlas\Pending\SpeechRequest;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
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

function createSpeechPending(?Driver $driver = null): SpeechRequest
{
    $driver ??= Mockery::mock(Driver::class);
    $registry = Mockery::mock(ProviderRegistryContract::class);
    $registry->shouldReceive('resolve')->with('openai')->andReturn($driver);

    return new SpeechRequest('openai', 'tts-1', $registry);
}

it('returns $this from all fluent methods', function () {
    $pending = createSpeechPending();

    expect($pending->instructions('speak clearly'))->toBe($pending);
    expect($pending->withMedia([]))->toBe($pending);
    expect($pending->withVoice('alloy'))->toBe($pending);
    expect($pending->withVoiceClone([]))->toBe($pending);
    expect($pending->withSpeed(1.5))->toBe($pending);
    expect($pending->withLanguage('en'))->toBe($pending);
    expect($pending->withFormat('mp3'))->toBe($pending);
    expect($pending->withProviderOptions([]))->toBe($pending);
});

it('dispatches asAudio to driver', function () {
    $response = new AudioResponse('base64data');
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(audio: true));
    $driver->shouldReceive('audio')->once()->andReturn($response);

    expect(createSpeechPending($driver)->asAudio())->toBe($response);
});

it('dispatches asText to driver', function () {
    $response = new TextResponse('transcribed text', new Usage(10, 5), FinishReason::Stop);
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(audioToText: true));
    $driver->shouldReceive('audioToText')->once()->andReturn($response);

    expect(createSpeechPending($driver)->asText())->toBe($response);
});

it('builds request with speech audio mode', function () {
    $request = createSpeechPending()->buildRequest();

    expect($request->meta['_audio_mode'])->toBe('speech');
});

it('builds request with correct properties', function () {
    $request = createSpeechPending()
        ->instructions('Read this aloud')
        ->withVoice('alloy')
        ->withSpeed(1.5)
        ->withLanguage('en')
        ->withFormat('mp3')
        ->withProviderOptions(['key' => 'val'])
        ->buildRequest();

    expect($request->model)->toBe('tts-1');
    expect($request->instructions)->toBe('Read this aloud');
    expect($request->voice)->toBe('alloy');
    expect($request->speed)->toBe(1.5);
    expect($request->language)->toBe('en');
    expect($request->format)->toBe('mp3');
    expect($request->providerOptions)->toBe(['key' => 'val']);
});

it('serializes to queue payload', function () {
    $payload = createSpeechPending()
        ->instructions('Hello world')
        ->withMedia(['audio.mp3'])
        ->withVoice('nova')
        ->withVoiceClone(['sample' => 'data'])
        ->withSpeed(1.2)
        ->withLanguage('fr')
        ->withFormat('wav')
        ->withProviderOptions(['quality' => 'hd'])
        ->toQueuePayload();

    expect($payload)->toMatchArray([
        'provider' => 'openai',
        'model' => 'tts-1',
        'instructions' => 'Hello world',
        'media' => ['audio.mp3'],
        'voice' => 'nova',
        'voiceClone' => ['sample' => 'data'],
        'speed' => 1.2,
        'language' => 'fr',
        'format' => 'wav',
        'providerOptions' => ['quality' => 'hd'],
    ]);
});

it('queued dispatch returns PendingExecution', function () {
    Queue::fake();

    $result = createSpeechPending()->instructions('test')->queue()->asAudio();

    expect($result)->toBeInstanceOf(PendingExecution::class);
});

it('dispatches Speech modality events for asAudio', function () {
    Event::fake();

    $response = new AudioResponse('data');
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(audio: true));
    $driver->shouldReceive('audio')->once()->andReturn($response);

    createSpeechPending($driver)->asAudio();

    Event::assertDispatched(
        ModalityStarted::class,
        fn ($e) => $e->modality === Modality::Speech
    );
    Event::assertDispatched(
        ModalityCompleted::class,
        fn ($e) => $e->modality === Modality::Speech
    );
});

it('dispatches SpeechToText modality events for asText', function () {
    Event::fake();

    $response = new TextResponse('text', new Usage(10, 5), FinishReason::Stop);
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(audioToText: true));
    $driver->shouldReceive('audioToText')->once()->andReturn($response);

    createSpeechPending($driver)->asText();

    Event::assertDispatched(
        ModalityStarted::class,
        fn ($e) => $e->modality === Modality::SpeechToText
    );
    Event::assertDispatched(
        ModalityCompleted::class,
        fn ($e) => $e->modality === Modality::SpeechToText && $e->usage !== null
    );
});

it('fires ModalityCompleted on asAudio error', function () {
    Event::fake();

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(audio: true));
    $driver->shouldReceive('audio')->andThrow(new RuntimeException('fail'));

    try {
        createSpeechPending($driver)->asAudio();
    } catch (RuntimeException) {
    }

    Event::assertDispatched(
        ModalityCompleted::class,
        fn ($e) => $e->modality === Modality::Speech && $e->usage === null
    );
});

it('fires ModalityCompleted on asText error', function () {
    Event::fake();

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(audioToText: true));
    $driver->shouldReceive('audioToText')->andThrow(new RuntimeException('fail'));

    try {
        createSpeechPending($driver)->asText();
    } catch (RuntimeException) {
    }

    Event::assertDispatched(
        ModalityCompleted::class,
        fn ($e) => $e->modality === Modality::SpeechToText && $e->usage === null
    );
});

it('resolveExecutionType returns Speech for asAudio', function () {
    $pending = createSpeechPending();
    $reflection = new ReflectionMethod($pending, 'resolveExecutionType');

    expect($reflection->invoke($pending, 'asAudio'))->toBe(ExecutionType::Speech);
});

it('resolveExecutionType returns AudioToText for asText', function () {
    $pending = createSpeechPending();
    $reflection = new ReflectionMethod($pending, 'resolveExecutionType');

    expect($reflection->invoke($pending, 'asText'))->toBe(ExecutionType::AudioToText);
});

it('executeFromPayload asAudio rebuilds correctly', function () {
    Atlas::fake([
        AudioResponseFake::make(),
    ]);

    $result = SpeechRequest::executeFromPayload(
        payload: ['provider' => 'openai', 'model' => 'tts-1', 'instructions' => 'hello', 'media' => [], 'voice' => 'alloy', 'voiceClone' => null, 'speed' => 1.0, 'language' => 'en', 'format' => 'mp3', 'providerOptions' => [], 'meta' => [], 'variables' => [], 'interpolate_messages' => false],
        terminal: 'asAudio',
    );

    expect($result)->toBeInstanceOf(AudioResponse::class);
});

it('executeFromPayload throws on unknown terminal', function () {
    SpeechRequest::executeFromPayload(
        payload: ['provider' => 'openai', 'model' => 'tts-1', 'instructions' => null, 'media' => [], 'voice' => null, 'voiceClone' => null, 'speed' => null, 'language' => null, 'format' => null, 'providerOptions' => [], 'meta' => [], 'variables' => [], 'interpolate_messages' => false],
        terminal: 'asImage',
    );
})->throws(InvalidArgumentException::class, 'Unknown terminal method');
