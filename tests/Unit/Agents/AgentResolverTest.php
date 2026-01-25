<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Exceptions\AgentException;
use Atlasphp\Atlas\Agents\Services\AgentExtensionRegistry;
use Atlasphp\Atlas\Agents\Services\AgentRegistry;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Agents\Support\AgentDecorator;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Illuminate\Container\Container;

beforeEach(function () {
    $this->container = new Container;
    $this->registry = new AgentRegistry($this->container);
    $this->extensionRegistry = new AgentExtensionRegistry;
    $this->resolver = new AgentResolver($this->registry, $this->container, $this->extensionRegistry);
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

test('it throws AgentException when container instantiation fails', function () {
    // Create a class that will fail to instantiate due to missing dependencies
    $class = AgentWithUnresolvableDependency::class;

    try {
        $this->resolver->resolve($class);
        $this->fail('Expected AgentException to be thrown');
    } catch (AgentException $e) {
        expect($e->getMessage())->toContain('Failed to resolve agent:');
        expect($e->getMessage())->toContain($class);
    }
});

test('it throws InvalidAgentException when class does not implement AgentContract', function () {
    $class = NotAnAgent::class;

    $this->resolver->resolve($class);
})->throws(\Atlasphp\Atlas\Agents\Exceptions\InvalidAgentException::class);

test('it applies decorators when resolving instance', function () {
    $this->extensionRegistry->registerDecorator(new ResolverTestDecorator);

    $agent = new TestAgent;
    $resolved = $this->resolver->resolve($agent);

    expect($resolved->key())->toBe('decorated:test-agent');
});

test('it applies decorators when resolving from registry', function () {
    $this->extensionRegistry->registerDecorator(new ResolverTestDecorator);
    $this->registry->register(TestAgent::class);

    $resolved = $this->resolver->resolve('test-agent');

    expect($resolved->key())->toBe('decorated:test-agent');
});

test('it applies decorators when resolving from container', function () {
    $this->extensionRegistry->registerDecorator(new ResolverTestDecorator);

    $resolved = $this->resolver->resolve(TestAgent::class);

    expect($resolved->key())->toBe('decorated:test-agent');
});

test('it works without extension registry', function () {
    $resolver = new AgentResolver($this->registry, $this->container);

    $agent = new TestAgent;
    $resolved = $resolver->resolve($agent);

    expect($resolved)->toBe($agent);
});

// Test helper classes

class AgentWithUnresolvableDependency
{
    public function __construct(UnresolvableService $service) {}
}

class UnresolvableService
{
    public function __construct()
    {
        throw new \RuntimeException('Cannot instantiate');
    }
}

class NotAnAgent
{
    public function doSomething(): void {}
}

class ResolverTestDecorator extends AgentDecorator
{
    public function appliesTo(AgentContract $agent): bool
    {
        return true;
    }

    public function key(): string
    {
        return 'decorated:'.$this->agent->key();
    }
}
