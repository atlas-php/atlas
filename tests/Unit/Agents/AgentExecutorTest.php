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
