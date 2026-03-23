<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Events\ModalityCompleted;
use Atlasphp\Atlas\Events\ModalityStarted;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Pending\MusicRequest;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Queue\PendingExecution;
use Atlasphp\Atlas\Responses\AudioResponse;
use Atlasphp\Atlas\Testing\AudioResponseFake;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

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

it('queued dispatch returns PendingExecution', function () {
    Queue::fake();

    $result = createMusicPending()
        ->instructions('test')
        ->queue()
        ->asAudio();

    expect($result)->toBeInstanceOf(PendingExecution::class);
});

it('dispatches ModalityStarted and ModalityCompleted events', function () {
    Event::fake();

    $response = new AudioResponse('base64data');
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(audio: true));
    $driver->shouldReceive('audio')->once()->andReturn($response);

    createMusicPending($driver)->asAudio();

    Event::assertDispatched(
        ModalityStarted::class,
        fn ($e) => $e->modality === Modality::Music
    );

    Event::assertDispatched(
        ModalityCompleted::class,
        fn ($e) => $e->modality === Modality::Music
    );
});

it('fires ModalityCompleted on error before rethrowing', function () {
    Event::fake();

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(audio: true));
    $driver->shouldReceive('audio')->andThrow(new RuntimeException('API error'));

    try {
        createMusicPending($driver)->asAudio();
    } catch (RuntimeException) {
        // expected
    }

    Event::assertDispatched(
        ModalityCompleted::class,
        fn ($e) => $e->modality === Modality::Music && $e->usage === null
    );
});

it('resolveExecutionType returns Music', function () {
    $pending = createMusicPending();

    $reflection = new ReflectionMethod($pending, 'resolveExecutionType');
    $result = $reflection->invoke($pending, 'asAudio');

    expect($result)->toBe(ExecutionType::Music);
});

it('executeFromPayload rebuilds and executes request', function () {
    Atlas::fake([
        AudioResponseFake::make(),
    ]);

    $result = MusicRequest::executeFromPayload(
        payload: [
            'provider' => 'openai',
            'model' => 'test',
            'instructions' => 'ambient',
            'duration' => 30,
            'format' => 'mp3',
            'providerOptions' => [],
            'meta' => [],
            'variables' => [],
            'interpolate_messages' => false,
        ],
        terminal: 'asAudio',
    );

    expect($result)->toBeInstanceOf(AudioResponse::class);
});

it('executeFromPayload throws on unknown terminal', function () {
    MusicRequest::executeFromPayload(
        payload: [
            'provider' => 'openai',
            'model' => 'test',
            'instructions' => null,
            'duration' => null,
            'format' => null,
            'providerOptions' => [],
            'meta' => [],
            'variables' => [],
            'interpolate_messages' => false,
        ],
        terminal: 'asText',
    );
})->throws(InvalidArgumentException::class, 'Unknown terminal method');
