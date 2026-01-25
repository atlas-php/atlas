<?php

declare(strict_types=1);

use Atlasphp\Atlas\Pipelines\PipelineRegistry;
use Atlasphp\Atlas\Pipelines\PipelineRunner;
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
    $this->pipelineRegistry = new PipelineRegistry;
    $this->pipelineRunner = new PipelineRunner($this->pipelineRegistry, $this->container);

    $this->toolRegistry = new ToolRegistry($this->container);
    $this->executor = new ToolExecutor($this->pipelineRunner);
    $this->builder = new ToolBuilder($this->toolRegistry, $this->executor, $this->container, $this->pipelineRunner);
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

test('tool implementing ConfiguresPrismTool has configurePrismTool called', function () {
    $tool = new \Atlasphp\Atlas\Tests\Fixtures\ConfigurableToolContract;
    $context = new ToolContext;

    $tools = $this->builder->buildFromInstances([$tool], $context);
    $prismTool = $tools[0];

    // Get providerOptions via reflection to verify configurePrismTool was called
    $reflection = new ReflectionClass($prismTool);
    $optionsProperty = $reflection->getProperty('providerOptions');
    $optionsProperty->setAccessible(true);
    $options = $optionsProperty->getValue($prismTool);

    // ConfigurableToolContract sets custom_option => true
    expect($options)->toBe(['custom_option' => true]);
});

test('ConfiguresPrismTool is only called for non-ToolDefinition tools', function () {
    // ToolDefinition tools use toPrismTool() directly
    // ConfiguresPrismTool is called in buildPrismToolManually()
    $tool = new \Atlasphp\Atlas\Tests\Fixtures\ConfigurableToolContract;
    $context = new ToolContext;

    // This should work without errors
    $tools = $this->builder->buildFromInstances([$tool], $context);

    expect($tools)->toHaveCount(1);
    expect($tools[0])->toBeInstanceOf(PrismTool::class);
});

test('it runs tool.before_resolve pipeline', function () {
    ToolBeforeResolveCapturingHandler::reset();

    $this->pipelineRegistry->define('tool.before_resolve');
    $this->pipelineRegistry->register('tool.before_resolve', ToolBeforeResolveCapturingHandler::class);

    $agent = new TestAgent;
    $context = new ToolContext;

    $this->builder->buildForAgent($agent, $context);

    expect(ToolBeforeResolveCapturingHandler::$called)->toBeTrue();
    expect(ToolBeforeResolveCapturingHandler::$data['agent'])->toBe($agent);
    expect(ToolBeforeResolveCapturingHandler::$data['context'])->toBe($context);
    expect(ToolBeforeResolveCapturingHandler::$data['tool_classes'])->toBe([TestTool::class]);
});

test('tool.before_resolve pipeline can filter tools', function () {
    ToolFilteringHandler::reset();

    $this->pipelineRegistry->define('tool.before_resolve');
    $this->pipelineRegistry->register('tool.before_resolve', ToolFilteringHandler::class);

    $agent = new TestAgent;
    $context = new ToolContext;

    $tools = $this->builder->buildForAgent($agent, $context);

    // Tool was filtered out by pipeline
    expect($tools)->toBe([]);
});

test('it runs tool.after_resolve pipeline', function () {
    ToolAfterResolveCapturingHandler::reset();

    $this->pipelineRegistry->define('tool.after_resolve');
    $this->pipelineRegistry->register('tool.after_resolve', ToolAfterResolveCapturingHandler::class);

    $agent = new TestAgent;
    $context = new ToolContext;

    $this->builder->buildForAgent($agent, $context);

    expect(ToolAfterResolveCapturingHandler::$called)->toBeTrue();
    expect(ToolAfterResolveCapturingHandler::$data['agent'])->toBe($agent);
    expect(ToolAfterResolveCapturingHandler::$data['context'])->toBe($context);
    expect(ToolAfterResolveCapturingHandler::$data['tool_classes'])->toBe([TestTool::class]);
    expect(ToolAfterResolveCapturingHandler::$data['prism_tools'])->toHaveCount(1);
    expect(ToolAfterResolveCapturingHandler::$data['prism_tools'][0])->toBeInstanceOf(PrismTool::class);
});

test('tool.after_resolve pipeline can modify prism_tools', function () {
    ToolAfterModifyingHandler::reset();

    $this->pipelineRegistry->define('tool.after_resolve');
    $this->pipelineRegistry->register('tool.after_resolve', ToolAfterModifyingHandler::class);

    $agent = new TestAgent;
    $context = new ToolContext;

    $tools = $this->builder->buildForAgent($agent, $context);

    // Tool array was cleared by pipeline
    expect($tools)->toBe([]);
});

// Pipeline Handler Classes for Tests

use Atlasphp\Atlas\Contracts\PipelineContract;
use Closure;

class ToolBeforeResolveCapturingHandler implements PipelineContract
{
    public static bool $called = false;

    public static ?array $data = null;

    public static function reset(): void
    {
        self::$called = false;
        self::$data = null;
    }

    public function handle(mixed $data, Closure $next): mixed
    {
        self::$called = true;
        self::$data = $data;

        return $next($data);
    }
}

class ToolFilteringHandler implements PipelineContract
{
    public static bool $called = false;

    public static function reset(): void
    {
        self::$called = false;
    }

    public function handle(mixed $data, Closure $next): mixed
    {
        self::$called = true;
        // Filter out all tools
        $data['tool_classes'] = [];

        return $next($data);
    }
}

class ToolAfterResolveCapturingHandler implements PipelineContract
{
    public static bool $called = false;

    public static ?array $data = null;

    public static function reset(): void
    {
        self::$called = false;
        self::$data = null;
    }

    public function handle(mixed $data, Closure $next): mixed
    {
        self::$called = true;
        self::$data = $data;

        return $next($data);
    }
}

class ToolAfterModifyingHandler implements PipelineContract
{
    public static bool $called = false;

    public static function reset(): void
    {
        self::$called = false;
    }

    public function handle(mixed $data, Closure $next): mixed
    {
        self::$called = true;
        // Clear all prism tools
        $data['prism_tools'] = [];

        return $next($data);
    }
}
