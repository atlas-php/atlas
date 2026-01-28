<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Exceptions\AgentException;
use Atlasphp\Atlas\Agents\Support\AgentContext;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Contracts\PipelineContract;
use Atlasphp\Atlas\Pipelines\PipelineRegistry;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Atlasphp\Atlas\Tests\Fixtures\TestAgentNoSystemPrompt;
use Atlasphp\Atlas\Tests\Fixtures\TestTool;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\Text\Response as PrismResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

beforeEach(function () {
    $this->registry = app(PipelineRegistry::class);
    $this->executor = app(AgentExecutorContract::class);
});

// =============================================================================
// agent.on_error Pipeline Tests
// =============================================================================

test('agent.on_error pipeline can provide recovery response', function () {
    Prism::fake([
        TextResponseFake::make()->withText('Normal response'),
    ]);

    // First register a handler that throws an error during after_execute
    // This simulates an error that triggers the on_error pipeline
    $this->registry->register('agent.after_execute', new class implements PipelineContract
    {
        public function handle(mixed $data, Closure $next): mixed
        {
            throw new \RuntimeException('Simulated API error');
        }
    }, priority: 100);

    // Register recovery handler
    $this->registry->register('agent.on_error', new class implements PipelineContract
    {
        public function handle(mixed $data, Closure $next): mixed
        {
            // Provide a recovery response
            $data['recovery'] = new PrismResponse(
                steps: collect([]),
                text: 'I apologize, but I encountered an issue. Please try again.',
                finishReason: FinishReason::Stop,
                toolCalls: [],
                toolResults: [],
                usage: new Usage(0, 0),
                meta: new Meta('fallback', 'fallback'),
                messages: collect([]),
                additionalContent: [],
            );

            return $next($data);
        }
    });

    $agent = new TestAgentNoSystemPrompt;
    $context = new AgentContext;

    $response = $this->executor->execute($agent, 'Hello', $context);

    expect($response)->toBeInstanceOf(AgentResponse::class);
    expect($response->text)->toBe('I apologize, but I encountered an issue. Please try again.');
});

test('agent.on_error pipeline receives exception details', function () {
    $capturedData = null;

    Prism::fake([
        TextResponseFake::make()->withText('Normal response'),
    ]);

    // Trigger an error
    $this->registry->register('agent.after_execute', new class implements PipelineContract
    {
        public function handle(mixed $data, Closure $next): mixed
        {
            throw new \RuntimeException('Test error message');
        }
    }, priority: 100);

    // Register handler to capture data
    $this->registry->register('agent.on_error', new class($capturedData) implements PipelineContract
    {
        public function __construct(private mixed &$captured) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->captured = $data;

            // Provide recovery to prevent exception being thrown
            $data['recovery'] = new PrismResponse(
                steps: collect([]),
                text: 'Recovery response',
                finishReason: FinishReason::Stop,
                toolCalls: [],
                toolResults: [],
                usage: new Usage(0, 0),
                meta: new Meta('fallback', 'fallback'),
                messages: collect([]),
                additionalContent: [],
            );

            return $next($data);
        }
    });

    $agent = new TestAgentNoSystemPrompt;
    $context = new AgentContext(metadata: ['user_id' => 123]);

    $this->executor->execute($agent, 'Hello', $context);

    expect($capturedData)->not->toBeNull();
    expect($capturedData['agent'])->toBe($agent);
    expect($capturedData['input'])->toBe('Hello');
    expect($capturedData['context']->getMeta('user_id'))->toBe(123);
    expect($capturedData['exception'])->toBeInstanceOf(Throwable::class);
    expect($capturedData['exception']->getMessage())->toBe('Test error message');
});

test('agent.on_error pipeline ignores invalid recovery types', function () {
    Prism::fake([
        TextResponseFake::make()->withText('Normal response'),
    ]);

    // Trigger an error
    $this->registry->register('agent.after_execute', new class implements PipelineContract
    {
        public function handle(mixed $data, Closure $next): mixed
        {
            throw new \RuntimeException('API Error');
        }
    }, priority: 100);

    // Register handler that returns invalid recovery type
    $this->registry->register('agent.on_error', new class implements PipelineContract
    {
        public function handle(mixed $data, Closure $next): mixed
        {
            // Set invalid recovery type - should be ignored
            $data['recovery'] = 'invalid string response';

            return $next($data);
        }
    });

    $agent = new TestAgentNoSystemPrompt;
    $context = new AgentContext;

    expect(fn () => $this->executor->execute($agent, 'Hello', $context))
        ->toThrow(AgentException::class);
});

// =============================================================================
// agent.tools.merged Pipeline Tests
// =============================================================================

test('agent.tools.merged pipeline can filter tools', function () {
    $mergedToolNames = [];

    Prism::fake([
        TextResponseFake::make()->withText('Response with filtered tools'),
    ]);

    // Register handler to filter tools and capture data
    $this->registry->register('agent.tools.merged', new class($mergedToolNames) implements PipelineContract
    {
        public function __construct(private array &$captured) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            // Capture tool names before filtering
            $this->captured = array_map(fn ($tool) => $tool->name(), $data['tools']);

            // Filter out all tools (return empty array)
            $data['tools'] = [];

            return $next($data);
        }
    });

    $agent = new TestAgent;
    $context = new AgentContext;

    $response = $this->executor->execute($agent, 'Hello', $context);

    expect($response->text)->toBe('Response with filtered tools');
    expect($mergedToolNames)->toContain('test_tool');
});

test('agent.tools.merged pipeline receives all tool sources', function () {
    $capturedData = null;

    Prism::fake([
        TextResponseFake::make()->withText('Response'),
    ]);

    // Register handler to capture all data
    $this->registry->register('agent.tools.merged', new class($capturedData) implements PipelineContract
    {
        public function __construct(private mixed &$captured) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->captured = $data;

            return $next($data);
        }
    });

    $agent = new TestAgent;
    $context = new AgentContext;

    $this->executor->execute($agent, 'Hello', $context);

    expect($capturedData)->not->toBeNull();
    expect($capturedData)->toHaveKey('agent');
    expect($capturedData)->toHaveKey('context');
    expect($capturedData)->toHaveKey('tool_context');
    expect($capturedData)->toHaveKey('agent_tools');
    expect($capturedData)->toHaveKey('agent_mcp_tools');
    expect($capturedData)->toHaveKey('tools');
    expect($capturedData['agent'])->toBe($agent);
});

// =============================================================================
// agent.context.validate Pipeline Tests
// =============================================================================

test('agent.context.validate pipeline can modify context', function () {
    $modifiedContext = null;

    Prism::fake([
        TextResponseFake::make()->withText('Response'),
    ]);

    // Register handler to modify context
    $this->registry->register('agent.context.validate', new class implements PipelineContract
    {
        public function handle(mixed $data, Closure $next): mixed
        {
            // Add validated_at to metadata
            $data['context'] = $data['context']->mergeMetadata([
                'validated_at' => '2024-01-01T00:00:00Z',
            ]);

            return $next($data);
        }
    });

    // Register after_execute to capture final context
    $this->registry->register('agent.after_execute', new class($modifiedContext) implements PipelineContract
    {
        public function __construct(private mixed &$captured) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->captured = $data['context'];

            return $next($data);
        }
    });

    $agent = new TestAgentNoSystemPrompt;
    $context = new AgentContext(metadata: ['user_id' => 1]);

    $this->executor->execute($agent, 'Hello', $context);

    expect($modifiedContext)->not->toBeNull();
    expect($modifiedContext->getMeta('validated_at'))->toBe('2024-01-01T00:00:00Z');
    expect($modifiedContext->getMeta('user_id'))->toBe(1);
});

test('agent.context.validate pipeline can throw validation errors', function () {
    // Register handler that throws on missing user_id
    $this->registry->register('agent.context.validate', new class implements PipelineContract
    {
        public function handle(mixed $data, Closure $next): mixed
        {
            if ($data['context']->getMeta('user_id') === null) {
                throw new \InvalidArgumentException('user_id is required in context metadata');
            }

            return $next($data);
        }
    });

    $agent = new TestAgentNoSystemPrompt;
    $context = new AgentContext; // No user_id

    // Exception is wrapped in AgentException
    expect(fn () => $this->executor->execute($agent, 'Hello', $context))
        ->toThrow(AgentException::class);
});

// =============================================================================
// tool.before_resolve Pipeline Tests
// =============================================================================

test('tool.before_resolve pipeline can filter tool classes', function () {
    $capturedToolsBefore = null;

    Prism::fake([
        TextResponseFake::make()->withText('Response'),
    ]);

    // Register handler to capture and filter tools
    $this->registry->register('tool.before_resolve', new class($capturedToolsBefore) implements PipelineContract
    {
        public function __construct(private mixed &$captured) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->captured = $data['tools'];

            // Filter out TestTool
            $data['tools'] = array_filter(
                $data['tools'],
                fn ($tool) => $tool !== TestTool::class
            );

            return $next($data);
        }
    });

    $agent = new TestAgent;
    $context = new AgentContext;

    $this->executor->execute($agent, 'Hello', $context);

    expect($capturedToolsBefore)->toContain(TestTool::class);
});

// =============================================================================
// tool.after_resolve Pipeline Tests
// =============================================================================

test('tool.after_resolve pipeline receives built Prism tools', function () {
    $capturedPrismTools = null;

    Prism::fake([
        TextResponseFake::make()->withText('Response'),
    ]);

    // Register handler to capture built tools
    $this->registry->register('tool.after_resolve', new class($capturedPrismTools) implements PipelineContract
    {
        public function __construct(private mixed &$captured) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->captured = $data['prism_tools'];

            return $next($data);
        }
    });

    $agent = new TestAgent;
    $context = new AgentContext;

    $this->executor->execute($agent, 'Hello', $context);

    expect($capturedPrismTools)->toBeArray();
    expect($capturedPrismTools)->not->toBeEmpty();
    expect($capturedPrismTools[0])->toBeInstanceOf(\Prism\Prism\Tool::class);
    expect($capturedPrismTools[0]->name())->toBe('test_tool');
});

// =============================================================================
// Error Scenario Tests
// =============================================================================

test('pipeline handler throwing exception propagates as AgentException', function () {
    Prism::fake([
        TextResponseFake::make()->withText('Response'),
    ]);

    $this->registry->register('agent.before_execute', new class implements PipelineContract
    {
        public function handle(mixed $data, Closure $next): mixed
        {
            throw new \RuntimeException('Handler exploded');
        }
    });

    $agent = new TestAgentNoSystemPrompt;
    $context = new AgentContext;

    // Pipeline exceptions are wrapped in AgentException during execution
    expect(fn () => $this->executor->execute($agent, 'Hello', $context))
        ->toThrow(AgentException::class);
});

test('conditional handler condition throwing exception propagates as AgentException', function () {
    Prism::fake([
        TextResponseFake::make()->withText('Response'),
    ]);

    // Register conditional handler with condition that throws
    $this->registry->registerWhen(
        'agent.before_execute',
        new class implements PipelineContract
        {
            public function handle(mixed $data, Closure $next): mixed
            {
                return $next($data);
            }
        },
        fn (array $data) => throw new \RuntimeException('Condition exploded')
    );

    $agent = new TestAgentNoSystemPrompt;
    $context = new AgentContext;

    // Pipeline exceptions are wrapped in AgentException during execution
    expect(fn () => $this->executor->execute($agent, 'Hello', $context))
        ->toThrow(AgentException::class);
});

// =============================================================================
// Runtime Middleware Tests
// =============================================================================

test('runtime middleware executes alongside global handlers', function () {
    $executionOrder = [];

    Prism::fake([
        TextResponseFake::make()->withText('Response'),
    ]);

    // Register a global handler
    $this->registry->register('agent.before_execute', new class($executionOrder) implements PipelineContract
    {
        public function __construct(private array &$order) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->order[] = 'global';

            return $next($data);
        }
    }, priority: 50);

    // Create context with runtime middleware
    $runtimeHandler = new class($executionOrder) implements PipelineContract
    {
        public function __construct(private array &$order) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->order[] = 'runtime';

            return $next($data);
        }
    };

    $context = new AgentContext(middleware: [
        'agent.before_execute' => [
            ['handler' => $runtimeHandler, 'priority' => 0],
        ],
    ]);

    $agent = new TestAgentNoSystemPrompt;
    $this->executor->execute($agent, 'Hello', $context);

    // Global handler (priority 50) runs before runtime (priority 0)
    expect($executionOrder)->toBe(['global', 'runtime']);
});

test('runtime middleware runs in registration order', function () {
    $executionOrder = [];

    Prism::fake([
        TextResponseFake::make()->withText('Response'),
    ]);

    // Create runtime handlers
    $firstHandler = new class($executionOrder) implements PipelineContract
    {
        public function __construct(private array &$order) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->order[] = 'first';

            return $next($data);
        }
    };

    $secondHandler = new class($executionOrder) implements PipelineContract
    {
        public function __construct(private array &$order) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->order[] = 'second';

            return $next($data);
        }
    };

    $context = new AgentContext(middleware: [
        'agent.before_execute' => [
            ['handler' => $firstHandler, 'priority' => 0],
            ['handler' => $secondHandler, 'priority' => 0],
        ],
    ]);

    $agent = new TestAgentNoSystemPrompt;
    $this->executor->execute($agent, 'Hello', $context);

    // Same priority, so registration order preserved
    expect($executionOrder)->toBe(['first', 'second']);
});

test('runtime middleware only applies to current request', function () {
    $executionCount = 0;

    Prism::fake([
        TextResponseFake::make()->withText('First response'),
        TextResponseFake::make()->withText('Second response'),
    ]);

    $runtimeHandler = new class($executionCount) implements PipelineContract
    {
        public function __construct(private int &$count) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->count++;

            return $next($data);
        }
    };

    $agent = new TestAgentNoSystemPrompt;

    // First request with middleware
    $contextWithMiddleware = new AgentContext(middleware: [
        'agent.before_execute' => [
            ['handler' => $runtimeHandler, 'priority' => 0],
        ],
    ]);
    $this->executor->execute($agent, 'First', $contextWithMiddleware);

    expect($executionCount)->toBe(1);

    // Second request without middleware
    $contextWithoutMiddleware = new AgentContext;
    $this->executor->execute($agent, 'Second', $contextWithoutMiddleware);

    // Count should still be 1 - middleware didn't run for second request
    expect($executionCount)->toBe(1);
});

test('runtime middleware can modify context', function () {
    $capturedMetadata = null;

    Prism::fake([
        TextResponseFake::make()->withText('Response'),
    ]);

    // Runtime handler that modifies context
    $contextModifyingHandler = new class implements PipelineContract
    {
        public function handle(mixed $data, Closure $next): mixed
        {
            $data['context'] = $data['context']->mergeMetadata([
                'injected_by_middleware' => true,
            ]);

            return $next($data);
        }
    };

    // Handler to capture the modified context
    $this->registry->register('agent.after_execute', new class($capturedMetadata) implements PipelineContract
    {
        public function __construct(private mixed &$captured) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->captured = $data['context']->metadata;

            return $next($data);
        }
    });

    $context = new AgentContext(
        metadata: ['original' => 'value'],
        middleware: [
            'agent.before_execute' => [
                ['handler' => $contextModifyingHandler, 'priority' => 0],
            ],
        ],
    );

    $agent = new TestAgentNoSystemPrompt;
    $this->executor->execute($agent, 'Hello', $context);

    expect($capturedMetadata)->toHaveKey('original');
    expect($capturedMetadata)->toHaveKey('injected_by_middleware');
    expect($capturedMetadata['injected_by_middleware'])->toBeTrue();
});

test('runtime middleware can provide error recovery', function () {
    Prism::fake([
        TextResponseFake::make()->withText('Normal response'),
    ]);

    // First register a handler that throws
    $this->registry->register('agent.after_execute', new class implements PipelineContract
    {
        public function handle(mixed $data, Closure $next): mixed
        {
            throw new \RuntimeException('Simulated failure');
        }
    }, priority: 100);

    // Runtime recovery handler
    $recoveryHandler = new class implements PipelineContract
    {
        public function handle(mixed $data, Closure $next): mixed
        {
            $data['recovery'] = new PrismResponse(
                steps: collect([]),
                text: 'Recovered by runtime middleware',
                finishReason: FinishReason::Stop,
                toolCalls: [],
                toolResults: [],
                usage: new Usage(0, 0),
                meta: new Meta('recovery', 'recovery'),
                messages: collect([]),
                additionalContent: [],
            );

            return $next($data);
        }
    };

    $context = new AgentContext(middleware: [
        'agent.on_error' => [
            ['handler' => $recoveryHandler, 'priority' => 0],
        ],
    ]);

    $agent = new TestAgentNoSystemPrompt;
    $response = $this->executor->execute($agent, 'Hello', $context);

    expect($response)->toBeInstanceOf(AgentResponse::class);
    expect($response->text)->toBe('Recovered by runtime middleware');
});

test('runtime middleware works with agent.tools.merged pipeline', function () {
    $capturedToolCount = null;

    Prism::fake([
        TextResponseFake::make()->withText('Response'),
    ]);

    // Runtime handler to capture tool info
    $toolAuditHandler = new class($capturedToolCount) implements PipelineContract
    {
        public function __construct(private mixed &$captured) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->captured = count($data['tools']);

            return $next($data);
        }
    };

    $context = new AgentContext(middleware: [
        'agent.tools.merged' => [
            ['handler' => $toolAuditHandler, 'priority' => 0],
        ],
    ]);

    $agent = new TestAgent;  // TestAgent has tools
    $this->executor->execute($agent, 'Hello', $context);

    expect($capturedToolCount)->not->toBeNull();
    expect($capturedToolCount)->toBeGreaterThanOrEqual(1);
});

test('runtime middleware works with agent.context.validate pipeline', function () {
    $validated = false;

    Prism::fake([
        TextResponseFake::make()->withText('Response'),
    ]);

    // Runtime validation handler
    $validationHandler = new class($validated) implements PipelineContract
    {
        public function __construct(private bool &$flag) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->flag = true;

            return $next($data);
        }
    };

    $context = new AgentContext(middleware: [
        'agent.context.validate' => [
            ['handler' => $validationHandler, 'priority' => 0],
        ],
    ]);

    $agent = new TestAgentNoSystemPrompt;
    $this->executor->execute($agent, 'Hello', $context);

    expect($validated)->toBeTrue();
});

test('runtime middleware priority ordering is respected', function () {
    $executionOrder = [];

    Prism::fake([
        TextResponseFake::make()->withText('Response'),
    ]);

    // Global handler with priority 25
    $this->registry->register('agent.before_execute', new class($executionOrder) implements PipelineContract
    {
        public function __construct(private array &$order) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->order[] = 'global-25';

            return $next($data);
        }
    }, priority: 25);

    // Runtime handlers - both with priority 0, but registered in order
    $handler1 = new class($executionOrder) implements PipelineContract
    {
        public function __construct(private array &$order) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->order[] = 'runtime-1';

            return $next($data);
        }
    };

    $handler2 = new class($executionOrder) implements PipelineContract
    {
        public function __construct(private array &$order) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->order[] = 'runtime-2';

            return $next($data);
        }
    };

    $context = new AgentContext(middleware: [
        'agent.before_execute' => [
            ['handler' => $handler1, 'priority' => 0],
            ['handler' => $handler2, 'priority' => 0],
        ],
    ]);

    $agent = new TestAgentNoSystemPrompt;
    $this->executor->execute($agent, 'Hello', $context);

    // Global (25) runs before runtime handlers (0), runtime handlers run in order
    expect($executionOrder)->toBe(['global-25', 'runtime-1', 'runtime-2']);
});
