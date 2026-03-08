<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Jobs\BroadcastAgent;
use Atlasphp\Atlas\Agents\Support\AgentContext;

test('BroadcastAgent stores agent key, input, context, and request ID', function () {
    $context = new AgentContext(
        variables: ['user' => 'Alice'],
    );

    $job = new BroadcastAgent(
        agentKey: 'test-agent',
        input: 'Hello',
        serializedContext: $context->toArray(),
        requestId: 'req-123',
    );

    expect($job->agentKey)->toBe('test-agent');
    expect($job->input)->toBe('Hello');
    expect($job->serializedContext['variables'])->toBe(['user' => 'Alice']);
    expect($job->requestId)->toBe('req-123');
});
