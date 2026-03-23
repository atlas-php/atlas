<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Pending\SpeechRequest;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Responses\AudioResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;

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
