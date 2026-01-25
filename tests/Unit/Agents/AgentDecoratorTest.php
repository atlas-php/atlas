<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\AgentDefinition;
use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Enums\AgentType;
use Atlasphp\Atlas\Agents\Support\AgentDecorator;
use Prism\Prism\Contracts\Schema;

beforeEach(function () {
    $this->agent = new FullFeaturedAgent;
});

test('decorate returns a clone with the agent set', function () {
    $decorator = new PassthroughDecorator;

    $result = $decorator->decorate($this->agent);

    expect($result)->toBeInstanceOf(AgentDecorator::class);
    expect($result)->not->toBe($decorator); // Should be a clone
    expect($result->key())->toBe('full-featured-agent');
});

test('priority returns 0 by default', function () {
    $decorator = new PassthroughDecorator;

    expect($decorator->priority())->toBe(0);
});

test('priority can be overridden', function () {
    $decorator = new CustomPriorityDecorator;

    expect($decorator->priority())->toBe(500);
});

test('key delegates to wrapped agent', function () {
    $decorator = new PassthroughDecorator;
    $decorated = $decorator->decorate($this->agent);

    expect($decorated->key())->toBe('full-featured-agent');
});

test('name delegates to wrapped agent', function () {
    $decorator = new PassthroughDecorator;
    $decorated = $decorator->decorate($this->agent);

    expect($decorated->name())->toBe('Full Featured Agent');
});

test('description delegates to wrapped agent', function () {
    $decorator = new PassthroughDecorator;
    $decorated = $decorator->decorate($this->agent);

    expect($decorated->description())->toBe('A test agent with all features');
});

test('provider delegates to wrapped agent', function () {
    $decorator = new PassthroughDecorator;
    $decorated = $decorator->decorate($this->agent);

    expect($decorated->provider())->toBe('anthropic');
});

test('model delegates to wrapped agent', function () {
    $decorator = new PassthroughDecorator;
    $decorated = $decorator->decorate($this->agent);

    expect($decorated->model())->toBe('claude-sonnet-4-20250514');
});

test('type delegates to wrapped agent', function () {
    $decorator = new PassthroughDecorator;
    $decorated = $decorator->decorate($this->agent);

    expect($decorated->type())->toBe(AgentType::Api);
});

test('systemPrompt delegates to wrapped agent', function () {
    $decorator = new PassthroughDecorator;
    $decorated = $decorator->decorate($this->agent);

    expect($decorated->systemPrompt())->toBe('You are a helpful assistant for {user_name}.');
});

test('tools delegates to wrapped agent', function () {
    $decorator = new PassthroughDecorator;
    $decorated = $decorator->decorate($this->agent);

    expect($decorated->tools())->toBe(['ToolA', 'ToolB']);
});

test('providerTools delegates to wrapped agent', function () {
    $decorator = new PassthroughDecorator;
    $decorated = $decorator->decorate($this->agent);

    expect($decorated->providerTools())->toBe(['web_search']);
});

test('mcpTools delegates to wrapped agent', function () {
    $decorator = new PassthroughDecorator;
    $decorated = $decorator->decorate($this->agent);

    expect($decorated->mcpTools())->toBe([]);
});

test('schema delegates to wrapped agent', function () {
    $decorator = new PassthroughDecorator;
    $decorated = $decorator->decorate($this->agent);

    expect($decorated->schema())->toBeInstanceOf(Schema::class);
});

test('temperature delegates to wrapped agent', function () {
    $decorator = new PassthroughDecorator;
    $decorated = $decorator->decorate($this->agent);

    expect($decorated->temperature())->toBe(0.7);
});

test('maxTokens delegates to wrapped agent', function () {
    $decorator = new PassthroughDecorator;
    $decorated = $decorator->decorate($this->agent);

    expect($decorated->maxTokens())->toBe(2000);
});

test('maxSteps delegates to wrapped agent', function () {
    $decorator = new PassthroughDecorator;
    $decorated = $decorator->decorate($this->agent);

    expect($decorated->maxSteps())->toBe(5);
});

test('clientOptions delegates to wrapped agent', function () {
    $decorator = new PassthroughDecorator;
    $decorated = $decorator->decorate($this->agent);

    expect($decorated->clientOptions())->toBe(['timeout' => 30]);
});

test('providerOptions delegates to wrapped agent', function () {
    $decorator = new PassthroughDecorator;
    $decorated = $decorator->decorate($this->agent);

    expect($decorated->providerOptions())->toBe(['top_p' => 0.9]);
});

test('decorator can override key method', function () {
    $decorator = new KeyOverridingDecorator;
    $decorated = $decorator->decorate($this->agent);

    expect($decorated->key())->toBe('decorated:full-featured-agent');
});

test('decorator can override systemPrompt method', function () {
    $decorator = new PromptOverridingDecorator;
    $decorated = $decorator->decorate($this->agent);

    expect($decorated->systemPrompt())->toContain('You are a helpful assistant');
    expect($decorated->systemPrompt())->toContain('[Enhanced Mode]');
});

test('decorator can override tools method', function () {
    $decorator = new ToolsOverridingDecorator;
    $decorated = $decorator->decorate($this->agent);

    expect($decorated->tools())->toBe(['ToolA', 'ToolB', 'InjectedTool']);
});

test('decorator can override temperature method', function () {
    $decorator = new TemperatureOverridingDecorator;
    $decorated = $decorator->decorate($this->agent);

    expect($decorated->temperature())->toBe(0.0);
});

test('decorator can override maxTokens method', function () {
    $decorator = new MaxTokensOverridingDecorator;
    $decorated = $decorator->decorate($this->agent);

    expect($decorated->maxTokens())->toBe(4000);
});

test('decorator handles agent with null values', function () {
    $agent = new MinimalAgent;
    $decorator = new PassthroughDecorator;
    $decorated = $decorator->decorate($agent);

    expect($decorated->description())->toBeNull();
    expect($decorated->systemPrompt())->toBeNull();
    expect($decorated->schema())->toBeNull();
    expect($decorated->temperature())->toBeNull();
    expect($decorated->maxTokens())->toBeNull();
    expect($decorated->maxSteps())->toBeNull();
});

test('multiple decorators can be chained', function () {
    $first = new KeyOverridingDecorator;
    $second = new PromptOverridingDecorator;

    $decorated = $first->decorate($this->agent);
    $doubleDecorated = $second->decorate($decorated);

    expect($doubleDecorated->key())->toBe('decorated:full-featured-agent');
    expect($doubleDecorated->systemPrompt())->toContain('[Enhanced Mode]');
});

// Test Fixtures

class PassthroughDecorator extends AgentDecorator
{
    public function appliesTo(AgentContract $agent): bool
    {
        return true;
    }
}

class CustomPriorityDecorator extends AgentDecorator
{
    public function appliesTo(AgentContract $agent): bool
    {
        return true;
    }

    public function priority(): int
    {
        return 500;
    }
}

class KeyOverridingDecorator extends AgentDecorator
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

class PromptOverridingDecorator extends AgentDecorator
{
    public function appliesTo(AgentContract $agent): bool
    {
        return true;
    }

    public function systemPrompt(): ?string
    {
        return $this->agent->systemPrompt()."\n\n[Enhanced Mode]";
    }
}

class ToolsOverridingDecorator extends AgentDecorator
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

class TemperatureOverridingDecorator extends AgentDecorator
{
    public function appliesTo(AgentContract $agent): bool
    {
        return true;
    }

    public function temperature(): ?float
    {
        return 0.0;
    }
}

class MaxTokensOverridingDecorator extends AgentDecorator
{
    public function appliesTo(AgentContract $agent): bool
    {
        return true;
    }

    public function maxTokens(): ?int
    {
        return 4000;
    }
}

class FullFeaturedAgent extends AgentDefinition
{
    public function key(): string
    {
        return 'full-featured-agent';
    }

    public function name(): string
    {
        return 'Full Featured Agent';
    }

    public function description(): ?string
    {
        return 'A test agent with all features';
    }

    public function provider(): ?string
    {
        return 'anthropic';
    }

    public function model(): ?string
    {
        return 'claude-sonnet-4-20250514';
    }

    public function systemPrompt(): ?string
    {
        return 'You are a helpful assistant for {user_name}.';
    }

    public function tools(): array
    {
        return ['ToolA', 'ToolB'];
    }

    public function providerTools(): array
    {
        return ['web_search'];
    }

    public function schema(): ?Schema
    {
        return Mockery::mock(Schema::class);
    }

    public function temperature(): ?float
    {
        return 0.7;
    }

    public function maxTokens(): ?int
    {
        return 2000;
    }

    public function maxSteps(): ?int
    {
        return 5;
    }

    public function clientOptions(): array
    {
        return ['timeout' => 30];
    }

    public function providerOptions(): array
    {
        return ['top_p' => 0.9];
    }
}

class MinimalAgent extends AgentDefinition
{
    public function key(): string
    {
        return 'minimal-agent';
    }

    public function provider(): ?string
    {
        return 'openai';
    }

    public function model(): ?string
    {
        return 'gpt-4';
    }
}
