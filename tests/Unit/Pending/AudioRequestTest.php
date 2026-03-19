<?php

declare(strict_types=1);

use Atlasphp\Atlas\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Pending\AudioRequest;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Responses\AudioResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;

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
