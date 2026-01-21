<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Agents\Services\AgentExecutor;
use Atlasphp\Atlas\Agents\Services\AgentRegistry;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Agents\Services\SystemPromptBuilder;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;

test('it registers agent services in container', function () {
    expect(app(AgentRegistryContract::class))->toBeInstanceOf(AgentRegistry::class);
    expect(app(AgentExecutorContract::class))->toBeInstanceOf(AgentExecutor::class);
    expect(app(AgentResolver::class))->toBeInstanceOf(AgentResolver::class);
    expect(app(SystemPromptBuilder::class))->toBeInstanceOf(SystemPromptBuilder::class);
});

test('it resolves services as singletons', function () {
    $registry1 = app(AgentRegistryContract::class);
    $registry2 = app(AgentRegistryContract::class);

    expect($registry1)->toBe($registry2);
});

test('agent registry registers and retrieves agents', function () {
    $registry = app(AgentRegistryContract::class);

    $registry->register(TestAgent::class);

    expect($registry->has('test-agent'))->toBeTrue();
    expect($registry->get('test-agent'))->toBeInstanceOf(TestAgent::class);
});

test('agent resolver resolves from multiple sources', function () {
    $registry = app(AgentRegistryContract::class);
    $resolver = app(AgentResolver::class);

    // From instance
    $agent = new TestAgent;
    expect($resolver->resolve($agent))->toBe($agent);

    // From registry
    $registry->register(TestAgent::class);
    expect($resolver->resolve('test-agent'))->toBeInstanceOf(TestAgent::class);

    // From class
    expect($resolver->resolve(TestAgent::class))->toBeInstanceOf(TestAgent::class);
});

test('system prompt builder interpolates variables', function () {
    $builder = app(SystemPromptBuilder::class);
    $agent = new TestAgent;

    $context = new ExecutionContext(variables: [
        'agent_name' => 'Atlas',
        'user_name' => 'Jane',
    ]);

    $prompt = $builder->build($agent, $context);

    expect($prompt)->toContain('You are Atlas');
    expect($prompt)->toContain('Help Jane');
});

test('agent executor integrates with mocked prism', function () {
    // Mock the PrismBuilder response
    $mockResponse = new class
    {
        public ?string $text = 'Mocked response';

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

    $prismBuilder = Mockery::mock(PrismBuilderContract::class);
    $prismBuilder->shouldReceive('forPrompt')->andReturn($mockPendingRequest);

    app()->instance(PrismBuilderContract::class, $prismBuilder);

    $executor = app(AgentExecutorContract::class);
    $agent = new TestAgent;

    $response = $executor->execute($agent, 'Hello');

    expect($response)->toBeInstanceOf(AgentResponse::class);
    expect($response->text)->toBe('Mocked response');
});
