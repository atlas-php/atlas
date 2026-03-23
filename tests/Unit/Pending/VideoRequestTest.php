<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Events\ModalityCompleted;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Pending\VideoRequest;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Queue\PendingExecution;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;
use Atlasphp\Atlas\Responses\VideoResponse;
use Atlasphp\Atlas\Testing\VideoResponseFake;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

function createVideoPending(?Driver $driver = null): VideoRequest
{
    $driver ??= Mockery::mock(Driver::class);
    $registry = Mockery::mock(ProviderRegistryContract::class);
    $registry->shouldReceive('resolve')->with('openai')->andReturn($driver);

    return new VideoRequest('openai', 'sora', $registry);
}

it('returns $this from fluent methods', function () {
    $pending = createVideoPending();

    expect($pending->instructions('generate'))->toBe($pending);
    expect($pending->withMedia([]))->toBe($pending);
    expect($pending->withDuration(10))->toBe($pending);
    expect($pending->withRatio('16:9'))->toBe($pending);
    expect($pending->withFormat('mp4'))->toBe($pending);
    expect($pending->withProviderOptions([]))->toBe($pending);
});

it('dispatches asVideo to driver', function () {
    $response = new VideoResponse('https://example.com/video.mp4');
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(video: true));
    $driver->shouldReceive('video')->once()->andReturn($response);

    expect(createVideoPending($driver)->asVideo())->toBe($response);
});

it('dispatches asText to driver videoToText', function () {
    $response = new TextResponse('video description', new Usage(10, 5), FinishReason::Stop);
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(videoToText: true));
    $driver->shouldReceive('videoToText')->once()->andReturn($response);

    expect(createVideoPending($driver)->asText())->toBe($response);
});

it('throws when video capability is unsupported', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities);
    $driver->shouldReceive('name')->andReturn('test');

    createVideoPending($driver)->asVideo();
})->throws(UnsupportedFeatureException::class);

it('builds request with correct values', function () {
    $request = createVideoPending()
        ->instructions('Generate a clip')
        ->withDuration(10)
        ->withRatio('16:9')
        ->withFormat('mp4')
        ->withProviderOptions(['fps' => 30])
        ->buildRequest();

    expect($request->model)->toBe('sora');
    expect($request->instructions)->toBe('Generate a clip');
    expect($request->duration)->toBe(10);
    expect($request->ratio)->toBe('16:9');
    expect($request->format)->toBe('mp4');
    expect($request->providerOptions)->toBe(['fps' => 30]);
});

it('fires ModalityCompleted on asVideo error', function () {
    Event::fake();

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(video: true));
    $driver->shouldReceive('video')->andThrow(new RuntimeException('fail'));

    try {
        createVideoPending($driver)->instructions('test')->asVideo();
    } catch (RuntimeException) {
    }

    Event::assertDispatched(
        ModalityCompleted::class,
        fn ($e) => $e->modality === Modality::Video && $e->usage === null
    );
});

it('fires ModalityCompleted on asText error', function () {
    Event::fake();

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(videoToText: true));
    $driver->shouldReceive('videoToText')->andThrow(new RuntimeException('fail'));

    try {
        createVideoPending($driver)->instructions('test')->asText();
    } catch (RuntimeException) {
    }

    Event::assertDispatched(
        ModalityCompleted::class,
        fn ($e) => $e->modality === Modality::VideoToText && $e->usage === null
    );
});

it('queued asVideo returns PendingExecution', function () {
    Queue::fake();

    $result = createVideoPending()->instructions('test')->queue()->asVideo();

    expect($result)->toBeInstanceOf(PendingExecution::class);
});

it('serializes to queue payload', function () {
    $payload = createVideoPending()
        ->instructions('A sunrise')
        ->withDuration(10)
        ->withRatio('16:9')
        ->toQueuePayload();

    expect($payload['provider'])->toBe('openai')
        ->and($payload['instructions'])->toBe('A sunrise')
        ->and($payload['duration'])->toBe(10)
        ->and($payload['ratio'])->toBe('16:9');
});

it('executeFromPayload rebuilds and executes', function () {
    Atlas::fake([
        VideoResponseFake::make(),
    ]);

    $result = VideoRequest::executeFromPayload(
        payload: ['provider' => 'openai', 'model' => 'sora', 'instructions' => 'clip', 'media' => [], 'duration' => null, 'ratio' => null, 'format' => null, 'providerOptions' => [], 'meta' => [], 'variables' => [], 'interpolate_messages' => false],
        terminal: 'asVideo',
    );

    expect($result)->toBeInstanceOf(VideoResponse::class);
});
