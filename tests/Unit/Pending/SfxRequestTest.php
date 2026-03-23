<?php

declare(strict_types=1);

use Atlasphp\Atlas\Pending\SfxRequest;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Responses\AudioResponse;

function createSfxPending(?Driver $driver = null): SfxRequest
{
    $driver ??= Mockery::mock(Driver::class);
    $registry = Mockery::mock(ProviderRegistryContract::class);
    $registry->shouldReceive('resolve')->with('openai')->andReturn($driver);

    return new SfxRequest('openai', 'test-model', $registry);
}

it('returns $this from all fluent methods', function () {
    $pending = createSfxPending();

    expect($pending->instructions('explosion sound'))->toBe($pending);
    expect($pending->withDuration(5))->toBe($pending);
    expect($pending->withFormat('mp3'))->toBe($pending);
    expect($pending->withProviderOptions([]))->toBe($pending);
});

it('dispatches asAudio to driver', function () {
    $response = new AudioResponse('base64data');
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(audio: true));
    $driver->shouldReceive('audio')->once()->andReturn($response);

    expect(createSfxPending($driver)->asAudio())->toBe($response);
});

it('builds request with sfx audio mode', function () {
    $request = createSfxPending()->buildRequest();

    expect($request->meta['_audio_mode'])->toBe('sfx');
});

it('builds request with correct properties', function () {
    $request = createSfxPending()
        ->instructions('door creak')
        ->withDuration(3)
        ->withFormat('wav')
        ->withProviderOptions(['key' => 'val'])
        ->buildRequest();

    expect($request->model)->toBe('test-model');
    expect($request->instructions)->toBe('door creak');
    expect($request->duration)->toBe(3);
    expect($request->format)->toBe('wav');
    expect($request->providerOptions)->toBe(['key' => 'val']);
});

it('serializes to queue payload', function () {
    $payload = createSfxPending()
        ->instructions('thunder clap')
        ->withDuration(2)
        ->withFormat('mp3')
        ->withProviderOptions(['intensity' => 'high'])
        ->toQueuePayload();

    expect($payload)->toMatchArray([
        'provider' => 'openai',
        'model' => 'test-model',
        'instructions' => 'thunder clap',
        'duration' => 2,
        'format' => 'mp3',
        'providerOptions' => ['intensity' => 'high'],
    ]);
});
