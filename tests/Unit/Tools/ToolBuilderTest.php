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

test('tool handler returns text property from executor result', function () {
    $tool = new TestTool;
    $context = new ToolContext;

    // Build the tool
    $tools = $this->builder->buildFromInstances([$tool], $context);
    $prismTool = $tools[0];

    // Get the handler via reflection and invoke it
    $reflection = new ReflectionClass($prismTool);
    $handlerProperty = $reflection->getProperty('fn');
    $handlerProperty->setAccessible(true);
    $handler = $handlerProperty->getValue($prismTool);

    // Execute the handler with test arguments
    $result = $handler(input: 'test input');

    // Should return the text property from ToolResult
    expect($result)->toBe('Result: test input');
});

test('it builds tool manually for non-ToolDefinition implementations', function () {
    $tool = new \Atlasphp\Atlas\Tests\Fixtures\RawToolContract;
    $context = new ToolContext;

    $tools = $this->builder->buildFromInstances([$tool], $context);

    expect($tools)->toHaveCount(1);
    expect($tools[0])->toBeInstanceOf(PrismTool::class);
});

test('manually built tool has correct name and description', function () {
    $tool = new \Atlasphp\Atlas\Tests\Fixtures\RawToolContract;
    $context = new ToolContext;

    $tools = $this->builder->buildFromInstances([$tool], $context);
    $prismTool = $tools[0];

    // Get name and description via reflection
    $reflection = new ReflectionClass($prismTool);

    $nameProperty = $reflection->getProperty('name');
    $nameProperty->setAccessible(true);

    $descProperty = $reflection->getProperty('description');
    $descProperty->setAccessible(true);

    expect($nameProperty->getValue($prismTool))->toBe('raw_tool');
    expect($descProperty->getValue($prismTool))->toBe('A raw tool that implements ToolContract directly');
});

test('manually built tool handler executes correctly', function () {
    $tool = new \Atlasphp\Atlas\Tests\Fixtures\RawToolContract;
    $context = new ToolContext;

    $tools = $this->builder->buildFromInstances([$tool], $context);
    $prismTool = $tools[0];

    // Get the handler via reflection
    $reflection = new ReflectionClass($prismTool);
    $handlerProperty = $reflection->getProperty('fn');
    $handlerProperty->setAccessible(true);
    $handler = $handlerProperty->getValue($prismTool);

    // Execute the handler
    $result = $handler(query: 'test query', limit: 5);

    expect($result)->toBe('Searched for: test query (limit: 5)');
});

test('manually built tool includes parameters', function () {
    $tool = new \Atlasphp\Atlas\Tests\Fixtures\RawToolContract;
    $context = new ToolContext;

    $tools = $this->builder->buildFromInstances([$tool], $context);
    $prismTool = $tools[0];

    // Get parameters via reflection
    $reflection = new ReflectionClass($prismTool);
    $paramsProperty = $reflection->getProperty('parameters');
    $paramsProperty->setAccessible(true);
    $parameters = $paramsProperty->getValue($prismTool);

    // Should have 2 parameters
    expect($parameters)->toHaveCount(2);
});
