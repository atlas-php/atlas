<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Testing\AtlasFake;
use Atlasphp\Atlas\Testing\FakeAgentExecutor;
use Atlasphp\Atlas\Testing\PendingFakeRequest;
use Illuminate\Container\Container;

beforeEach(function () {
    $this->container = new Container;
    $this->fake = new AtlasFake($this->container);
});

test('response with immediate response returns self', function () {
    $result = $this->fake->response('test-agent', AgentResponse::text('Hello'));

    expect($result)->toBe($this->fake);
});

test('response without response returns PendingFakeRequest', function () {
    $result = $this->fake->response('test-agent');

    expect($result)->toBeInstanceOf(PendingFakeRequest::class);
});

test('sequence configures default sequence', function () {
    $result = $this->fake->sequence([
        AgentResponse::text('First'),
        AgentResponse::text('Second'),
    ]);

    expect($result)->toBe($this->fake);
});

test('preventStrayRequests returns self', function () {
    $result = $this->fake->preventStrayRequests();

    expect($result)->toBe($this->fake);
});

test('allowStrayRequests returns self', function () {
    $this->fake->preventStrayRequests();
    $result = $this->fake->allowStrayRequests();

    expect($result)->toBe($this->fake);
});

test('recorded returns empty array initially', function () {
    $recorded = $this->fake->recorded();

    expect($recorded)->toBeArray()->toBeEmpty();
});

test('recordedFor returns empty array for unknown agent', function () {
    $recorded = $this->fake->recordedFor('unknown-agent');

    expect($recorded)->toBeArray()->toBeEmpty();
});

test('reset clears all state and returns self', function () {
    $this->fake->response('test-agent', AgentResponse::text('Hello'));
    $result = $this->fake->reset();

    expect($result)->toBe($this->fake);
});

test('activate binds fake executor to container', function () {
    $this->fake->activate();

    expect($this->container->bound(AgentExecutorContract::class))->toBeTrue();
    expect($this->container->make(AgentExecutorContract::class))
        ->toBeInstanceOf(FakeAgentExecutor::class);
});

test('activate stores original executor if bound', function () {
    $originalExecutor = Mockery::mock(AgentExecutorContract::class);
    $this->container->instance(AgentExecutorContract::class, $originalExecutor);

    $this->fake->activate();

    expect($this->container->make(AgentExecutorContract::class))
        ->toBeInstanceOf(FakeAgentExecutor::class);
});

test('restore rebinds original executor', function () {
    $originalExecutor = Mockery::mock(AgentExecutorContract::class);
    $this->container->instance(AgentExecutorContract::class, $originalExecutor);

    $this->fake->activate();
    $this->fake->restore();

    expect($this->container->make(AgentExecutorContract::class))->toBe($originalExecutor);
});

test('getExecutor returns FakeAgentExecutor instance', function () {
    $executor = $this->fake->getExecutor();

    expect($executor)->toBeInstanceOf(FakeAgentExecutor::class);
});

test('registerSequence adds sequence to executor', function () {
    $sequence = new \Atlasphp\Atlas\Testing\Support\FakeResponseSequence;
    $sequence->push(AgentResponse::text('Test'));

    $this->fake->registerSequence('test-agent', $sequence);

    // Verify via getExecutor
    $executor = $this->fake->getExecutor();
    expect($executor)->toBeInstanceOf(FakeAgentExecutor::class);
});
