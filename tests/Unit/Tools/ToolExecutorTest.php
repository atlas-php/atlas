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

    expect($result->toText())->toBe('Result: hello');
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

        public function handle(array $params, ToolContext $context): ToolResult
        {
            throw new \RuntimeException('Tool failed');
        }
    };

    $context = new ToolContext;
    $result = $this->executor->execute($tool, [], $context);

    expect($result->isError)->toBeTrue();
    expect($result->toText())->toContain('failed');
});

test('it passes arguments to tool', function () {
    $tool = new TestTool;
    $context = new ToolContext;

    $result = $this->executor->execute(
        $tool,
        ['input' => 'test', 'uppercase' => true],
        $context,
    );

    expect($result->toText())->toBe('Result: TEST');
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

        public function handle(array $params, ToolContext $context): ToolResult
        {
            return ToolResult::text('Value: '.$context->getMeta('key', 'none'));
        }
    };

    $context = new ToolContext(['key' => 'value']);
    $result = $this->executor->execute($tool, [], $context);

    expect($result->toText())->toBe('Value: value');
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

        public function handle(array $params, ToolContext $context): ToolResult
        {
            throw new \Atlasphp\Atlas\Tools\Exceptions\ToolException('Specific error');
        }
    };

    $context = new ToolContext;
    $result = $this->executor->execute($tool, [], $context);

    expect($result->isError)->toBeTrue();
    expect($result->toText())->toBe('Specific error');
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

        public function handle(array $params, ToolContext $context): ToolResult
        {
            throw new \Exception('Generic error');
        }
    };

    $context = new ToolContext;
    $result = $this->executor->execute($tool, [], $context);

    expect($result->toText())->toContain("'my_tool'");
    expect($result->toText())->toContain('Generic error');
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

        public function handle(array $params, ToolContext $context): ToolResult
        {
            throw new \RuntimeException('Tool execution failed');
        }
    };

    $context = new ToolContext(['test_key' => 'test_value']);
    $executor->execute($tool, ['arg1' => 'value1'], $context);

    expect(ToolErrorCapturingHandler::$called)->toBeTrue();
    expect(ToolErrorCapturingHandler::$data)->not->toBeNull();
    expect(ToolErrorCapturingHandler::$data['tool'])->toBe($tool);
    expect(ToolErrorCapturingHandler::$data['params'])->toBe(['arg1' => 'value1']);
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

        public function handle(array $params, ToolContext $context): ToolResult
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

test('it runs tool.before_execute pipeline with correct data', function () {
    $container = new Container;
    $registry = new PipelineRegistry;
    $runner = new PipelineRunner($registry, $container);

    $registry->define('tool.before_execute', 'Before execute pipeline');
    ToolBeforeExecuteCapturingHandler::reset();
    $registry->register('tool.before_execute', ToolBeforeExecuteCapturingHandler::class);

    $executor = new ToolExecutor($runner);

    $tool = new TestTool;
    $context = new ToolContext(['user_id' => 123]);

    $executor->execute($tool, ['input' => 'test value'], $context);

    expect(ToolBeforeExecuteCapturingHandler::$called)->toBeTrue();
    expect(ToolBeforeExecuteCapturingHandler::$data)->not->toBeNull();
    expect(ToolBeforeExecuteCapturingHandler::$data['tool'])->toBe($tool);
    expect(ToolBeforeExecuteCapturingHandler::$data['params'])->toBe(['input' => 'test value']);
    expect(ToolBeforeExecuteCapturingHandler::$data['context'])->toBe($context);
});

test('tool.before_execute pipeline can modify args', function () {
    $container = new Container;
    $registry = new PipelineRegistry;
    $runner = new PipelineRunner($registry, $container);

    $registry->define('tool.before_execute', 'Before execute pipeline');
    ToolBeforeExecuteModifyingHandler::reset();
    $registry->register('tool.before_execute', ToolBeforeExecuteModifyingHandler::class);

    $executor = new ToolExecutor($runner);

    $tool = new TestTool;
    $context = new ToolContext;

    $result = $executor->execute($tool, ['input' => 'original'], $context);

    // The handler modifies input to 'modified by pipeline'
    expect($result->toText())->toBe('Result: modified by pipeline');
});

test('it runs tool.after_execute pipeline with correct data including result', function () {
    $container = new Container;
    $registry = new PipelineRegistry;
    $runner = new PipelineRunner($registry, $container);

    $registry->define('tool.after_execute', 'After execute pipeline');
    ToolAfterExecuteCapturingHandler::reset();
    $registry->register('tool.after_execute', ToolAfterExecuteCapturingHandler::class);

    $executor = new ToolExecutor($runner);

    $tool = new TestTool;
    $context = new ToolContext(['tenant_id' => 456]);

    $executor->execute($tool, ['input' => 'hello world'], $context);

    expect(ToolAfterExecuteCapturingHandler::$called)->toBeTrue();
    expect(ToolAfterExecuteCapturingHandler::$data)->not->toBeNull();
    expect(ToolAfterExecuteCapturingHandler::$data['tool'])->toBe($tool);
    expect(ToolAfterExecuteCapturingHandler::$data['params'])->toBe(['input' => 'hello world']);
    expect(ToolAfterExecuteCapturingHandler::$data['context'])->toBe($context);
    expect(ToolAfterExecuteCapturingHandler::$data['result'])->toBeInstanceOf(ToolResult::class);
    expect(ToolAfterExecuteCapturingHandler::$data['result']->toText())->toBe('Result: hello world');
});

test('tool.after_execute pipeline can check result success status', function () {
    $container = new Container;
    $registry = new PipelineRegistry;
    $runner = new PipelineRunner($registry, $container);

    $registry->define('tool.after_execute', 'After execute pipeline');
    ToolAfterExecuteStatusCheckHandler::reset();
    $registry->register('tool.after_execute', ToolAfterExecuteStatusCheckHandler::class);

    $executor = new ToolExecutor($runner);

    $tool = new TestTool;
    $context = new ToolContext;

    $executor->execute($tool, ['input' => 'test'], $context);

    expect(ToolAfterExecuteStatusCheckHandler::$succeeded)->toBeTrue();
    expect(ToolAfterExecuteStatusCheckHandler::$failed)->toBeFalse();
});

test('tool.after_execute pipeline can check result failure status', function () {
    $container = new Container;
    $registry = new PipelineRegistry;
    $runner = new PipelineRunner($registry, $container);

    $registry->define('tool.after_execute', 'After execute pipeline');
    ToolAfterExecuteStatusCheckHandler::reset();
    $registry->register('tool.after_execute', ToolAfterExecuteStatusCheckHandler::class);

    $executor = new ToolExecutor($runner);

    $tool = new class implements ToolContract
    {
        public function name(): string
        {
            return 'failing_result_tool';
        }

        public function description(): string
        {
            return 'Returns error result';
        }

        public function parameters(): array
        {
            return [];
        }

        public function handle(array $params, ToolContext $context): ToolResult
        {
            return ToolResult::error('Something went wrong');
        }
    };

    $context = new ToolContext;
    $executor->execute($tool, [], $context);

    expect(ToolAfterExecuteStatusCheckHandler::$succeeded)->toBeFalse();
    expect(ToolAfterExecuteStatusCheckHandler::$failed)->toBeTrue();
});

test('tool.after_execute pipeline can modify the result', function () {
    $container = new Container;
    $registry = new PipelineRegistry;
    $runner = new PipelineRunner($registry, $container);

    $registry->define('tool.after_execute', 'After execute pipeline');
    ToolAfterExecuteModifyingHandler::reset();
    $registry->register('tool.after_execute', ToolAfterExecuteModifyingHandler::class);

    $executor = new ToolExecutor($runner);

    $tool = new TestTool;
    $context = new ToolContext;

    $result = $executor->execute($tool, ['input' => 'original'], $context);

    // The handler replaces the result
    expect($result->toText())->toBe('Modified by after_execute pipeline');
});

test('tool.before_execute and tool.after_execute pipelines run in correct order', function () {
    $container = new Container;
    $registry = new PipelineRegistry;
    $runner = new PipelineRunner($registry, $container);

    $registry->define('tool.before_execute', 'Before execute pipeline');
    $registry->define('tool.after_execute', 'After execute pipeline');

    ToolExecutionOrderTracker::reset();
    $registry->register('tool.before_execute', ToolBeforeExecuteOrderHandler::class);
    $registry->register('tool.after_execute', ToolAfterExecuteOrderHandler::class);

    $executor = new ToolExecutor($runner);

    $tool = new TestTool;
    $context = new ToolContext;

    $executor->execute($tool, ['input' => 'test'], $context);

    expect(ToolExecutionOrderTracker::$order)->toBe(['before_execute', 'after_execute']);
});

// Pipeline Handler Classes for Tests

class ToolBeforeExecuteCapturingHandler implements \Atlasphp\Atlas\Contracts\PipelineContract
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

class ToolBeforeExecuteModifyingHandler implements \Atlasphp\Atlas\Contracts\PipelineContract
{
    public static bool $called = false;

    public static function reset(): void
    {
        self::$called = false;
    }

    public function handle(mixed $data, \Closure $next): mixed
    {
        self::$called = true;
        $data['params']['input'] = 'modified by pipeline';

        return $next($data);
    }
}

class ToolAfterExecuteCapturingHandler implements \Atlasphp\Atlas\Contracts\PipelineContract
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

class ToolAfterExecuteStatusCheckHandler implements \Atlasphp\Atlas\Contracts\PipelineContract
{
    public static bool $succeeded = false;

    public static bool $failed = false;

    public static function reset(): void
    {
        self::$succeeded = false;
        self::$failed = false;
    }

    public function handle(mixed $data, \Closure $next): mixed
    {
        self::$succeeded = $data['result']->succeeded();
        self::$failed = $data['result']->failed();

        return $next($data);
    }
}

class ToolAfterExecuteModifyingHandler implements \Atlasphp\Atlas\Contracts\PipelineContract
{
    public static bool $called = false;

    public static function reset(): void
    {
        self::$called = false;
    }

    public function handle(mixed $data, \Closure $next): mixed
    {
        self::$called = true;
        $data['result'] = ToolResult::text('Modified by after_execute pipeline');

        return $next($data);
    }
}

class ToolExecutionOrderTracker
{
    public static array $order = [];

    public static function reset(): void
    {
        self::$order = [];
    }
}

class ToolBeforeExecuteOrderHandler implements \Atlasphp\Atlas\Contracts\PipelineContract
{
    public function handle(mixed $data, \Closure $next): mixed
    {
        ToolExecutionOrderTracker::$order[] = 'before_execute';

        return $next($data);
    }
}

class ToolAfterExecuteOrderHandler implements \Atlasphp\Atlas\Contracts\PipelineContract
{
    public function handle(mixed $data, \Closure $next): mixed
    {
        ToolExecutionOrderTracker::$order[] = 'after_execute';

        return $next($data);
    }
}

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
