<?php

declare(strict_types=1);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Events\ModalityCompleted;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Pending\ModerateRequest;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Queue\PendingExecution;
use Atlasphp\Atlas\Responses\ModerationResponse;
use Atlasphp\Atlas\Testing\ModerationResponseFake;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

function createModeratePending(?Driver $driver = null): ModerateRequest
{
    $driver ??= Mockery::mock(Driver::class);
    $registry = Mockery::mock(ProviderRegistryContract::class);
    $registry->shouldReceive('resolve')->with('openai')->andReturn($driver);

    return new ModerateRequest('openai', 'text-moderation-latest', $registry);
}

it('returns $this from fluent methods', function () {
    $pending = createModeratePending();

    expect($pending->fromInput('test content'))->toBe($pending);
    expect($pending->withProviderOptions([]))->toBe($pending);
});

it('dispatches asModeration to driver', function () {
    $response = new ModerationResponse(false);
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(moderate: true));
    $driver->shouldReceive('moderate')->once()->andReturn($response);

    $result = createModeratePending($driver)->fromInput('safe text')->asModeration();

    expect($result)->toBe($response);
});

it('throws when moderate capability is unsupported', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities);
    $driver->shouldReceive('name')->andReturn('test');

    createModeratePending($driver)->fromInput('test')->asModeration();
})->throws(UnsupportedFeatureException::class);

it('throws when input is not set', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(moderate: true));

    createModeratePending($driver)->asModeration();
})->throws(InvalidArgumentException::class, 'Input must be provided');

it('builds request with correct values', function () {
    $request = createModeratePending()
        ->fromInput('check this content')
        ->withProviderOptions(['model' => 'v2'])
        ->buildRequest();

    expect($request->model)->toBe('text-moderation-latest');
    expect($request->input)->toBe('check this content');
    expect($request->providerOptions)->toBe(['model' => 'v2']);
});

it('queued asModeration returns PendingExecution', function () {
    Queue::fake();

    $result = createModeratePending()->fromInput('check')->queue()->asModeration();

    expect($result)->toBeInstanceOf(PendingExecution::class);
});

it('fires ModalityCompleted on error', function () {
    Event::fake();

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(moderate: true));
    $driver->shouldReceive('moderate')->andThrow(new RuntimeException('fail'));

    try {
        createModeratePending($driver)->fromInput('text')->asModeration();
    } catch (RuntimeException) {
    }

    Event::assertDispatched(
        ModalityCompleted::class,
        fn ($e) => $e->modality === Modality::Moderate && $e->usage === null
    );
});

it('executeFromPayload rebuilds and executes', function () {
    Atlas::fake([
        ModerationResponseFake::make(),
    ]);

    $result = ModerateRequest::executeFromPayload(
        payload: ['provider' => 'openai', 'model' => 'text-moderation-latest', 'input' => 'content', 'providerOptions' => [], 'meta' => [], 'variables' => [], 'interpolate_messages' => false],
        terminal: 'asModeration',
    );

    expect($result)->toBeInstanceOf(ModerationResponse::class);
});
