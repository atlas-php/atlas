<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Jobs\InvokeAgent;
use Atlasphp\Atlas\Agents\Support\AgentContext;

test('InvokeAgent stores agent key, input, and serialized context', function () {
    $context = new AgentContext(
        variables: ['user' => 'Alice'],
        metadata: ['trace_id' => '123'],
    );

    $job = new InvokeAgent(
        agentKey: 'test-agent',
        input: 'Hello',
        serializedContext: $context->toArray(),
    );

    expect($job->agentKey)->toBe('test-agent');
    expect($job->input)->toBe('Hello');
    expect($job->serializedContext['variables'])->toBe(['user' => 'Alice']);
    expect($job->serializedContext['metadata'])->toBe(['trace_id' => '123']);
});

test('InvokeAgent then() sets success callback', function () {
    $job = new InvokeAgent('test-agent', 'Hello', []);

    $result = $job->then(fn ($response) => null);

    expect($result)->toBe($job);
    expect($job->thenCallback)->not->toBeNull();
});

test('InvokeAgent catch() sets failure callback', function () {
    $job = new InvokeAgent('test-agent', 'Hello', []);

    $result = $job->catch(fn ($e) => null);

    expect($result)->toBe($job);
    expect($job->catchCallback)->not->toBeNull();
});
