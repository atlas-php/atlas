<?php

declare(strict_types=1);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Events\ModalityCompleted;
use Atlasphp\Atlas\Events\ModalityStarted;
use Atlasphp\Atlas\Pending\SfxRequest;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Queue\PendingExecution;
use Atlasphp\Atlas\Responses\AudioResponse;
use Atlasphp\Atlas\Testing\AudioResponseFake;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

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

it('queued dispatch returns PendingExecution', function () {
    Queue::fake();

    $result = createSfxPending()->instructions('boom')->queue()->asAudio();

    expect($result)->toBeInstanceOf(PendingExecution::class);
});

it('dispatches ModalityStarted and ModalityCompleted with Sfx modality', function () {
    Event::fake();

    $response = new AudioResponse('base64data');
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(audio: true));
    $driver->shouldReceive('audio')->once()->andReturn($response);

    createSfxPending($driver)->asAudio();

    Event::assertDispatched(
        ModalityStarted::class,
        fn ($e) => $e->modality === Modality::Sfx
    );
    Event::assertDispatched(
        ModalityCompleted::class,
        fn ($e) => $e->modality === Modality::Sfx
    );
});

it('fires ModalityCompleted on error', function () {
    Event::fake();

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(audio: true));
    $driver->shouldReceive('audio')->andThrow(new RuntimeException('fail'));

    try {
        createSfxPending($driver)->asAudio();
    } catch (RuntimeException) {
    }

    Event::assertDispatched(
        ModalityCompleted::class,
        fn ($e) => $e->modality === Modality::Sfx && $e->usage === null
    );
});

it('resolveExecutionType returns Sfx', function () {
    $pending = createSfxPending();
    $reflection = new ReflectionMethod($pending, 'resolveExecutionType');

    expect($reflection->invoke($pending, 'asAudio'))->toBe(ExecutionType::Sfx);
});

it('executeFromPayload rebuilds and executes', function () {
    Atlas::fake([
        AudioResponseFake::make(),
    ]);

    $result = SfxRequest::executeFromPayload(
        payload: ['provider' => 'openai', 'model' => 'test', 'instructions' => 'boom', 'duration' => 2, 'format' => 'mp3', 'providerOptions' => [], 'meta' => [], 'variables' => [], 'interpolate_messages' => false],
        terminal: 'asAudio',
    );

    expect($result)->toBeInstanceOf(AudioResponse::class);
});

it('executeFromPayload throws on unknown terminal', function () {
    SfxRequest::executeFromPayload(
        payload: ['provider' => 'openai', 'model' => 'test', 'instructions' => null, 'duration' => null, 'format' => null, 'providerOptions' => [], 'meta' => [], 'variables' => [], 'interpolate_messages' => false],
        terminal: 'asText',
    );
})->throws(InvalidArgumentException::class, 'Unknown terminal method');
