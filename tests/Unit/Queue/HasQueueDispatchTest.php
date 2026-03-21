<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Pending\TextRequest;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
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
    expect($method->invoke($pending, 'unknownTerminal'))->toBe(ExecutionType::Text);
});

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

it('resolveQueueConnection uses config fallback', function () {
    config()->set('atlas.queue.connection', 'redis');

    $pending = createQueueTextPending();
    $method = new ReflectionMethod($pending, 'resolveQueueConnection');

    expect($method->invoke($pending))->toBe('redis');
});

it('resolveQueueConnection prefers explicit over config', function () {
    config()->set('atlas.queue.connection', 'redis');

    $pending = createQueueTextPending();
    $pending->onConnection('sqs');

    $method = new ReflectionMethod($pending, 'resolveQueueConnection');

    expect($method->invoke($pending))->toBe('sqs');
});

it('resolveQueueName uses config fallback', function () {
    config()->set('atlas.queue.queue', 'atlas-priority');

    $pending = createQueueTextPending();
    $method = new ReflectionMethod($pending, 'resolveQueueName');

    expect($method->invoke($pending))->toBe('atlas-priority');
});

it('resolveQueueName prefers explicit over config', function () {
    config()->set('atlas.queue.queue', 'atlas-priority');

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
