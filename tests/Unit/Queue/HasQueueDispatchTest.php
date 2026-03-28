<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Pending\TextRequest;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Queue\Jobs\ExecuteAtlasJob;
use Atlasphp\Atlas\Queue\PendingExecution;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Queue;

function createQueueTextPending(
    ?Driver $driver = null,
    Provider|string $provider = 'openai',
): TextRequest {
    $driver ??= Mockery::mock(Driver::class);
    $registry = Mockery::mock(ProviderRegistryContract::class);
    $key = $provider instanceof Provider ? $provider->value : $provider;
    $registry->shouldReceive('resolve')->with($key)->andReturn($driver);

    return new TextRequest($provider, 'gpt-4o', $registry);
}

it('queue() returns the same instance for chaining', function () {
    $pending = createQueueTextPending();

    expect($pending->queue())->toBe($pending);
});

it('queue() with name sets queue name', function () {
    $pending = createQueueTextPending();

    $result = $pending->queue('high-priority');

    expect($result)->toBe($pending);
});

it('onConnection() sets queue connection', function () {
    $pending = createQueueTextPending();

    $result = $pending->onConnection('redis');

    expect($result)->toBe($pending);
});

it('onQueue() sets queue name', function () {
    $pending = createQueueTextPending();

    $result = $pending->onQueue('atlas-jobs');

    expect($result)->toBe($pending);
});

it('resolveExecutionType maps terminal names correctly', function () {
    $pending = createQueueTextPending();

    // Use reflection to access the protected method
    $method = new ReflectionMethod($pending, 'resolveExecutionType');

    expect($method->invoke($pending, 'asText'))->toBe(ExecutionType::Text);
    expect($method->invoke($pending, 'asStream'))->toBe(ExecutionType::Stream);
    expect($method->invoke($pending, 'stream'))->toBe(ExecutionType::Stream);
    expect($method->invoke($pending, 'asStructured'))->toBe(ExecutionType::Structured);
    expect($method->invoke($pending, 'asImage'))->toBe(ExecutionType::Image);
    expect($method->invoke($pending, 'asAudio'))->toBe(ExecutionType::Audio);
    expect($method->invoke($pending, 'asVideo'))->toBe(ExecutionType::Video);
    expect($method->invoke($pending, 'asEmbeddings'))->toBe(ExecutionType::Embed);
    expect($method->invoke($pending, 'asModeration'))->toBe(ExecutionType::Moderate);
    expect($method->invoke($pending, 'asReranked'))->toBe(ExecutionType::Rerank);
});

it('resolveExecutionType throws on unknown terminal', function () {
    $pending = createQueueTextPending();
    $method = new ReflectionMethod($pending, 'resolveExecutionType');

    $method->invoke($pending, 'unknownTerminal');
})->throws(InvalidArgumentException::class, 'Cannot resolve execution type for terminal: unknownTerminal');

it('queued terminal returns PendingExecution', function () {
    Queue::fake();

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(text: true));

    $pending = createQueueTextPending($driver)
        ->message('Hello')
        ->queue();

    $result = $pending->asText();

    expect($result)->toBeInstanceOf(PendingExecution::class);
});

it('broadcastOn sets broadcast channel', function () {
    $pending = createQueueTextPending();

    $channel = new PrivateChannel('test-channel');
    $result = $pending->broadcastOn($channel);

    expect($result)->toBe($pending);

    // Verify via reflection that broadcastChannel is stored
    $property = new ReflectionProperty($pending, 'broadcastChannel');
    expect($property->getValue($pending))->toBe($channel);
});

it('resolveQueueConnection returns null when not set', function () {
    $pending = createQueueTextPending();
    $method = new ReflectionMethod($pending, 'resolveQueueConnection');

    expect($method->invoke($pending))->toBeNull();
});

it('resolveQueueConnection returns explicit value', function () {
    $pending = createQueueTextPending();
    $pending->onConnection('sqs');

    $method = new ReflectionMethod($pending, 'resolveQueueConnection');

    expect($method->invoke($pending))->toBe('sqs');
});

it('resolveQueueName uses AtlasConfig queue', function () {
    config()->set('atlas.queue', 'atlas-priority');
    AtlasConfig::refresh();

    $pending = createQueueTextPending();
    $method = new ReflectionMethod($pending, 'resolveQueueName');

    expect($method->invoke($pending))->toBe('atlas-priority');
});

it('resolveQueueName prefers explicit over config', function () {
    config()->set('atlas.queue', 'atlas-priority');
    AtlasConfig::refresh();

    $pending = createQueueTextPending();
    $pending->onQueue('custom-queue');

    $method = new ReflectionMethod($pending, 'resolveQueueName');

    expect($method->invoke($pending))->toBe('custom-queue');
});

it('resolveQueueName defaults to default from config', function () {
    // The config ships with 'default' as the default value
    $pending = createQueueTextPending();
    $method = new ReflectionMethod($pending, 'resolveQueueName');

    expect($method->invoke($pending))->toBe('default');
});

it('withQueueDelay() sets delay and returns self', function () {
    $pending = createQueueTextPending();

    $result = $pending->withQueueDelay(30);

    expect($result)->toBe($pending);

    $property = new ReflectionProperty($pending, 'queueDelay');
    expect($property->getValue($pending))->toBe(30);
});

it('withQueueDelay() clamps negative values to zero', function () {
    $pending = createQueueTextPending();

    $pending->withQueueDelay(-10);

    $property = new ReflectionProperty($pending, 'queueDelay');
    expect($property->getValue($pending))->toBe(0);
});

it('dispatched job applies connection when set', function () {
    Queue::fake();

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(text: true));

    $pending = createQueueTextPending($driver)
        ->message('Hello')
        ->queue()
        ->onConnection('redis');

    $result = $pending->asText();
    $result->dispatch(); // Force dispatch before assertion

    expect($result)->toBeInstanceOf(PendingExecution::class);

    Queue::assertPushed(function (ExecuteAtlasJob $job) {
        return $job->connection === 'redis';
    });
});

it('dispatched job applies delay when set', function () {
    Queue::fake();

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(text: true));

    $pending = createQueueTextPending($driver)
        ->message('Hello')
        ->queue()
        ->withQueueDelay(60);

    $result = $pending->asText();
    $result->dispatch(); // Force dispatch before assertion

    expect($result)->toBeInstanceOf(PendingExecution::class);

    Queue::assertPushed(function (ExecuteAtlasJob $job) {
        return $job->delay === 60;
    });
});

// ─── withTimeout ────────────────────────────────────────────────────────────

it('withTimeout() sets timeout and returns self', function () {
    $pending = createQueueTextPending();

    $result = $pending->withQueueTimeout(1800);

    expect($result)->toBe($pending);

    $property = new ReflectionProperty($pending, 'queueTimeout');
    expect($property->getValue($pending))->toBe(1800);
});

it('withTimeout() clamps negative values to zero', function () {
    $pending = createQueueTextPending();

    $pending->withQueueTimeout(-10);

    $property = new ReflectionProperty($pending, 'queueTimeout');
    expect($property->getValue($pending))->toBe(0);
});

it('dispatched job applies timeout when set', function () {
    Queue::fake();

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(text: true));

    $pending = createQueueTextPending($driver)
        ->message('Hello')
        ->queue()
        ->withQueueTimeout(3600);

    $result = $pending->asText();
    $result->dispatch();

    Queue::assertPushed(function (ExecuteAtlasJob $job) {
        return $job->timeout === 3600;
    });
});

// ─── withTries ──────────────────────────────────────────────────────────────

it('withTries() sets tries and returns self', function () {
    $pending = createQueueTextPending();

    $result = $pending->withQueueTries(5);

    expect($result)->toBe($pending);

    $property = new ReflectionProperty($pending, 'queueTries');
    expect($property->getValue($pending))->toBe(5);
});

it('withTries() clamps to minimum of 1', function () {
    $pending = createQueueTextPending();

    $pending->withQueueTries(0);

    $property = new ReflectionProperty($pending, 'queueTries');
    expect($property->getValue($pending))->toBe(1);
});

it('dispatched job applies tries when set', function () {
    Queue::fake();

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(text: true));

    $pending = createQueueTextPending($driver)
        ->message('Hello')
        ->queue()
        ->withQueueTries(1);

    $result = $pending->asText();
    $result->dispatch();

    Queue::assertPushed(function (ExecuteAtlasJob $job) {
        return $job->tries === 1;
    });
});

// ─── withBackoff ────────────────────────────────────────────────────────────

it('withBackoff() sets backoff and returns self', function () {
    $pending = createQueueTextPending();

    $result = $pending->withQueueBackoff(120);

    expect($result)->toBe($pending);

    $property = new ReflectionProperty($pending, 'queueBackoff');
    expect($property->getValue($pending))->toBe(120);
});

it('withBackoff() clamps negative values to zero', function () {
    $pending = createQueueTextPending();

    $pending->withQueueBackoff(-5);

    $property = new ReflectionProperty($pending, 'queueBackoff');
    expect($property->getValue($pending))->toBe(0);
});

it('dispatched job applies backoff when set', function () {
    Queue::fake();

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(text: true));

    $pending = createQueueTextPending($driver)
        ->message('Hello')
        ->queue()
        ->withQueueBackoff(120);

    $result = $pending->asText();
    $result->dispatch();

    Queue::assertPushed(function (ExecuteAtlasJob $job) {
        return $job->backoff === 120;
    });
});
