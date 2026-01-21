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
    $mockPendingRequest->shouldReceive('withProviderTool')->andReturnSelf();
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
    $mockPendingRequest->shouldReceive('withProviderTool')->andReturnSelf();
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
    $mockPendingRequest->shouldReceive('withProviderTool')->andReturnSelf();
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
    $mockPendingRequest->shouldReceive('withProviderTool')->andReturnSelf();
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
    $mockPendingRequest->shouldReceive('withProviderTool')->andReturnSelf();
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
    $mockPendingRequest->shouldReceive('withProviderTool')->andReturnSelf();
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
