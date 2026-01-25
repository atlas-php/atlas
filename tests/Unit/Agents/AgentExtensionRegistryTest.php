<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Services\AgentExtensionRegistry;
use Atlasphp\Atlas\Agents\Support\AgentDecorator;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;

beforeEach(function () {
    $this->registry = new AgentExtensionRegistry;
});

test('it registers a decorator', function () {
    $decorator = new TestDecorator;

    $this->registry->registerDecorator($decorator);

    expect($this->registry->hasDecorators())->toBeTrue();
    expect($this->registry->decoratorCount())->toBe(1);
});

test('it registers multiple decorators', function () {
    $this->registry->registerDecorator(new TestDecorator);
    $this->registry->registerDecorator(new AnotherTestDecorator);

    expect($this->registry->decoratorCount())->toBe(2);
});

test('it applies decorators to agent', function () {
    $decorator = new ToolAddingDecorator;
    $this->registry->registerDecorator($decorator);

    $agent = new TestAgent;
    $decorated = $this->registry->applyDecorators($agent);

    // Decorated agent should have additional tool
    expect($decorated->tools())->toContain('InjectedTool');
});

test('it applies decorators in priority order', function () {
    // Register in wrong order
    $this->registry->registerDecorator(new LowPriorityDecorator);
    $this->registry->registerDecorator(new HighPriorityDecorator);

    $agent = new TestAgent;
    $decorated = $this->registry->applyDecorators($agent);

    // High priority applied first, so low priority wraps it
    // The final key should reflect the order: low -> high -> original
    expect($decorated->key())->toBe('low-priority:high-priority:test-agent');
});

test('it only applies decorators that match', function () {
    $this->registry->registerDecorator(new SelectiveDecorator);

    // TestAgent should match
    $testAgent = new TestAgent;
    $decorated = $this->registry->applyDecorators($testAgent);
    expect($decorated->key())->toBe('selected:test-agent');

    // Non-matching agent should not be decorated
    $otherAgent = new NonMatchingAgent;
    $notDecorated = $this->registry->applyDecorators($otherAgent);
    expect($notDecorated->key())->toBe('non-matching');
});

test('it clears decorators', function () {
    $this->registry->registerDecorator(new TestDecorator);
    $this->registry->registerDecorator(new AnotherTestDecorator);

    expect($this->registry->decoratorCount())->toBe(2);

    $this->registry->clearDecorators();

    expect($this->registry->hasDecorators())->toBeFalse();
    expect($this->registry->decoratorCount())->toBe(0);
});

test('it returns original agent when no decorators apply', function () {
    $decorator = new SelectiveDecorator;
    $this->registry->registerDecorator($decorator);

    $agent = new NonMatchingAgent;
    $result = $this->registry->applyDecorators($agent);

    expect($result)->toBe($agent);
});

test('registerDecorator returns self for chaining', function () {
    $result = $this->registry->registerDecorator(new TestDecorator);

    expect($result)->toBe($this->registry);
});

test('clearDecorators returns self for chaining', function () {
    $result = $this->registry->clearDecorators();

    expect($result)->toBe($this->registry);
});

// Test Fixtures

class TestDecorator extends AgentDecorator
{
    public function appliesTo(AgentContract $agent): bool
    {
        return true;
    }
}

class AnotherTestDecorator extends AgentDecorator
{
    public function appliesTo(AgentContract $agent): bool
    {
        return true;
    }
}

class ToolAddingDecorator extends AgentDecorator
{
    public function appliesTo(AgentContract $agent): bool
    {
        return true;
    }

    public function tools(): array
    {
        return array_merge($this->agent->tools(), ['InjectedTool']);
    }
}

class HighPriorityDecorator extends AgentDecorator
{
    public function priority(): int
    {
        return 100;
    }

    public function appliesTo(AgentContract $agent): bool
    {
        return true;
    }

    public function key(): string
    {
        return 'high-priority:'.$this->agent->key();
    }
}

class LowPriorityDecorator extends AgentDecorator
{
    public function priority(): int
    {
        return 10;
    }

    public function appliesTo(AgentContract $agent): bool
    {
        return true;
    }

    public function key(): string
    {
        return 'low-priority:'.$this->agent->key();
    }
}

class SelectiveDecorator extends AgentDecorator
{
    public function appliesTo(AgentContract $agent): bool
    {
        return $agent->key() === 'test-agent';
    }

    public function key(): string
    {
        return 'selected:'.$this->agent->key();
    }
}

class NonMatchingAgent extends \Atlasphp\Atlas\Agents\AgentDefinition
{
    public function key(): string
    {
        return 'non-matching';
    }

    public function provider(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return 'gpt-4';
    }

    public function systemPrompt(): string
    {
        return 'Test';
    }
}
