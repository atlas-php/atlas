<?php

declare(strict_types=1);

use Atlasphp\Atlas\Pipelines\PipelineRegistry;
use Atlasphp\Atlas\Pipelines\PipelineRunner;
use Atlasphp\Atlas\Tests\Fixtures\TestTool;
use Atlasphp\Atlas\Tools\Contracts\ToolContract;
use Atlasphp\Atlas\Tools\Services\ToolExecutor;
use Atlasphp\Atlas\Tools\Support\ToolContext;
use Atlasphp\Atlas\Tools\Support\ToolResult;
use Illuminate\Container\Container;

beforeEach(function () {
    $container = new Container;
    $registry = new PipelineRegistry;
    $this->runner = new PipelineRunner($registry, $container);
    $this->executor = new ToolExecutor($this->runner);
});

test('it executes tool successfully', function () {
    $tool = new TestTool;
    $context = new ToolContext;

    $result = $this->executor->execute($tool, ['input' => 'hello'], $context);

    expect($result->text)->toBe('Result: hello');
    expect($result->isError)->toBeFalse();
});

test('it returns error result on exception', function () {
    $tool = new class implements ToolContract
    {
        public function name(): string
        {
            return 'failing_tool';
        }

        public function description(): string
        {
            return 'A tool that fails';
        }

        public function parameters(): array
        {
            return [];
        }

        public function handle(array $args, ToolContext $context): ToolResult
        {
            throw new \RuntimeException('Tool failed');
        }
    };

    $context = new ToolContext;
    $result = $this->executor->execute($tool, [], $context);

    expect($result->isError)->toBeTrue();
    expect($result->text)->toContain('failed');
});

test('it passes arguments to tool', function () {
    $tool = new TestTool;
    $context = new ToolContext;

    $result = $this->executor->execute(
        $tool,
        ['input' => 'test', 'uppercase' => true],
        $context,
    );

    expect($result->text)->toBe('Result: TEST');
});

test('it passes context to tool', function () {
    $tool = new class implements ToolContract
    {
        public function name(): string
        {
            return 'context_tool';
        }

        public function description(): string
        {
            return 'Returns context value';
        }

        public function parameters(): array
        {
            return [];
        }

        public function handle(array $args, ToolContext $context): ToolResult
        {
            return ToolResult::text('Value: '.$context->getMeta('key', 'none'));
        }
    };

    $context = new ToolContext(['key' => 'value']);
    $result = $this->executor->execute($tool, [], $context);

    expect($result->text)->toBe('Value: value');
});

test('it handles tool exception gracefully', function () {
    $tool = new class implements ToolContract
    {
        public function name(): string
        {
            return 'exception_tool';
        }

        public function description(): string
        {
            return 'Throws exception';
        }

        public function parameters(): array
        {
            return [];
        }

        public function handle(array $args, ToolContext $context): ToolResult
        {
            throw new \Atlasphp\Atlas\Tools\Exceptions\ToolException('Specific error');
        }
    };

    $context = new ToolContext;
    $result = $this->executor->execute($tool, [], $context);

    expect($result->isError)->toBeTrue();
    expect($result->text)->toBe('Specific error');
});

test('it includes tool name in generic error', function () {
    $tool = new class implements ToolContract
    {
        public function name(): string
        {
            return 'my_tool';
        }

        public function description(): string
        {
            return 'Fails';
        }

        public function parameters(): array
        {
            return [];
        }

        public function handle(array $args, ToolContext $context): ToolResult
        {
            throw new \Exception('Generic error');
        }
    };

    $context = new ToolContext;
    $result = $this->executor->execute($tool, [], $context);

    expect($result->text)->toContain("'my_tool'");
    expect($result->text)->toContain('Generic error');
});

test('it runs tool.on_error pipeline when tool throws generic exception', function () {
    $container = new Container;
    $registry = new PipelineRegistry;
    $runner = new PipelineRunner($registry, $container);

    // Define and activate the error pipeline
    $registry->define('tool.on_error', 'Error pipeline');

    // Reset static state
    ToolErrorCapturingHandler::reset();
    $registry->register('tool.on_error', ToolErrorCapturingHandler::class);

    $executor = new ToolExecutor($runner);

    $tool = new class implements ToolContract
    {
        public function name(): string
        {
            return 'failing_tool';
        }

        public function description(): string
        {
            return 'A tool that fails';
        }

        public function parameters(): array
        {
            return [];
        }

        public function handle(array $args, ToolContext $context): ToolResult
        {
            throw new \RuntimeException('Tool execution failed');
        }
    };

    $context = new ToolContext(['test_key' => 'test_value']);
    $executor->execute($tool, ['arg1' => 'value1'], $context);

    expect(ToolErrorCapturingHandler::$called)->toBeTrue();
    expect(ToolErrorCapturingHandler::$data)->not->toBeNull();
    expect(ToolErrorCapturingHandler::$data['tool'])->toBe($tool);
    expect(ToolErrorCapturingHandler::$data['args'])->toBe(['arg1' => 'value1']);
    expect(ToolErrorCapturingHandler::$data['context'])->toBe($context);
    expect(ToolErrorCapturingHandler::$data['exception'])->toBeInstanceOf(\RuntimeException::class);
    expect(ToolErrorCapturingHandler::$data['exception']->getMessage())->toBe('Tool execution failed');
});

test('it runs tool.on_error pipeline when tool throws ToolException', function () {
    $container = new Container;
    $registry = new PipelineRegistry;
    $runner = new PipelineRunner($registry, $container);

    // Define and activate the error pipeline
    $registry->define('tool.on_error', 'Error pipeline');

    // Reset static state
    ToolErrorCapturingHandler::reset();
    $registry->register('tool.on_error', ToolErrorCapturingHandler::class);

    $executor = new ToolExecutor($runner);

    $tool = new class implements ToolContract
    {
        public function name(): string
        {
            return 'tool_exception_tool';
        }

        public function description(): string
        {
            return 'Throws ToolException';
        }

        public function parameters(): array
        {
            return [];
        }

        public function handle(array $args, ToolContext $context): ToolResult
        {
            throw new \Atlasphp\Atlas\Tools\Exceptions\ToolException('Specific tool error');
        }
    };

    $context = new ToolContext;
    $executor->execute($tool, [], $context);

    expect(ToolErrorCapturingHandler::$called)->toBeTrue();
    expect(ToolErrorCapturingHandler::$data)->not->toBeNull();
    expect(ToolErrorCapturingHandler::$data['exception'])->toBeInstanceOf(\Atlasphp\Atlas\Tools\Exceptions\ToolException::class);
    expect(ToolErrorCapturingHandler::$data['exception']->getMessage())->toBe('Specific tool error');
});

// Pipeline Handler Class for Tests

class ToolErrorCapturingHandler implements \Atlasphp\Atlas\Contracts\PipelineContract
{
    public static bool $called = false;

    public static ?array $data = null;

    public static function reset(): void
    {
        self::$called = false;
        self::$data = null;
    }

    public function handle(mixed $data, \Closure $next): mixed
    {
        self::$called = true;
        self::$data = $data;

        return $next($data);
    }
}
