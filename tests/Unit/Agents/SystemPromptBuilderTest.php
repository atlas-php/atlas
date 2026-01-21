<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Services\SystemPromptBuilder;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;
use Atlasphp\Atlas\Foundation\Services\PipelineRunner;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Illuminate\Container\Container;

beforeEach(function () {
    $container = new Container;
    $registry = new PipelineRegistry;
    $this->runner = new PipelineRunner($registry, $container);
    $this->builder = new SystemPromptBuilder($this->runner);
});

test('it builds system prompt from agent', function () {
    $agent = new TestAgent;
    $context = new ExecutionContext;

    $prompt = $this->builder->build($agent, $context);

    // TestAgent has: 'You are {agent_name}. Help {user_name} with their request.'
    // Without variables, placeholders remain
    expect($prompt)->toContain('{agent_name}');
    expect($prompt)->toContain('{user_name}');
});

test('it interpolates variables', function () {
    $agent = new TestAgent;
    $context = new ExecutionContext(variables: [
        'agent_name' => 'Atlas',
        'user_name' => 'John',
    ]);

    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toContain('You are Atlas');
    expect($prompt)->toContain('Help John');
});

test('it uses global variables', function () {
    $this->builder->registerVariable('agent_name', 'GlobalAgent');

    $agent = new TestAgent;
    $context = new ExecutionContext;

    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toContain('You are GlobalAgent');
});

test('context variables override global variables', function () {
    $this->builder->registerVariable('agent_name', 'GlobalAgent');

    $agent = new TestAgent;
    $context = new ExecutionContext(variables: ['agent_name' => 'ContextAgent']);

    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toContain('You are ContextAgent');
});

test('it unregisters global variable', function () {
    $this->builder->registerVariable('agent_name', 'Test');
    $this->builder->unregisterVariable('agent_name');

    $agent = new TestAgent;
    $context = new ExecutionContext;

    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toContain('{agent_name}');
});

test('it adds sections to prompt', function () {
    $this->builder->addSection('rules', '## Rules\nFollow these rules.');

    $agent = new TestAgent;
    $context = new ExecutionContext;

    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toContain('## Rules');
    expect($prompt)->toContain('Follow these rules.');
});

test('it removes section', function () {
    $this->builder->addSection('rules', '## Rules');
    $this->builder->removeSection('rules');

    $agent = new TestAgent;
    $context = new ExecutionContext;

    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->not->toContain('## Rules');
});

test('it clears all sections', function () {
    $this->builder->addSection('rules', '## Rules');
    $this->builder->addSection('examples', '## Examples');
    $this->builder->clearSections();

    $agent = new TestAgent;
    $context = new ExecutionContext;

    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->not->toContain('## Rules');
    expect($prompt)->not->toContain('## Examples');
});

test('it handles boolean variables', function () {
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'test';
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
            return 'Debug mode: {debug}';
        }
    };

    $context = new ExecutionContext(variables: ['debug' => true]);
    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toContain('Debug mode: true');
});

test('it handles array variables as JSON', function () {
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'test';
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
            return 'Data: {data}';
        }
    };

    $context = new ExecutionContext(variables: ['data' => ['a' => 1]]);
    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toContain('{"a":1}');
});
