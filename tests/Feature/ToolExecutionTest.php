<?php

declare(strict_types=1);

use Atlasphp\Atlas\Tests\Fixtures\TestTool;
use Atlasphp\Atlas\Tools\Contracts\ToolContract;
use Atlasphp\Atlas\Tools\Contracts\ToolRegistryContract;
use Atlasphp\Atlas\Tools\Services\ToolBuilder;
use Atlasphp\Atlas\Tools\Services\ToolExecutor;
use Atlasphp\Atlas\Tools\Services\ToolRegistry;
use Atlasphp\Atlas\Tools\Support\ToolContext;
use Atlasphp\Atlas\Tools\Support\ToolResult;
use Atlasphp\Atlas\Tools\ToolDefinition;
use Prism\Prism\Tool as PrismTool;

test('it registers tool services in container', function () {
    expect(app(ToolRegistryContract::class))->toBeInstanceOf(ToolRegistry::class);
    expect(app(ToolExecutor::class))->toBeInstanceOf(ToolExecutor::class);
    expect(app(ToolBuilder::class))->toBeInstanceOf(ToolBuilder::class);
});

test('it resolves services as singletons', function () {
    $registry1 = app(ToolRegistryContract::class);
    $registry2 = app(ToolRegistryContract::class);

    expect($registry1)->toBe($registry2);
});

test('tool registry registers and retrieves tools', function () {
    $registry = app(ToolRegistryContract::class);

    $registry->register(TestTool::class);

    expect($registry->has('test_tool'))->toBeTrue();

    $tool = $registry->get('test_tool');
    expect($tool)->toBeInstanceOf(ToolContract::class);
    expect($tool->name())->toBe('test_tool');
});

test('tool executor runs tool successfully', function () {
    $executor = app(ToolExecutor::class);
    $tool = new TestTool;
    $context = new ToolContext;

    $result = $executor->execute($tool, ['input' => 'hello world'], $context);

    expect($result)->toBeInstanceOf(ToolResult::class);
    expect($result->text)->toBe('Result: hello world');
    expect($result->isError)->toBeFalse();
});

test('tool executor passes context metadata', function () {
    $executor = app(ToolExecutor::class);

    $tool = new class extends ToolDefinition
    {
        public function name(): string
        {
            return 'meta_tool';
        }

        public function description(): string
        {
            return 'Returns meta value';
        }

        public function handle(array $args, ToolContext $context): ToolResult
        {
            return ToolResult::text('Meta: '.$context->getMeta('key', 'default'));
        }
    };

    $context = new ToolContext(['key' => 'custom_value']);
    $result = $executor->execute($tool, [], $context);

    expect($result->text)->toBe('Meta: custom_value');
});

test('tool builder creates prism tools', function () {
    $builder = app(ToolBuilder::class);
    $context = new ToolContext;

    $tools = $builder->buildFromClasses([TestTool::class], $context);

    expect($tools)->toHaveCount(1);
    expect($tools[0])->toBeInstanceOf(PrismTool::class);
});

test('tool builder handles tool with multiple parameters', function () {
    $builder = app(ToolBuilder::class);
    $context = new ToolContext;

    $tools = $builder->buildFromInstances([new TestTool], $context);

    expect($tools)->toHaveCount(1);
    // TestTool has 2 parameters: input (required) and uppercase (optional)
    expect($tools[0])->toBeInstanceOf(PrismTool::class);
});

test('tool handles errors gracefully', function () {
    $executor = app(ToolExecutor::class);

    $tool = new class extends ToolDefinition
    {
        public function name(): string
        {
            return 'error_tool';
        }

        public function description(): string
        {
            return 'Throws error';
        }

        public function handle(array $args, ToolContext $context): ToolResult
        {
            throw new RuntimeException('Test error');
        }
    };

    $context = new ToolContext;
    $result = $executor->execute($tool, [], $context);

    expect($result->isError)->toBeTrue();
    expect($result->text)->toContain('Test error');
});
