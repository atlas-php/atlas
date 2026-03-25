<?php

declare(strict_types=1);

use Atlasphp\Atlas\Executor\ExecutionContext;
use Illuminate\Broadcasting\Channel;

it('stores all properties', function () {
    $channel = new Channel('test-channel');

    $context = new ExecutionContext(
        agentKey: 'my-agent',
        provider: 'openai',
        model: 'gpt-4o',
        traceId: 'trace-abc',
        broadcastChannel: $channel,
    );

    expect($context->agentKey)->toBe('my-agent');
    expect($context->provider)->toBe('openai');
    expect($context->model)->toBe('gpt-4o');
    expect($context->traceId)->toBe('trace-abc');
    expect($context->broadcastChannel)->toBe($channel);
});

it('defaults all properties to null', function () {
    $context = new ExecutionContext;

    expect($context->agentKey)->toBeNull();
    expect($context->provider)->toBeNull();
    expect($context->model)->toBeNull();
    expect($context->traceId)->toBeNull();
    expect($context->broadcastChannel)->toBeNull();
});

it('has readonly properties', function () {
    $context = new ExecutionContext(agentKey: 'test');

    $reflection = new ReflectionClass($context);

    foreach ($reflection->getProperties() as $property) {
        expect($property->isReadOnly())->toBeTrue(
            "Property {$property->getName()} should be readonly"
        );
    }
});
