<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Services\AgentExecutor;
use Atlasphp\Atlas\Agents\Services\SystemPromptBuilder;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;
use Atlasphp\Atlas\Foundation\Services\PipelineRunner;
use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;
use Atlasphp\Atlas\Providers\Services\ProviderConfigService;
use Atlasphp\Atlas\Providers\Services\UsageExtractorRegistry;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Atlasphp\Atlas\Tools\Enums\ToolChoice;
use Atlasphp\Atlas\Tools\Services\ToolBuilder;
use Atlasphp\Atlas\Tools\Services\ToolExecutor;
use Atlasphp\Atlas\Tools\Services\ToolRegistry;
use Illuminate\Container\Container;
use Prism\Prism\Enums\ToolChoice as PrismToolChoice;

beforeEach(function () {
    $this->container = new Container;
    $registry = new PipelineRegistry;
    $this->runner = new PipelineRunner($registry, $this->container);
    $this->prismBuilder = Mockery::mock(PrismBuilderContract::class);
    $this->systemPromptBuilder = new SystemPromptBuilder($this->runner);
    $toolRegistry = new ToolRegistry($this->container);
    $toolExecutor = new ToolExecutor($this->runner);
    $this->toolBuilder = new ToolBuilder($toolRegistry, $toolExecutor, $this->container);
    $this->usageExtractor = new UsageExtractorRegistry;
    $this->configService = Mockery::mock(ProviderConfigService::class);
    $this->configService->shouldReceive('getRetryConfig')->andReturn(null);
});

afterEach(function () {
    Mockery::close();
});

function createMockResponse()
{
    return new class
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
}

test('it applies ToolChoice::Auto to request', function () {
    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withToolChoice')
        ->once()
        ->with(PrismToolChoice::Auto)
        ->andReturnSelf();
    $mockPendingRequest->shouldReceive('asText')->andReturn(createMockResponse());

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
    $context = new ExecutionContext(toolChoice: ToolChoice::Auto);

    $executor->execute($agent, 'Hello', $context);
});

test('it applies ToolChoice::Any to request', function () {
    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withToolChoice')
        ->once()
        ->with(PrismToolChoice::Any)
        ->andReturnSelf();
    $mockPendingRequest->shouldReceive('asText')->andReturn(createMockResponse());

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
    $context = new ExecutionContext(toolChoice: ToolChoice::Any);

    $executor->execute($agent, 'Hello', $context);
});

test('it applies ToolChoice::None to request', function () {
    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withToolChoice')
        ->once()
        ->with(PrismToolChoice::None)
        ->andReturnSelf();
    $mockPendingRequest->shouldReceive('asText')->andReturn(createMockResponse());

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
    $context = new ExecutionContext(toolChoice: ToolChoice::None);

    $executor->execute($agent, 'Hello', $context);
});

test('it applies string tool name to request', function () {
    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withToolChoice')
        ->once()
        ->with('calculator')
        ->andReturnSelf();
    $mockPendingRequest->shouldReceive('asText')->andReturn(createMockResponse());

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
    $context = new ExecutionContext(toolChoice: 'calculator');

    $executor->execute($agent, 'Hello', $context);
});

test('it does not apply tool choice when context has null', function () {
    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldNotReceive('withToolChoice');
    $mockPendingRequest->shouldReceive('asText')->andReturn(createMockResponse());

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
    $context = new ExecutionContext(toolChoice: null);

    $executor->execute($agent, 'Hello', $context);
});
