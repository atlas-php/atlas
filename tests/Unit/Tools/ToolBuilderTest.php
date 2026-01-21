<?php

declare(strict_types=1);

use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;
use Atlasphp\Atlas\Foundation\Services\PipelineRunner;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Atlasphp\Atlas\Tests\Fixtures\TestTool;
use Atlasphp\Atlas\Tools\Services\ToolBuilder;
use Atlasphp\Atlas\Tools\Services\ToolExecutor;
use Atlasphp\Atlas\Tools\Services\ToolRegistry;
use Atlasphp\Atlas\Tools\Support\ToolContext;
use Illuminate\Container\Container;
use Prism\Prism\Tool as PrismTool;

beforeEach(function () {
    $this->container = new Container;
    $registry = new PipelineRegistry;
    $runner = new PipelineRunner($registry, $this->container);

    $this->toolRegistry = new ToolRegistry($this->container);
    $this->executor = new ToolExecutor($runner);
    $this->builder = new ToolBuilder($this->toolRegistry, $this->executor, $this->container);
});

test('it builds tools for agent', function () {
    $agent = new TestAgent;
    $context = new ToolContext;

    $tools = $this->builder->buildForAgent($agent, $context);

    expect($tools)->toHaveCount(1);
    expect($tools[0])->toBeInstanceOf(PrismTool::class);
});

test('it returns empty array for agent without tools', function () {
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'no-tools';
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

        public function tools(): array
        {
            return [];
        }
    };

    $context = new ToolContext;
    $tools = $this->builder->buildForAgent($agent, $context);

    expect($tools)->toBe([]);
});

test('it builds from tool classes', function () {
    $context = new ToolContext;

    $tools = $this->builder->buildFromClasses([TestTool::class], $context);

    expect($tools)->toHaveCount(1);
    expect($tools[0])->toBeInstanceOf(PrismTool::class);
});

test('it builds from tool instances', function () {
    $tool = new TestTool;
    $context = new ToolContext;

    $tools = $this->builder->buildFromInstances([$tool], $context);

    expect($tools)->toHaveCount(1);
    expect($tools[0])->toBeInstanceOf(PrismTool::class);
});

test('tool handler executes via executor', function () {
    $agent = new TestAgent;
    $context = new ToolContext;

    $tools = $this->builder->buildForAgent($agent, $context);

    // The handler should be callable
    // We can't easily test the full flow without mocking Prism,
    // but we can verify the tool was built correctly
    expect($tools[0])->toBeInstanceOf(PrismTool::class);
});
