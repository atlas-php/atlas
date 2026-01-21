<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Exceptions\AgentException;
use Atlasphp\Atlas\Agents\Services\AgentRegistry;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Illuminate\Container\Container;

beforeEach(function () {
    $this->container = new Container;
    $this->registry = new AgentRegistry($this->container);
    $this->resolver = new AgentResolver($this->registry, $this->container);
});

test('it passes through agent instance', function () {
    $agent = new TestAgent;

    $resolved = $this->resolver->resolve($agent);

    expect($resolved)->toBe($agent);
});

test('it resolves from registry by key', function () {
    $this->registry->register(TestAgent::class);

    $resolved = $this->resolver->resolve('test-agent');

    expect($resolved)->toBeInstanceOf(AgentContract::class);
    expect($resolved->key())->toBe('test-agent');
});

test('it resolves from container by class', function () {
    $resolved = $this->resolver->resolve(TestAgent::class);

    expect($resolved)->toBeInstanceOf(TestAgent::class);
});

test('it throws when class does not exist', function () {
    $this->resolver->resolve('NonExistentClass');
})->throws(AgentException::class);

test('it prefers registry over container', function () {
    $registeredAgent = new TestAgent;
    $this->registry->registerInstance($registeredAgent);

    $resolved = $this->resolver->resolve('test-agent');

    expect($resolved)->toBe($registeredAgent);
});
