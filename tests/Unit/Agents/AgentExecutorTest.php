<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Services\AgentExecutor;
use Atlasphp\Atlas\Agents\Services\SystemPromptBuilder;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;
use Atlasphp\Atlas\Foundation\Services\PipelineRunner;
use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;
use Atlasphp\Atlas\Providers\Services\UsageExtractorRegistry;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Atlasphp\Atlas\Tools\Services\ToolBuilder;
use Atlasphp\Atlas\Tools\Services\ToolExecutor;
use Atlasphp\Atlas\Tools\Services\ToolRegistry;
use Illuminate\Container\Container;

beforeEach(function () {
    $this->container = new Container;
    $registry = new PipelineRegistry;
    $this->runner = new PipelineRunner($registry, $this->container);

    // Create mock for PrismBuilder
    $this->prismBuilder = Mockery::mock(PrismBuilderContract::class);

    // Create real service instances
    $this->systemPromptBuilder = new SystemPromptBuilder($this->runner);
    $toolRegistry = new ToolRegistry($this->container);
    $toolExecutor = new ToolExecutor($this->runner);
    $this->toolBuilder = new ToolBuilder($toolRegistry, $toolExecutor, $this->container);
    $this->usageExtractor = new UsageExtractorRegistry;
});

afterEach(function () {
    Mockery::close();
});

test('it creates executor with dependencies', function () {
    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
    );

    expect($executor)->toBeInstanceOf(AgentExecutor::class);
});

test('it creates default context when none provided', function () {
    // Create a mock response object
    $mockResponse = new class
    {
        public ?string $text = 'Hello';

        public array $toolCalls = [];

        public object $finishReason;

        public function __construct()
        {
            $this->finishReason = new class
            {
                public string $value = 'stop';
            };
        }
    };

    // Mock the pending request
    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asText')->andReturn($mockResponse);

    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->once()
        ->andReturn($mockPendingRequest);

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
    );

    $agent = new TestAgent;
    $response = $executor->execute($agent, 'Hello');

    expect($response)->toBeInstanceOf(AgentResponse::class);
});

test('it uses provided context', function () {
    $mockResponse = new class
    {
        public ?string $text = 'Hello';

        public array $toolCalls = [];

        public object $finishReason;

        public function __construct()
        {
            $this->finishReason = new class
            {
                public string $value = 'stop';
            };
        }
    };

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asText')->andReturn($mockResponse);

    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->once()
        ->andReturn($mockPendingRequest);

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
    );

    $context = new ExecutionContext(
        variables: ['user_name' => 'John'],
    );

    $agent = new TestAgent;
    $response = $executor->execute($agent, 'Hello', $context);

    expect($response)->toBeInstanceOf(AgentResponse::class);
});

test('it uses forMessages when context has messages', function () {
    $mockResponse = new class
    {
        public ?string $text = 'Hello';

        public array $toolCalls = [];

        public object $finishReason;

        public function __construct()
        {
            $this->finishReason = new class
            {
                public string $value = 'stop';
            };
        }
    };

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asText')->andReturn($mockResponse);

    $this->prismBuilder
        ->shouldReceive('forMessages')
        ->once()
        ->andReturn($mockPendingRequest);

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
    );

    $context = new ExecutionContext(
        messages: [['role' => 'user', 'content' => 'Previous message']],
    );

    $agent = new TestAgent;
    $response = $executor->execute($agent, 'Hello', $context);

    expect($response)->toBeInstanceOf(AgentResponse::class);
});

test('it extracts text from response', function () {
    $mockResponse = new class
    {
        public ?string $text = 'Response text';

        public array $toolCalls = [];

        public object $finishReason;

        public function __construct()
        {
            $this->finishReason = new class
            {
                public string $value = 'stop';
            };
        }
    };

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asText')->andReturn($mockResponse);

    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->once()
        ->andReturn($mockPendingRequest);

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
    );

    $agent = new TestAgent;
    $response = $executor->execute($agent, 'Hello');

    expect($response->text)->toBe('Response text');
});

test('it applies agent settings to request', function () {
    $mockResponse = new class
    {
        public ?string $text = 'Hello';

        public array $toolCalls = [];

        public object $finishReason;

        public function __construct()
        {
            $this->finishReason = new class
            {
                public string $value = 'stop';
            };
        }
    };

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->once()->with(0.7)->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->once()->with(1000)->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->once()->with(5)->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asText')->andReturn($mockResponse);

    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->once()
        ->andReturn($mockPendingRequest);

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
    );

    $agent = new TestAgent;
    $response = $executor->execute($agent, 'Hello');

    expect($response)->toBeInstanceOf(AgentResponse::class);
});

test('it builds structured response when schema provided', function () {
    $mockResponse = new class
    {
        public mixed $structured = ['name' => 'John'];

        public object $finishReason;

        public function __construct()
        {
            $this->finishReason = new class
            {
                public string $value = 'stop';
            };
        }
    };

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asStructured')->andReturn($mockResponse);

    $mockSchema = Mockery::mock(\Prism\Prism\Contracts\Schema::class);

    $this->prismBuilder
        ->shouldReceive('forStructured')
        ->once()
        ->andReturn($mockPendingRequest);

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
    );

    $agent = new TestAgent;
    $response = $executor->execute($agent, 'Hello', null, $mockSchema);

    expect($response->structured)->toBe(['name' => 'John']);
});

test('it converts provider tools strings to ProviderTool objects', function () {
    $mockResponse = new class
    {
        public ?string $text = 'Hello';

        public array $toolCalls = [];

        public array $steps = [];

        public object $finishReason;

        public function __construct()
        {
            $this->finishReason = new class
            {
                public string $value = 'stop';
            };
        }
    };

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')
        ->once()
        ->with(Mockery::on(function ($tools) {
            // Verify tools is an array of ProviderTool objects
            if (! is_array($tools) || count($tools) !== 2) {
                return false;
            }

            // First tool should be web_search_preview string converted
            $first = $tools[0];
            if (! $first instanceof \Prism\Prism\ValueObjects\ProviderTool) {
                return false;
            }
            if ($first->type !== 'web_search_preview') {
                return false;
            }

            // Second tool should be code_execution with options
            $second = $tools[1];
            if (! $second instanceof \Prism\Prism\ValueObjects\ProviderTool) {
                return false;
            }
            if ($second->type !== 'code_execution') {
                return false;
            }

            return true;
        }))
        ->andReturnSelf();
    $mockPendingRequest->shouldReceive('asText')->andReturn($mockResponse);

    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->once()
        ->andReturn($mockPendingRequest);

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
    );

    $agent = new \Atlasphp\Atlas\Tests\Fixtures\TestAgentWithProviderTools;
    $response = $executor->execute($agent, 'Hello');

    expect($response)->toBeInstanceOf(AgentResponse::class);
});

test('it extracts tool calls from response steps', function () {
    // Create mock tool call
    $mockToolCall = new class
    {
        public string $name = 'calculator';

        public function arguments(): array
        {
            return ['operation' => 'add', 'a' => 5, 'b' => 3];
        }
    };

    // Create mock tool result
    $mockToolResult = new class
    {
        public string $result = '8';
    };

    // Create mock step with tool calls and results
    $mockStep = new class($mockToolCall, $mockToolResult)
    {
        public array $toolCalls;

        public array $toolResults;

        public function __construct($toolCall, $toolResult)
        {
            $this->toolCalls = [$toolCall];
            $this->toolResults = [$toolResult];
        }
    };

    $mockResponse = new class($mockStep)
    {
        public ?string $text = 'The result is 8.';

        public array $toolCalls = [];

        public array $steps;

        public object $finishReason;

        public function __construct($step)
        {
            $this->steps = [$step];
            $this->finishReason = new class
            {
                public string $value = 'stop';
            };
        }
    };

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asText')->andReturn($mockResponse);

    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->once()
        ->andReturn($mockPendingRequest);

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
    );

    $agent = new TestAgent;
    $response = $executor->execute($agent, 'What is 5 + 3?');

    expect($response->toolCalls)->toHaveCount(1);
    expect($response->toolCalls[0]['name'])->toBe('calculator');
    expect($response->toolCalls[0]['arguments'])->toBe(['operation' => 'add', 'a' => 5, 'b' => 3]);
    expect($response->toolCalls[0]['result'])->toBe('8');
});

test('it extracts tool calls without results from steps', function () {
    // Create mock tool call without result
    $mockToolCall = new class
    {
        public string $name = 'weather';

        public function arguments(): array
        {
            return ['city' => 'New York'];
        }
    };

    // Step with tool calls but no results array
    $mockStep = new class($mockToolCall)
    {
        public array $toolCalls;

        public array $toolResults = [];

        public function __construct($toolCall)
        {
            $this->toolCalls = [$toolCall];
        }
    };

    $mockResponse = new class($mockStep)
    {
        public ?string $text = 'Checking weather...';

        public array $toolCalls = [];

        public array $steps;

        public object $finishReason;

        public function __construct($step)
        {
            $this->steps = [$step];
            $this->finishReason = new class
            {
                public string $value = 'tool_calls';
            };
        }
    };

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asText')->andReturn($mockResponse);

    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->once()
        ->andReturn($mockPendingRequest);

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
    );

    $agent = new TestAgent;
    $response = $executor->execute($agent, 'What is the weather in New York?');

    expect($response->toolCalls)->toHaveCount(1);
    expect($response->toolCalls[0]['name'])->toBe('weather');
    expect($response->toolCalls[0]['arguments'])->toBe(['city' => 'New York']);
    expect($response->toolCalls[0]['result'])->toBeNull();
});

test('it falls back to direct toolCalls when no steps present', function () {
    // Create mock tool call on response directly
    $mockToolCall = new class
    {
        public string $name = 'datetime';

        public function arguments(): array
        {
            return ['format' => 'Y-m-d'];
        }
    };

    $mockResponse = new class($mockToolCall)
    {
        public ?string $text = null;

        public array $toolCalls;

        public object $finishReason;

        public function __construct($toolCall)
        {
            $this->toolCalls = [$toolCall];
            $this->finishReason = new class
            {
                public string $value = 'tool_calls';
            };
        }
    };

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asText')->andReturn($mockResponse);

    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->once()
        ->andReturn($mockPendingRequest);

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
    );

    $agent = new TestAgent;
    $response = $executor->execute($agent, 'What is the current date?');

    expect($response->toolCalls)->toHaveCount(1);
    expect($response->toolCalls[0]['name'])->toBe('datetime');
    expect($response->toolCalls[0]['arguments'])->toBe(['format' => 'Y-m-d']);
    expect($response->toolCalls[0]['result'])->toBeNull();
});

test('it runs agent.on_error pipeline when execution fails', function () {
    $container = new Container;
    $registry = new PipelineRegistry;
    $runner = new PipelineRunner($registry, $container);

    // Define and activate the error pipeline
    $registry->define('agent.on_error', 'Error pipeline');

    // Reset static state
    AgentErrorCapturingHandler::reset();
    $registry->register('agent.on_error', AgentErrorCapturingHandler::class);

    $prismBuilder = Mockery::mock(PrismBuilderContract::class);
    $prismBuilder->shouldReceive('forPrompt')
        ->andThrow(new \RuntimeException('API Error'));

    $systemPromptBuilder = new SystemPromptBuilder($runner);
    $toolRegistry = new ToolRegistry($container);
    $toolExecutor = new ToolExecutor($runner);
    $toolBuilder = new ToolBuilder($toolRegistry, $toolExecutor, $container);
    $usageExtractor = new UsageExtractorRegistry;

    $executor = new AgentExecutor(
        $prismBuilder,
        $toolBuilder,
        $systemPromptBuilder,
        $runner,
        $usageExtractor,
    );

    $agent = new TestAgent;

    try {
        $executor->execute($agent, 'Hello');
    } catch (\Atlasphp\Atlas\Agents\Exceptions\AgentException $e) {
        // Expected
    }

    expect(AgentErrorCapturingHandler::$called)->toBeTrue();
    expect(AgentErrorCapturingHandler::$data)->not->toBeNull();
    expect(AgentErrorCapturingHandler::$data['agent'])->toBe($agent);
    expect(AgentErrorCapturingHandler::$data['input'])->toBe('Hello');
    expect(AgentErrorCapturingHandler::$data['exception'])->toBeInstanceOf(\RuntimeException::class);
});

test('it includes system_prompt in after_execute pipeline data', function () {
    $container = new Container;
    $registry = new PipelineRegistry;
    $runner = new PipelineRunner($registry, $container);

    // Define and activate the after pipeline
    $registry->define('agent.after_execute', 'After pipeline');

    // Reset static state
    AgentAfterExecuteCapturingHandler::reset();
    $registry->register('agent.after_execute', AgentAfterExecuteCapturingHandler::class);

    $mockResponse = new class
    {
        public ?string $text = 'Hello';

        public array $toolCalls = [];

        public object $finishReason;

        public function __construct()
        {
            $this->finishReason = new class
            {
                public string $value = 'stop';
            };
        }
    };

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asText')->andReturn($mockResponse);

    $prismBuilder = Mockery::mock(PrismBuilderContract::class);
    $prismBuilder->shouldReceive('forPrompt')->andReturn($mockPendingRequest);

    $systemPromptBuilder = new SystemPromptBuilder($runner);
    $toolRegistry = new ToolRegistry($container);
    $toolExecutor = new ToolExecutor($runner);
    $toolBuilder = new ToolBuilder($toolRegistry, $toolExecutor, $container);
    $usageExtractor = new UsageExtractorRegistry;

    $executor = new AgentExecutor(
        $prismBuilder,
        $toolBuilder,
        $systemPromptBuilder,
        $runner,
        $usageExtractor,
    );

    $agent = new TestAgent;
    $executor->execute($agent, 'Hello');

    expect(AgentAfterExecuteCapturingHandler::$data)->not->toBeNull();
    expect(array_key_exists('system_prompt', AgentAfterExecuteCapturingHandler::$data))->toBeTrue();
    expect(AgentAfterExecuteCapturingHandler::$data['system_prompt'])->toBeString();
});

test('it extracts tool call id from response steps', function () {
    // Create mock tool call with id
    $mockToolCall = new class
    {
        public string $id = 'call_123abc';

        public string $name = 'calculator';

        public function arguments(): array
        {
            return ['operation' => 'add'];
        }
    };

    $mockToolResult = new class
    {
        public string $result = '8';
    };

    $mockStep = new class($mockToolCall, $mockToolResult)
    {
        public array $toolCalls;

        public array $toolResults;

        public function __construct($toolCall, $toolResult)
        {
            $this->toolCalls = [$toolCall];
            $this->toolResults = [$toolResult];
        }
    };

    $mockResponse = new class($mockStep)
    {
        public ?string $text = 'Result';

        public array $toolCalls = [];

        public array $steps;

        public object $finishReason;

        public function __construct($step)
        {
            $this->steps = [$step];
            $this->finishReason = new class
            {
                public string $value = 'stop';
            };
        }
    };

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asText')->andReturn($mockResponse);

    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->once()
        ->andReturn($mockPendingRequest);

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
    );

    $agent = new TestAgent;
    $response = $executor->execute($agent, 'Calculate');

    expect($response->toolCalls)->toHaveCount(1);
    expect($response->toolCalls[0]['id'])->toBe('call_123abc');
    expect($response->toolCalls[0]['name'])->toBe('calculator');
});

test('it extracts null id when tool call has no id', function () {
    // Create mock tool call without id
    $mockToolCall = new class
    {
        public string $name = 'calculator';

        public function arguments(): array
        {
            return ['operation' => 'add'];
        }
    };

    $mockStep = new class($mockToolCall)
    {
        public array $toolCalls;

        public array $toolResults = [];

        public function __construct($toolCall)
        {
            $this->toolCalls = [$toolCall];
        }
    };

    $mockResponse = new class($mockStep)
    {
        public ?string $text = 'Result';

        public array $toolCalls = [];

        public array $steps;

        public object $finishReason;

        public function __construct($step)
        {
            $this->steps = [$step];
            $this->finishReason = new class
            {
                public string $value = 'stop';
            };
        }
    };

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asText')->andReturn($mockResponse);

    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->once()
        ->andReturn($mockPendingRequest);

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
    );

    $agent = new TestAgent;
    $response = $executor->execute($agent, 'Calculate');

    expect($response->toolCalls)->toHaveCount(1);
    expect($response->toolCalls[0]['id'])->toBeNull();
});

test('it preserves original exception in AgentException', function () {
    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('forPrompt')
        ->andThrow(new \RuntimeException('Original error'));

    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->andThrow(new \RuntimeException('Original error'));

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
    );

    $agent = new TestAgent;

    try {
        $executor->execute($agent, 'Hello');
        $this->fail('Expected AgentException to be thrown');
    } catch (\Atlasphp\Atlas\Agents\Exceptions\AgentException $e) {
        expect($e->getPrevious())->toBeInstanceOf(\RuntimeException::class);
        expect($e->getPrevious()->getMessage())->toBe('Original error');
    }
});

test('it rethrows AgentException without wrapping', function () {
    $container = new Container;
    $registry = new PipelineRegistry;
    $runner = new PipelineRunner($registry, $container);

    // Define and activate the error pipeline
    $registry->define('agent.on_error', 'Error pipeline');

    // Reset static state
    AgentErrorCapturingHandler::reset();
    $registry->register('agent.on_error', AgentErrorCapturingHandler::class);

    $originalException = \Atlasphp\Atlas\Agents\Exceptions\AgentException::executionFailed(
        'test-agent',
        'Custom agent error'
    );

    $prismBuilder = Mockery::mock(PrismBuilderContract::class);
    $prismBuilder->shouldReceive('forPrompt')
        ->andThrow($originalException);

    $systemPromptBuilder = new SystemPromptBuilder($runner);
    $toolRegistry = new ToolRegistry($container);
    $toolExecutor = new ToolExecutor($runner);
    $toolBuilder = new ToolBuilder($toolRegistry, $toolExecutor, $container);
    $usageExtractor = new UsageExtractorRegistry;

    $executor = new AgentExecutor(
        $prismBuilder,
        $toolBuilder,
        $systemPromptBuilder,
        $runner,
        $usageExtractor,
    );

    $agent = new TestAgent;

    try {
        $executor->execute($agent, 'Hello');
        $this->fail('Expected AgentException to be thrown');
    } catch (\Atlasphp\Atlas\Agents\Exceptions\AgentException $e) {
        // Should be the exact same exception, not wrapped
        expect($e)->toBe($originalException);
        expect($e->getMessage())->toBe("Agent 'test-agent' execution failed: Custom agent error");
        // Should have no previous exception (wasn't wrapped)
        expect($e->getPrevious())->toBeNull();
    }

    // Error pipeline should still have been called
    expect(AgentErrorCapturingHandler::$called)->toBeTrue();
    expect(AgentErrorCapturingHandler::$data['exception'])->toBe($originalException);
});

test('it combines messages with input for structured requests', function () {
    $mockResponse = new class
    {
        public mixed $structured = ['name' => 'John'];

        public object $finishReason;

        public function __construct()
        {
            $this->finishReason = new class
            {
                public string $value = 'stop';
            };
        }
    };

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asStructured')->andReturn($mockResponse);

    $mockSchema = Mockery::mock(\Prism\Prism\Contracts\Schema::class);

    // Capture the combined input
    $capturedInput = null;
    $this->prismBuilder
        ->shouldReceive('forStructured')
        ->once()
        ->withArgs(function ($provider, $model, $schema, $input, $systemPrompt) use (&$capturedInput) {
            $capturedInput = $input;

            return true;
        })
        ->andReturn($mockPendingRequest);

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
    );

    $context = new ExecutionContext(
        messages: [
            ['role' => 'user', 'content' => 'My name is John.'],
            ['role' => 'assistant', 'content' => 'Nice to meet you, John!'],
        ],
    );

    $agent = new TestAgent;
    $executor->execute($agent, 'What is my name?', $context, $mockSchema);

    // Verify the combined format
    expect($capturedInput)->toBe("User: My name is John.\n\nAssistant: Nice to meet you, John!\n\nUser: What is my name?");
});

test('it throws InvalidArgumentException for provider tool array without type key', function () {
    $mockResponse = new class
    {
        public ?string $text = 'Hello';

        public array $toolCalls = [];

        public object $finishReason;

        public function __construct()
        {
            $this->finishReason = new class
            {
                public string $value = 'stop';
            };
        }
    };

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asText')->andReturn($mockResponse);

    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->andReturn($mockPendingRequest);

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
    );

    $agent = new \Atlasphp\Atlas\Tests\Fixtures\TestAgentWithInvalidProviderTools;

    try {
        $executor->execute($agent, 'Hello');
        $this->fail('Expected exception to be thrown');
    } catch (\Atlasphp\Atlas\Agents\Exceptions\AgentException $e) {
        // The InvalidArgumentException gets wrapped in an AgentException
        expect($e->getMessage())->toContain('Invalid provider tool format at index 0');
        expect($e->getPrevious())->toBeInstanceOf(\InvalidArgumentException::class);
        expect($e->getPrevious()->getMessage())->toBe('Invalid provider tool format at index 0. Array must have a "type" key.');
    }
});

test('it passes through ProviderTool instances unchanged', function () {
    $mockResponse = new class
    {
        public ?string $text = 'Hello';

        public array $toolCalls = [];

        public array $steps = [];

        public object $finishReason;

        public function __construct()
        {
            $this->finishReason = new class
            {
                public string $value = 'stop';
            };
        }
    };

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')
        ->once()
        ->with(Mockery::on(function ($tools) {
            // Verify tools is an array of ProviderTool objects
            if (! is_array($tools) || count($tools) !== 2) {
                return false;
            }

            // First tool should be the ProviderTool instance passed through unchanged
            $first = $tools[0];
            if (! $first instanceof \Prism\Prism\ValueObjects\ProviderTool) {
                return false;
            }
            if ($first->type !== 'web_search') {
                return false;
            }
            if ($first->name !== 'custom_search') {
                return false;
            }
            if ($first->options !== ['max_results' => 10]) {
                return false;
            }

            // Second tool should be code_execution string converted
            $second = $tools[1];
            if (! $second instanceof \Prism\Prism\ValueObjects\ProviderTool) {
                return false;
            }
            if ($second->type !== 'code_execution') {
                return false;
            }

            return true;
        }))
        ->andReturnSelf();
    $mockPendingRequest->shouldReceive('asText')->andReturn($mockResponse);

    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->once()
        ->andReturn($mockPendingRequest);

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
    );

    $agent = new \Atlasphp\Atlas\Tests\Fixtures\TestAgentWithProviderToolInstances;
    $response = $executor->execute($agent, 'Hello');

    expect($response)->toBeInstanceOf(AgentResponse::class);
});

// Pipeline Handler Classes for Tests

class AgentErrorCapturingHandler implements \Atlasphp\Atlas\Foundation\Contracts\PipelineContract
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

class AgentAfterExecuteCapturingHandler implements \Atlasphp\Atlas\Foundation\Contracts\PipelineContract
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
