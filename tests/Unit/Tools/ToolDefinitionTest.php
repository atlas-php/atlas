<?php

declare(strict_types=1);

use Atlasphp\Atlas\Tests\Fixtures\TestTool;
use Atlasphp\Atlas\Tools\Support\ToolContext;
use Atlasphp\Atlas\Tools\Support\ToolParameter;
use Atlasphp\Atlas\Tools\Support\ToolResult;
use Atlasphp\Atlas\Tools\ToolDefinition;
use Prism\Prism\Tool as PrismTool;

test('it returns empty parameters by default', function () {
    $tool = new class extends ToolDefinition
    {
        public function name(): string
        {
            return 'test';
        }

        public function description(): string
        {
            return 'Test tool';
        }

        public function handle(array $args, ToolContext $context): ToolResult
        {
            return ToolResult::text('ok');
        }
    };

    expect($tool->parameters())->toBe([]);
});

test('it converts to Prism tool', function () {
    $tool = new TestTool;
    $handler = fn (array $args) => 'result';

    $prismTool = $tool->toPrismTool($handler);

    expect($prismTool)->toBeInstanceOf(PrismTool::class);
});

test('it builds parameter schema', function () {
    $tool = new class extends ToolDefinition
    {
        public function name(): string
        {
            return 'test';
        }

        public function description(): string
        {
            return 'Test';
        }

        public function parameters(): array
        {
            return [
                ToolParameter::string('name', 'The name'),
                ToolParameter::integer('age', 'The age', false),
            ];
        }

        public function handle(array $args, ToolContext $context): ToolResult
        {
            return ToolResult::text('ok');
        }
    };

    $schema = $tool->buildParameterSchema();

    expect($schema['type'])->toBe('object');
    expect($schema['properties'])->toHaveKey('name');
    expect($schema['properties'])->toHaveKey('age');
    expect($schema['required'])->toBe(['name']);
});

test('TestTool fixture has correct name', function () {
    $tool = new TestTool;

    expect($tool->name())->toBe('test_tool');
});

test('TestTool fixture has correct description', function () {
    $tool = new TestTool;

    expect($tool->description())->toBe('A test tool that echoes input back.');
});

test('TestTool fixture handles input', function () {
    $tool = new TestTool;
    $context = new ToolContext;

    $result = $tool->handle(['input' => 'hello'], $context);

    expect($result->text)->toBe('Result: hello');
});

test('TestTool fixture handles uppercase option', function () {
    $tool = new TestTool;
    $context = new ToolContext;

    $result = $tool->handle(['input' => 'hello', 'uppercase' => true], $context);

    expect($result->text)->toBe('Result: HELLO');
});
