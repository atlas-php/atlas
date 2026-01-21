<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Exceptions\AgentException;
use Atlasphp\Atlas\Agents\Exceptions\AgentNotFoundException;
use Atlasphp\Atlas\Agents\Services\AgentRegistry;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Illuminate\Container\Container;

beforeEach(function () {
    $this->container = new Container;
    $this->registry = new AgentRegistry($this->container);
});

test('it registers agent class', function () {
    $this->registry->register(TestAgent::class);

    expect($this->registry->has('test-agent'))->toBeTrue();
});

test('it registers agent instance', function () {
    $agent = new TestAgent;

    $this->registry->registerInstance($agent);

    expect($this->registry->has('test-agent'))->toBeTrue();
});

test('it throws on duplicate registration', function () {
    $this->registry->register(TestAgent::class);

    $this->registry->register(TestAgent::class);
})->throws(AgentException::class, "An agent with key 'test-agent' has already been registered.");

test('it allows override on duplicate registration', function () {
    $this->registry->register(TestAgent::class);
    $this->registry->register(TestAgent::class, override: true);

    expect($this->registry->has('test-agent'))->toBeTrue();
});

test('it gets agent by key', function () {
    $this->registry->register(TestAgent::class);

    $agent = $this->registry->get('test-agent');

    expect($agent)->toBeInstanceOf(AgentContract::class);
    expect($agent->key())->toBe('test-agent');
});

test('it throws when getting unknown agent', function () {
    $this->registry->get('nonexistent');
})->throws(AgentNotFoundException::class, "No agent found with key 'nonexistent'.");

test('it reports has correctly', function () {
    expect($this->registry->has('test-agent'))->toBeFalse();

    $this->registry->register(TestAgent::class);

    expect($this->registry->has('test-agent'))->toBeTrue();
});

test('it returns all agents', function () {
    $this->registry->register(TestAgent::class);

    $all = $this->registry->all();

    expect($all)->toHaveKey('test-agent');
    expect($all['test-agent'])->toBeInstanceOf(AgentContract::class);
});

test('it returns all keys', function () {
    $this->registry->register(TestAgent::class);

    $keys = $this->registry->keys();

    expect($keys)->toBe(['test-agent']);
});

test('it unregisters agent', function () {
    $this->registry->register(TestAgent::class);

    $result = $this->registry->unregister('test-agent');

    expect($result)->toBeTrue();
    expect($this->registry->has('test-agent'))->toBeFalse();
});

test('it returns false when unregistering unknown agent', function () {
    $result = $this->registry->unregister('nonexistent');

    expect($result)->toBeFalse();
});

test('it counts registered agents', function () {
    expect($this->registry->count())->toBe(0);

    $this->registry->register(TestAgent::class);

    expect($this->registry->count())->toBe(1);
});

test('it clears all agents', function () {
    $this->registry->register(TestAgent::class);
    $this->registry->clear();

    expect($this->registry->count())->toBe(0);
});
