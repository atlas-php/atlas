<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Agents\Jobs\BroadcastAgent;
use Atlasphp\Atlas\AtlasManager;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Illuminate\Support\Facades\Queue;

test('broadcast() dispatches BroadcastAgent job', function () {
    Queue::fake();

    $registry = app(AgentRegistryContract::class);
    $registry->register(TestAgent::class);

    $manager = app(AtlasManager::class);
    $manager->agent('test-agent')
        ->withVariables(['user' => 'Alice'])
        ->broadcast('Summarize this', 'req-123');

    Queue::assertPushed(BroadcastAgent::class, function (BroadcastAgent $job) {
        return $job->agentKey === 'test-agent'
            && $job->input === 'Summarize this'
            && $job->requestId === 'req-123'
            && $job->serializedContext['variables'] === ['user' => 'Alice'];
    });
});

test('broadcast() generates request ID when not provided', function () {
    Queue::fake();

    $registry = app(AgentRegistryContract::class);
    $registry->register(TestAgent::class);

    $manager = app(AtlasManager::class);
    $manager->agent('test-agent')->broadcast('Hello');

    Queue::assertPushed(BroadcastAgent::class, function (BroadcastAgent $job) {
        return $job->requestId !== '' && strlen($job->requestId) === 32;
    });
});
