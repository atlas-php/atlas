<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\PendingAgentRequest;
use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;
use Atlasphp\Atlas\Providers\Facades\Atlas;
use Atlasphp\Atlas\Providers\Services\AtlasManager;
use Atlasphp\Atlas\Providers\Support\PendingEmbeddingRequest;
use Atlasphp\Atlas\Providers\Support\PendingImageRequest;
use Atlasphp\Atlas\Providers\Support\PendingSpeechRequest;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;

test('facade resolves atlas manager', function () {
    expect(Atlas::getFacadeRoot())->toBeInstanceOf(AtlasManager::class);
});

test('agent returns pending agent request', function () {
    expect(Atlas::agent('test-agent'))->toBeInstanceOf(PendingAgentRequest::class);
});

test('embeddings returns pending embedding request', function () {
    expect(Atlas::embeddings())->toBeInstanceOf(PendingEmbeddingRequest::class);
});

test('it executes simple chat', function () {
    $mockResponse = new class
    {
        public ?string $text = 'Hello from agent';

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

    // Register the agent
    $registry = app(AgentRegistryContract::class);
    $registry->register(TestAgent::class);

    $response = Atlas::agent('test-agent')->chat('Hello');

    expect($response)->toBeInstanceOf(AgentResponse::class);
    expect($response->text)->toBe('Hello from agent');
});

test('it executes chat with messages', function () {
    $mockResponse = new class
    {
        public ?string $text = 'Continuing conversation';

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
    $prismBuilder->shouldReceive('forMessages')->andReturn($mockPendingRequest);

    app()->instance(PrismBuilderContract::class, $prismBuilder);

    $registry = app(AgentRegistryContract::class);
    $registry->register(TestAgent::class);

    $messages = [
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi there!'],
    ];

    $response = Atlas::agent('test-agent')
        ->withMessages($messages)
        ->chat('Continue');

    expect($response)->toBeInstanceOf(AgentResponse::class);
    expect($response->text)->toBe('Continuing conversation');
});

test('it executes chat with variables', function () {
    $mockResponse = new class
    {
        public ?string $text = 'Hello John';

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
    $prismBuilder->shouldReceive('forMessages')->andReturn($mockPendingRequest);

    app()->instance(PrismBuilderContract::class, $prismBuilder);

    $registry = app(AgentRegistryContract::class);
    $registry->register(TestAgent::class);

    $response = Atlas::agent('test-agent')
        ->withMessages([['role' => 'user', 'content' => 'Hello']])
        ->withVariables(['user_name' => 'John'])
        ->chat('Continue');

    expect($response)->toBeInstanceOf(AgentResponse::class);
    expect($response->text)->toBe('Hello John');
});

test('it executes chat with metadata', function () {
    $mockResponse = new class
    {
        public ?string $text = 'Response with metadata';

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
    $prismBuilder->shouldReceive('forMessages')->andReturn($mockPendingRequest);

    app()->instance(PrismBuilderContract::class, $prismBuilder);

    $registry = app(AgentRegistryContract::class);
    $registry->register(TestAgent::class);

    $response = Atlas::agent('test-agent')
        ->withMessages([['role' => 'user', 'content' => 'Hello']])
        ->withMetadata(['session_id' => 'abc123'])
        ->chat('Continue');

    expect($response)->toBeInstanceOf(AgentResponse::class);
    expect($response->text)->toBe('Response with metadata');
});

test('it executes structured output', function () {
    $mockResponse = new class
    {
        public mixed $structured = ['name' => 'John', 'age' => 30];

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

    $prismBuilder = Mockery::mock(PrismBuilderContract::class);
    $prismBuilder->shouldReceive('forStructured')->andReturn($mockPendingRequest);

    app()->instance(PrismBuilderContract::class, $prismBuilder);

    $registry = app(AgentRegistryContract::class);
    $registry->register(TestAgent::class);

    $response = Atlas::agent('test-agent')
        ->withSchema($mockSchema)
        ->chat('Extract person info');

    expect($response)->toBeInstanceOf(AgentResponse::class);
    expect($response->structured)->toBe(['name' => 'John', 'age' => 30]);
});

test('it returns pending image request', function () {
    $imageRequest = Atlas::image();

    expect($imageRequest)->toBeInstanceOf(PendingImageRequest::class);
});

test('it returns pending image request with provider and model', function () {
    $imageRequest = Atlas::image('openai', 'dall-e-3');

    expect($imageRequest)->toBeInstanceOf(PendingImageRequest::class);
});

test('it returns pending speech request', function () {
    $speechRequest = Atlas::speech();

    expect($speechRequest)->toBeInstanceOf(PendingSpeechRequest::class);
});

test('it returns pending speech request with provider and model', function () {
    $speechRequest = Atlas::speech('openai', 'tts-1-hd');

    expect($speechRequest)->toBeInstanceOf(PendingSpeechRequest::class);
});
