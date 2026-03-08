<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Agents\Jobs\InvokeAgent;
use Atlasphp\Atlas\Agents\Support\QueuedAgentResponse;
use Atlasphp\Atlas\AtlasManager;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Illuminate\Support\Facades\Queue;

test('queue() returns QueuedAgentResponse', function () {
    Queue::fake();

    $registry = app(AgentRegistryContract::class);
    $registry->register(TestAgent::class);

    $manager = app(AtlasManager::class);
    $queued = $manager->agent('test-agent')
        ->withVariables(['user' => 'Alice'])
        ->queue('Summarize this');

    expect($queued)->toBeInstanceOf(QueuedAgentResponse::class);
    expect($queued->getJob()->agentKey)->toBe('test-agent');
    expect($queued->getJob()->input)->toBe('Summarize this');
    expect($queued->getJob()->serializedContext['variables'])->toBe(['user' => 'Alice']);
});

test('queue() dispatches InvokeAgent job', function () {
    Queue::fake();

    $registry = app(AgentRegistryContract::class);
    $registry->register(TestAgent::class);

    $manager = app(AtlasManager::class);
    $queued = $manager->agent('test-agent')->queue('Hello');
    $queued->dispatch();

    Queue::assertPushed(InvokeAgent::class, function (InvokeAgent $job) {
        return $job->agentKey === 'test-agent' && $job->input === 'Hello';
    });
});

test('queue() with onQueue dispatches to correct queue', function () {
    Queue::fake();

    $registry = app(AgentRegistryContract::class);
    $registry->register(TestAgent::class);

    $manager = app(AtlasManager::class);
    $queued = $manager->agent('test-agent')
        ->queue('Hello')
        ->onQueue('ai');
    $queued->dispatch();

    Queue::assertPushed(InvokeAgent::class, function (InvokeAgent $job) {
        return $job->queue === 'ai';
    });
});
