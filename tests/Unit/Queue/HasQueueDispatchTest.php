<?php

declare(strict_types=1);

use Atlasphp\Atlas\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Pending\TextRequest;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Queue\PendingExecution;
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
