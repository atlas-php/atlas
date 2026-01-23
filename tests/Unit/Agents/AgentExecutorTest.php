<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Services\AgentExecutor;
use Atlasphp\Atlas\Agents\Services\SystemPromptBuilder;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;
use Atlasphp\Atlas\Foundation\Services\PipelineRunner;
use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;
use Atlasphp\Atlas\Providers\Services\ProviderConfigService;
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

    // Create mock for ProviderConfigService
    $this->configService = Mockery::mock(ProviderConfigService::class);
    $this->configService->shouldReceive('getRetryConfig')->andReturn(null);
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
        $this->configService,
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
        $this->configService,
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
        $this->configService,
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
        $this->configService,
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
        $this->configService,
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
        $this->configService,
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
        $this->configService,
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
        $this->configService,
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
        $this->configService,
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
        $this->configService,
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
        $this->configService,
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
    $configService = Mockery::mock(ProviderConfigService::class);
    $configService->shouldReceive('getRetryConfig')->andReturn(null);

    $executor = new AgentExecutor(
        $prismBuilder,
        $toolBuilder,
        $systemPromptBuilder,
        $runner,
        $usageExtractor,
        $configService,
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
    $configService = Mockery::mock(ProviderConfigService::class);
    $configService->shouldReceive('getRetryConfig')->andReturn(null);

    $executor = new AgentExecutor(
        $prismBuilder,
        $toolBuilder,
        $systemPromptBuilder,
        $runner,
        $usageExtractor,
        $configService,
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
        $this->configService,
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
        $this->configService,
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
        $this->configService,
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
    $configService = Mockery::mock(ProviderConfigService::class);
    $configService->shouldReceive('getRetryConfig')->andReturn(null);

    $executor = new AgentExecutor(
        $prismBuilder,
        $toolBuilder,
        $systemPromptBuilder,
        $runner,
        $usageExtractor,
        $configService,
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
        $this->configService,
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

// ===========================================
// STREAMING TESTS
// ===========================================

test('stream returns StreamResponse', function () {
    $prismTextDeltaEvent = new \Prism\Prism\Streaming\Events\TextDeltaEvent(
        id: 'evt_1',
        timestamp: 1234567890,
        delta: 'Hello',
        messageId: 'msg_1',
    );

    $prismUsage = new \Prism\Prism\ValueObjects\Usage(
        promptTokens: 10,
        completionTokens: 5,
    );

    $prismStreamEndEvent = new \Prism\Prism\Streaming\Events\StreamEndEvent(
        id: 'evt_2',
        timestamp: 1234567891,
        finishReason: \Prism\Prism\Enums\FinishReason::Stop,
        usage: $prismUsage,
    );

    $mockStreamGenerator = function () use ($prismTextDeltaEvent, $prismStreamEndEvent) {
        yield $prismTextDeltaEvent;
        yield $prismStreamEndEvent;
    };

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asStream')->andReturn($mockStreamGenerator());

    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->andReturn($mockPendingRequest);

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
        $this->configService,
    );

    $agent = new TestAgent;
    $stream = $executor->stream($agent, 'Hello');

    // Verify return type
    expect($stream)->toBeInstanceOf(\Atlasphp\Atlas\Streaming\StreamResponse::class);

    // Iterate to trigger execution (generators are lazy)
    iterator_to_array($stream);
});

test('stream generates text delta events', function () {
    // Create real Prism TextDeltaEvent
    $prismTextDeltaEvent = new \Prism\Prism\Streaming\Events\TextDeltaEvent(
        id: 'evt_1',
        timestamp: 1234567890,
        delta: 'Hello World',
        messageId: 'msg_1',
    );

    // Create real Prism StreamEndEvent with usage
    $prismUsage = new \Prism\Prism\ValueObjects\Usage(
        promptTokens: 10,
        completionTokens: 5,
    );

    $prismStreamEndEvent = new \Prism\Prism\Streaming\Events\StreamEndEvent(
        id: 'evt_2',
        timestamp: 1234567891,
        finishReason: \Prism\Prism\Enums\FinishReason::Stop,
        usage: $prismUsage,
    );

    $mockStreamGenerator = function () use ($prismTextDeltaEvent, $prismStreamEndEvent) {
        yield $prismTextDeltaEvent;
        yield $prismStreamEndEvent;
    };

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asStream')->andReturn($mockStreamGenerator());

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
        $this->configService,
    );

    $agent = new TestAgent;
    $stream = $executor->stream($agent, 'Hello');

    // Iterate and collect events
    $events = iterator_to_array($stream);

    expect($events)->toHaveCount(2);
    expect($events[0])->toBeInstanceOf(\Atlasphp\Atlas\Streaming\Events\TextDeltaEvent::class);
    expect($events[0]->text)->toBe('Hello World');
    expect($events[1])->toBeInstanceOf(\Atlasphp\Atlas\Streaming\Events\StreamEndEvent::class);
    expect($events[1]->finishReason)->toBe('stop');
});

test('stream runs stream.on_event pipeline for each event', function () {
    $container = new Container;
    $registry = new PipelineRegistry;
    $runner = new PipelineRunner($registry, $container);

    // Define and activate the stream pipelines
    $registry->define('stream.on_event', 'Stream event pipeline');

    StreamOnEventCapturingHandler::reset();
    $registry->register('stream.on_event', StreamOnEventCapturingHandler::class);

    // Create real Prism events
    $prismTextDeltaEvent = new \Prism\Prism\Streaming\Events\TextDeltaEvent(
        id: 'evt_1',
        timestamp: 1234567890,
        delta: 'Hello',
        messageId: 'msg_1',
    );

    $prismUsage = new \Prism\Prism\ValueObjects\Usage(
        promptTokens: 10,
        completionTokens: 5,
    );

    $prismStreamEndEvent = new \Prism\Prism\Streaming\Events\StreamEndEvent(
        id: 'evt_2',
        timestamp: 1234567891,
        finishReason: \Prism\Prism\Enums\FinishReason::Stop,
        usage: $prismUsage,
    );

    $mockStreamGenerator = function () use ($prismTextDeltaEvent, $prismStreamEndEvent) {
        yield $prismTextDeltaEvent;
        yield $prismStreamEndEvent;
    };

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asStream')->andReturn($mockStreamGenerator());

    $prismBuilder = Mockery::mock(PrismBuilderContract::class);
    $prismBuilder->shouldReceive('forPrompt')->andReturn($mockPendingRequest);

    $systemPromptBuilder = new SystemPromptBuilder($runner);
    $toolRegistry = new ToolRegistry($container);
    $toolExecutor = new ToolExecutor($runner);
    $toolBuilder = new ToolBuilder($toolRegistry, $toolExecutor, $container);
    $usageExtractor = new UsageExtractorRegistry;
    $configService = Mockery::mock(ProviderConfigService::class);
    $configService->shouldReceive('getRetryConfig')->andReturn(null);

    $executor = new AgentExecutor(
        $prismBuilder,
        $toolBuilder,
        $systemPromptBuilder,
        $runner,
        $usageExtractor,
        $configService,
    );

    $agent = new TestAgent;
    $stream = $executor->stream($agent, 'Hello');
    iterator_to_array($stream);

    expect(StreamOnEventCapturingHandler::$events)->toHaveCount(2);
    expect(StreamOnEventCapturingHandler::$events[0]['event'])->toBeInstanceOf(\Atlasphp\Atlas\Streaming\Events\TextDeltaEvent::class);
    expect(StreamOnEventCapturingHandler::$events[0]['agent'])->toBe($agent);
});

test('stream runs stream.after_complete pipeline when stream ends', function () {
    $container = new Container;
    $registry = new PipelineRegistry;
    $runner = new PipelineRunner($registry, $container);

    // Define and activate the stream pipelines
    $registry->define('stream.after_complete', 'Stream after complete pipeline');

    StreamAfterCompleteCapturingHandler::reset();
    $registry->register('stream.after_complete', StreamAfterCompleteCapturingHandler::class);

    $prismUsage = new \Prism\Prism\ValueObjects\Usage(
        promptTokens: 10,
        completionTokens: 5,
    );

    $prismStreamEndEvent = new \Prism\Prism\Streaming\Events\StreamEndEvent(
        id: 'evt_1',
        timestamp: 1234567890,
        finishReason: \Prism\Prism\Enums\FinishReason::Stop,
        usage: $prismUsage,
    );

    $mockStreamGenerator = function () use ($prismStreamEndEvent) {
        yield $prismStreamEndEvent;
    };

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asStream')->andReturn($mockStreamGenerator());

    $prismBuilder = Mockery::mock(PrismBuilderContract::class);
    $prismBuilder->shouldReceive('forPrompt')->andReturn($mockPendingRequest);

    $systemPromptBuilder = new SystemPromptBuilder($runner);
    $toolRegistry = new ToolRegistry($container);
    $toolExecutor = new ToolExecutor($runner);
    $toolBuilder = new ToolBuilder($toolRegistry, $toolExecutor, $container);
    $usageExtractor = new UsageExtractorRegistry;
    $configService = Mockery::mock(ProviderConfigService::class);
    $configService->shouldReceive('getRetryConfig')->andReturn(null);

    $executor = new AgentExecutor(
        $prismBuilder,
        $toolBuilder,
        $systemPromptBuilder,
        $runner,
        $usageExtractor,
        $configService,
    );

    $agent = new TestAgent;
    $stream = $executor->stream($agent, 'Hello');
    iterator_to_array($stream);

    expect(StreamAfterCompleteCapturingHandler::$called)->toBeTrue();
    expect(StreamAfterCompleteCapturingHandler::$data['agent'])->toBe($agent);
    expect(StreamAfterCompleteCapturingHandler::$data['input'])->toBe('Hello');
    expect(array_key_exists('system_prompt', StreamAfterCompleteCapturingHandler::$data))->toBeTrue();
});

test('stream runs agent.on_error pipeline when execution fails', function () {
    $container = new Container;
    $registry = new PipelineRegistry;
    $runner = new PipelineRunner($registry, $container);

    // Define and activate the error pipeline
    $registry->define('agent.on_error', 'Error pipeline');

    AgentErrorCapturingHandler::reset();
    $registry->register('agent.on_error', AgentErrorCapturingHandler::class);

    $prismBuilder = Mockery::mock(PrismBuilderContract::class);
    $prismBuilder->shouldReceive('forPrompt')
        ->andThrow(new \RuntimeException('Stream API Error'));

    $systemPromptBuilder = new SystemPromptBuilder($runner);
    $toolRegistry = new ToolRegistry($container);
    $toolExecutor = new ToolExecutor($runner);
    $toolBuilder = new ToolBuilder($toolRegistry, $toolExecutor, $container);
    $usageExtractor = new UsageExtractorRegistry;
    $configService = Mockery::mock(ProviderConfigService::class);
    $configService->shouldReceive('getRetryConfig')->andReturn(null);

    $executor = new AgentExecutor(
        $prismBuilder,
        $toolBuilder,
        $systemPromptBuilder,
        $runner,
        $usageExtractor,
        $configService,
    );

    $agent = new TestAgent;

    try {
        $stream = $executor->stream($agent, 'Hello');
        iterator_to_array($stream);
    } catch (\Atlasphp\Atlas\Agents\Exceptions\AgentException $e) {
        // Expected
    }

    expect(AgentErrorCapturingHandler::$called)->toBeTrue();
    expect(AgentErrorCapturingHandler::$data['agent'])->toBe($agent);
    expect(AgentErrorCapturingHandler::$data['exception'])->toBeInstanceOf(\RuntimeException::class);
});

test('stream yields error event when execution fails', function () {
    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asStream')
        ->andThrow(new \RuntimeException('Stream failed'));

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
        $this->configService,
    );

    $agent = new TestAgent;
    $stream = $executor->stream($agent, 'Hello');

    $events = [];
    try {
        foreach ($stream as $event) {
            $events[] = $event;
        }
    } catch (\Atlasphp\Atlas\Agents\Exceptions\AgentException $e) {
        // Expected
    }

    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(\Atlasphp\Atlas\Streaming\Events\ErrorEvent::class);
    expect($events[0]->message)->toBe('Stream failed');
    expect($events[0]->recoverable)->toBeFalse();
});

test('stream uses forMessages when context has messages', function () {
    $prismUsage = new \Prism\Prism\ValueObjects\Usage(
        promptTokens: 10,
        completionTokens: 5,
    );

    $prismStreamEndEvent = new \Prism\Prism\Streaming\Events\StreamEndEvent(
        id: 'evt_1',
        timestamp: 1234567890,
        finishReason: \Prism\Prism\Enums\FinishReason::Stop,
        usage: $prismUsage,
    );

    $mockStreamGenerator = function () use ($prismStreamEndEvent) {
        yield $prismStreamEndEvent;
    };

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asStream')->andReturn($mockStreamGenerator());

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
        $this->configService,
    );

    $context = new ExecutionContext(
        messages: [['role' => 'user', 'content' => 'Previous message']],
    );

    $agent = new TestAgent;
    $stream = $executor->stream($agent, 'Hello', $context);
    iterator_to_array($stream);

    // Mockery will fail if forMessages was not called
    expect(true)->toBeTrue();
});

test('stream converts tool call events', function () {
    // Create real Prism ToolCallEvent
    $prismToolCall = new \Prism\Prism\ValueObjects\ToolCall(
        id: 'call_123',
        name: 'calculator',
        arguments: ['operation' => 'add', 'a' => 5, 'b' => 3],
    );

    $prismToolCallEvent = new \Prism\Prism\Streaming\Events\ToolCallEvent(
        id: 'evt_1',
        timestamp: 1234567890,
        toolCall: $prismToolCall,
        messageId: 'msg_1',
    );

    // Create real Prism ToolResultEvent
    $prismToolResult = new \Prism\Prism\ValueObjects\ToolResult(
        toolCallId: 'call_123',
        toolName: 'calculator',
        args: ['operation' => 'add', 'a' => 5, 'b' => 3],
        result: '8',
    );

    $prismToolResultEvent = new \Prism\Prism\Streaming\Events\ToolResultEvent(
        id: 'evt_2',
        timestamp: 1234567891,
        toolResult: $prismToolResult,
        messageId: 'msg_1',
        success: true,
    );

    // Create real Prism StreamEndEvent
    $prismUsage = new \Prism\Prism\ValueObjects\Usage(
        promptTokens: 10,
        completionTokens: 5,
    );

    $prismStreamEndEvent = new \Prism\Prism\Streaming\Events\StreamEndEvent(
        id: 'evt_3',
        timestamp: 1234567892,
        finishReason: \Prism\Prism\Enums\FinishReason::Stop,
        usage: $prismUsage,
    );

    $mockStreamGenerator = function () use ($prismToolCallEvent, $prismToolResultEvent, $prismStreamEndEvent) {
        yield $prismToolCallEvent;
        yield $prismToolResultEvent;
        yield $prismStreamEndEvent;
    };

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asStream')->andReturn($mockStreamGenerator());

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
        $this->configService,
    );

    $agent = new TestAgent;
    $stream = $executor->stream($agent, 'What is 5 + 3?');
    $events = iterator_to_array($stream);

    expect($events)->toHaveCount(3);
    expect($events[0])->toBeInstanceOf(\Atlasphp\Atlas\Streaming\Events\ToolCallStartEvent::class);
    expect($events[0]->toolId)->toBe('call_123');
    expect($events[0]->toolName)->toBe('calculator');
    expect($events[0]->arguments)->toBe(['operation' => 'add', 'a' => 5, 'b' => 3]);

    expect($events[1])->toBeInstanceOf(\Atlasphp\Atlas\Streaming\Events\ToolCallEndEvent::class);
    expect($events[1]->toolId)->toBe('call_123');
    expect($events[1]->toolName)->toBe('calculator');
    expect($events[1]->result)->toBe('8');
    expect($events[1]->success)->toBeTrue();
});

test('stream converts stream start event', function () {
    $prismStreamStartEvent = new \Prism\Prism\Streaming\Events\StreamStartEvent(
        id: 'evt_start',
        timestamp: 1234567890,
        model: 'gpt-4',
        provider: 'openai',
    );

    $prismUsage = new \Prism\Prism\ValueObjects\Usage(
        promptTokens: 10,
        completionTokens: 5,
    );

    $prismStreamEndEvent = new \Prism\Prism\Streaming\Events\StreamEndEvent(
        id: 'evt_end',
        timestamp: 1234567891,
        finishReason: \Prism\Prism\Enums\FinishReason::Stop,
        usage: $prismUsage,
    );

    $mockStreamGenerator = function () use ($prismStreamStartEvent, $prismStreamEndEvent) {
        yield $prismStreamStartEvent;
        yield $prismStreamEndEvent;
    };

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asStream')->andReturn($mockStreamGenerator());

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
        $this->configService,
    );

    $agent = new TestAgent;
    $stream = $executor->stream($agent, 'Hello');
    $events = iterator_to_array($stream);

    expect($events[0])->toBeInstanceOf(\Atlasphp\Atlas\Streaming\Events\StreamStartEvent::class);
    expect($events[0]->model)->toBe('gpt-4');
    expect($events[0]->provider)->toBe('openai');
});

test('stream extracts usage from stream end event', function () {
    $prismUsage = new \Prism\Prism\ValueObjects\Usage(
        promptTokens: 50,
        completionTokens: 25,
    );

    $prismStreamEndEvent = new \Prism\Prism\Streaming\Events\StreamEndEvent(
        id: 'evt_end',
        timestamp: 1234567890,
        finishReason: \Prism\Prism\Enums\FinishReason::Stop,
        usage: $prismUsage,
    );

    $mockStreamGenerator = function () use ($prismStreamEndEvent) {
        yield $prismStreamEndEvent;
    };

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asStream')->andReturn($mockStreamGenerator());

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
        $this->configService,
    );

    $agent = new TestAgent;
    $stream = $executor->stream($agent, 'Hello');
    $events = iterator_to_array($stream);

    $endEvent = $events[0];
    expect($endEvent)->toBeInstanceOf(\Atlasphp\Atlas\Streaming\Events\StreamEndEvent::class);
    expect($endEvent->usage)->toBe([
        'prompt_tokens' => 50,
        'completion_tokens' => 25,
        'total_tokens' => 75,
    ]);
    expect($endEvent->promptTokens())->toBe(50);
    expect($endEvent->completionTokens())->toBe(25);
    expect($endEvent->totalTokens())->toBe(75);
});

test('stream returns empty usage when stream end event has null usage', function () {
    $prismStreamEndEvent = new \Prism\Prism\Streaming\Events\StreamEndEvent(
        id: 'evt_end',
        timestamp: 1234567890,
        finishReason: \Prism\Prism\Enums\FinishReason::Stop,
        usage: null,
    );

    $mockStreamGenerator = function () use ($prismStreamEndEvent) {
        yield $prismStreamEndEvent;
    };

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asStream')->andReturn($mockStreamGenerator());

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
        $this->configService,
    );

    $agent = new TestAgent;
    $stream = $executor->stream($agent, 'Hello');
    $events = iterator_to_array($stream);

    $endEvent = $events[0];
    expect($endEvent)->toBeInstanceOf(\Atlasphp\Atlas\Streaming\Events\StreamEndEvent::class);
    expect($endEvent->usage)->toBe([]);
    expect($endEvent->promptTokens())->toBe(0);
    expect($endEvent->completionTokens())->toBe(0);
    expect($endEvent->totalTokens())->toBe(0);
});

test('stream converts Prism error event to Atlas error event', function () {
    $prismErrorEvent = new \Prism\Prism\Streaming\Events\ErrorEvent(
        id: 'err_123',
        timestamp: 1234567890,
        errorType: 'rate_limit',
        message: 'Rate limit exceeded',
        recoverable: true,
    );

    $prismUsage = new \Prism\Prism\ValueObjects\Usage(
        promptTokens: 10,
        completionTokens: 0,
    );

    $prismStreamEndEvent = new \Prism\Prism\Streaming\Events\StreamEndEvent(
        id: 'evt_end',
        timestamp: 1234567891,
        finishReason: \Prism\Prism\Enums\FinishReason::Stop,
        usage: $prismUsage,
    );

    $mockStreamGenerator = function () use ($prismErrorEvent, $prismStreamEndEvent) {
        yield $prismErrorEvent;
        yield $prismStreamEndEvent;
    };

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asStream')->andReturn($mockStreamGenerator());

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
        $this->configService,
    );

    $agent = new TestAgent;
    $stream = $executor->stream($agent, 'Hello');
    $events = iterator_to_array($stream);

    expect($events)->toHaveCount(2);
    expect($events[0])->toBeInstanceOf(\Atlasphp\Atlas\Streaming\Events\ErrorEvent::class);
    expect($events[0]->id)->toBe('err_123');
    expect($events[0]->timestamp)->toBe(1234567890);
    expect($events[0]->errorType)->toBe('rate_limit');
    expect($events[0]->message)->toBe('Rate limit exceeded');
    expect($events[0]->recoverable)->toBeTrue();
});

// ===========================================
// PROVIDER TOOLS TESTS
// ===========================================

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
        $this->configService,
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
        $this->configService,
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

class StreamOnEventCapturingHandler implements \Atlasphp\Atlas\Foundation\Contracts\PipelineContract
{
    public static array $events = [];

    public static function reset(): void
    {
        self::$events = [];
    }

    public function handle(mixed $data, \Closure $next): mixed
    {
        self::$events[] = $data;

        return $next($data);
    }
}

class StreamAfterCompleteCapturingHandler implements \Atlasphp\Atlas\Foundation\Contracts\PipelineContract
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
