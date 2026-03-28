<?php

declare(strict_types=1);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Events\ModalityCompleted;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Pending\ImageRequest;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Queue\PendingExecution;
use Atlasphp\Atlas\Responses\ImageResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;
use Atlasphp\Atlas\Testing\ImageResponseFake;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

function createImagePending(?Driver $driver = null): ImageRequest
{
    $driver ??= Mockery::mock(Driver::class);
    $registry = Mockery::mock(ProviderRegistryContract::class);
    $registry->shouldReceive('resolve')->with('openai')->andReturn($driver);

    return new ImageRequest('openai', 'dall-e-3', $registry);
}

it('returns $this from fluent methods', function () {
    $pending = createImagePending();

    expect($pending->instructions('draw'))->toBe($pending);
    expect($pending->withMedia([]))->toBe($pending);
    expect($pending->withSize('1024x1024'))->toBe($pending);
    expect($pending->withQuality('hd'))->toBe($pending);
    expect($pending->withFormat('png'))->toBe($pending);
    expect($pending->withProviderOptions([]))->toBe($pending);
    expect($pending->withCount(3))->toBe($pending);
});

it('dispatches asImage to driver', function () {
    $response = new ImageResponse('https://example.com/img.png');
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(image: true));
    $driver->shouldReceive('image')->once()->andReturn($response);

    $result = createImagePending($driver)->instructions('A cat')->asImage();

    expect($result)->toBe($response);
});

it('dispatches asText to driver imageToText', function () {
    $response = new TextResponse('A photo of a cat', new Usage(10, 5), FinishReason::Stop);
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(imageToText: true));
    $driver->shouldReceive('imageToText')->once()->andReturn($response);

    $result = createImagePending($driver)->asText();

    expect($result)->toBe($response);
});

it('throws when image capability is unsupported', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities);
    $driver->shouldReceive('name')->andReturn('test');

    createImagePending($driver)->asImage();
})->throws(UnsupportedFeatureException::class);

it('builds request with correct values', function () {
    $request = createImagePending()
        ->instructions('A sunset')
        ->withSize('1024x1024')
        ->withQuality('hd')
        ->withFormat('png')
        ->withProviderOptions(['style' => 'vivid'])
        ->buildRequest();

    expect($request->model)->toBe('dall-e-3');
    expect($request->instructions)->toBe('A sunset');
    expect($request->size)->toBe('1024x1024');
    expect($request->quality)->toBe('hd');
    expect($request->format)->toBe('png');
    expect($request->providerOptions)->toBe(['style' => 'vivid']);
    expect($request->count)->toBe(1);
});

it('builds request with custom count', function () {
    $request = createImagePending()
        ->instructions('A cat')
        ->withCount(3)
        ->buildRequest();

    expect($request->count)->toBe(3);
});

it('fires ModalityCompleted on asImage error', function () {
    Event::fake();

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(image: true));
    $driver->shouldReceive('image')->andThrow(new RuntimeException('fail'));

    try {
        createImagePending($driver)->instructions('test')->asImage();
    } catch (RuntimeException) {
    }

    Event::assertDispatched(
        ModalityCompleted::class,
        fn ($e) => $e->modality === Modality::Image && $e->usage === null
    );
});

it('fires ModalityCompleted on asText error', function () {
    Event::fake();

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(imageToText: true));
    $driver->shouldReceive('imageToText')->andThrow(new RuntimeException('fail'));

    try {
        createImagePending($driver)->instructions('test')->asText();
    } catch (RuntimeException) {
    }

    Event::assertDispatched(
        ModalityCompleted::class,
        fn ($e) => $e->modality === Modality::ImageToText && $e->usage === null
    );
});

it('queued asImage returns PendingExecution', function () {
    Queue::fake();

    $result = createImagePending()->instructions('test')->queue()->asImage();

    expect($result)->toBeInstanceOf(PendingExecution::class);
});

it('serializes to queue payload', function () {
    $payload = createImagePending()
        ->instructions('A sunset')
        ->withSize('1024x1024')
        ->withQuality('hd')
        ->toQueuePayload();

    expect($payload['provider'])->toBe('openai')
        ->and($payload['instructions'])->toBe('A sunset')
        ->and($payload['size'])->toBe('1024x1024')
        ->and($payload['quality'])->toBe('hd');
});

it('executeFromPayload rebuilds and executes', function () {
    Atlas::fake([
        ImageResponseFake::make(),
    ]);

    $result = ImageRequest::executeFromPayload(
        payload: ['provider' => 'openai', 'model' => 'dall-e-3', 'instructions' => 'cat', 'media' => [], 'size' => null, 'quality' => null, 'format' => null, 'count' => 1, 'providerOptions' => [], 'meta' => [], 'variables' => [], 'interpolate_messages' => false],
        terminal: 'asImage',
    );

    expect($result)->toBeInstanceOf(ImageResponse::class);
});
