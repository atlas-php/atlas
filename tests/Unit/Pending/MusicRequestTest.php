<?php

declare(strict_types=1);

use Atlasphp\Atlas\Pending\MusicRequest;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Responses\AudioResponse;

function createMusicPending(?Driver $driver = null): MusicRequest
{
    $driver ??= Mockery::mock(Driver::class);
    $registry = Mockery::mock(ProviderRegistryContract::class);
    $registry->shouldReceive('resolve')->with('openai')->andReturn($driver);

    return new MusicRequest('openai', 'test-model', $registry);
}

it('returns $this from all fluent methods', function () {
    $pending = createMusicPending();

    expect($pending->instructions('generate music'))->toBe($pending);
    expect($pending->withDuration(30))->toBe($pending);
    expect($pending->withFormat('mp3'))->toBe($pending);
    expect($pending->withProviderOptions([]))->toBe($pending);
});

it('dispatches asAudio to driver', function () {
    $response = new AudioResponse('base64data');
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(audio: true));
    $driver->shouldReceive('audio')->once()->andReturn($response);

    expect(createMusicPending($driver)->asAudio())->toBe($response);
});

it('builds request with music audio mode', function () {
    $request = createMusicPending()->buildRequest();

    expect($request->meta['_audio_mode'])->toBe('music');
});

it('builds request with correct properties', function () {
    $request = createMusicPending()
        ->instructions('epic orchestral')
        ->withDuration(60)
        ->withFormat('wav')
        ->withProviderOptions(['key' => 'val'])
        ->buildRequest();

    expect($request->model)->toBe('test-model');
    expect($request->instructions)->toBe('epic orchestral');
    expect($request->duration)->toBe(60);
    expect($request->format)->toBe('wav');
    expect($request->providerOptions)->toBe(['key' => 'val']);
});

it('serializes to queue payload', function () {
    $payload = createMusicPending()
        ->instructions('ambient track')
        ->withDuration(30)
        ->withFormat('mp3')
        ->withProviderOptions(['tempo' => 120])
        ->toQueuePayload();

    expect($payload)->toMatchArray([
        'provider' => 'openai',
        'model' => 'test-model',
        'instructions' => 'ambient track',
        'duration' => 30,
        'format' => 'mp3',
        'providerOptions' => ['tempo' => 120],
    ]);
});
