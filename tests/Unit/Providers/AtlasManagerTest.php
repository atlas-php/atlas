<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Providers\Services\AtlasManager;
use Atlasphp\Atlas\Providers\Services\EmbeddingService;
use Atlasphp\Atlas\Providers\Services\ImageService;
use Atlasphp\Atlas\Providers\Services\SpeechService;
use Atlasphp\Atlas\Providers\Support\MessageContextBuilder;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;

beforeEach(function () {
    $this->agentResolver = Mockery::mock(AgentResolver::class);
    $this->agentExecutor = Mockery::mock(AgentExecutorContract::class);
    $this->embeddingService = Mockery::mock(EmbeddingService::class);
    $this->imageService = Mockery::mock(ImageService::class);
    $this->speechService = Mockery::mock(SpeechService::class);

    $this->manager = new AtlasManager(
        $this->agentResolver,
        $this->agentExecutor,
        $this->embeddingService,
        $this->imageService,
        $this->speechService,
    );
});

afterEach(function () {
    Mockery::close();
});

test('it executes chat with agent key', function () {
    $agent = new TestAgent;
    $response = AgentResponse::text('Hello');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->with('test-agent')
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->with($agent, 'Hello', null, null, null)
        ->andReturn($response);

    $result = $this->manager->chat('test-agent', 'Hello');

    expect($result)->toBe($response);
});

test('it executes chat with agent class', function () {
    $agent = new TestAgent;
    $response = AgentResponse::text('Hello');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->with(TestAgent::class)
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->with($agent, 'Hello', null, null, null)
        ->andReturn($response);

    $result = $this->manager->chat(TestAgent::class, 'Hello');

    expect($result)->toBe($response);
});

test('it executes chat with agent instance', function () {
    $agent = new TestAgent;
    $response = AgentResponse::text('Hello');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->with($agent)
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->with($agent, 'Hello', null, null, null)
        ->andReturn($response);

    $result = $this->manager->chat($agent, 'Hello');

    expect($result)->toBe($response);
});

test('it executes chat with messages', function () {
    $agent = new TestAgent;
    $messages = [['role' => 'user', 'content' => 'Previous']];
    $response = AgentResponse::text('Hello');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->with('test-agent')
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($a, $input, $context, $schema) use ($agent, $messages) {
            return $a === $agent
                && $input === 'Hello'
                && $context instanceof ExecutionContext
                && $context->messages === $messages
                && $schema === null;
        })
        ->andReturn($response);

    $result = $this->manager->chat('test-agent', 'Hello', $messages);

    expect($result)->toBe($response);
});

test('it executes chat with schema', function () {
    $agent = new TestAgent;
    $schema = Mockery::mock(\Prism\Prism\Contracts\Schema::class);
    $response = AgentResponse::structured(['name' => 'John']);

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->with('test-agent')
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->with($agent, 'Hello', null, $schema, null)
        ->andReturn($response);

    $result = $this->manager->chat('test-agent', 'Hello', null, $schema);

    expect($result)->toBe($response);
});

test('it returns message context builder', function () {
    $messages = [['role' => 'user', 'content' => 'Hello']];

    $builder = $this->manager->forMessages($messages);

    expect($builder)->toBeInstanceOf(MessageContextBuilder::class);
    expect($builder->getMessages())->toBe($messages);
});

test('it generates embedding', function () {
    $embedding = [0.1, 0.2, 0.3];

    $this->embeddingService
        ->shouldReceive('generate')
        ->once()
        ->with('Hello', [], null)
        ->andReturn($embedding);

    $result = $this->manager->embed('Hello');

    expect($result)->toBe($embedding);
});

test('it generates batch embeddings', function () {
    $embeddings = [[0.1, 0.2], [0.3, 0.4]];

    $this->embeddingService
        ->shouldReceive('generateBatch')
        ->once()
        ->with(['Hello', 'World'], [], null)
        ->andReturn($embeddings);

    $result = $this->manager->embedBatch(['Hello', 'World']);

    expect($result)->toBe($embeddings);
});

test('it returns embedding dimensions', function () {
    $this->embeddingService
        ->shouldReceive('dimensions')
        ->once()
        ->andReturn(1536);

    $result = $this->manager->embeddingDimensions();

    expect($result)->toBe(1536);
});

test('it returns image service', function () {
    $result = $this->manager->image();

    expect($result)->toBe($this->imageService);
});

test('it returns image service with provider', function () {
    $clonedService = Mockery::mock(ImageService::class);

    $this->imageService
        ->shouldReceive('using')
        ->once()
        ->with('openai')
        ->andReturn($clonedService);

    $result = $this->manager->image('openai');

    expect($result)->toBe($clonedService);
});

test('it returns image service with provider and model', function () {
    $serviceWithProvider = Mockery::mock(ImageService::class);
    $serviceWithModel = Mockery::mock(ImageService::class);

    $this->imageService
        ->shouldReceive('using')
        ->once()
        ->with('openai')
        ->andReturn($serviceWithProvider);

    $serviceWithProvider
        ->shouldReceive('model')
        ->once()
        ->with('dall-e-3')
        ->andReturn($serviceWithModel);

    $result = $this->manager->image('openai', 'dall-e-3');

    expect($result)->toBe($serviceWithModel);
});

test('it returns speech service', function () {
    $result = $this->manager->speech();

    expect($result)->toBe($this->speechService);
});

test('it returns speech service with provider', function () {
    $clonedService = Mockery::mock(SpeechService::class);

    $this->speechService
        ->shouldReceive('using')
        ->once()
        ->with('openai')
        ->andReturn($clonedService);

    $result = $this->manager->speech('openai');

    expect($result)->toBe($clonedService);
});

test('it returns speech service with provider and model', function () {
    $serviceWithProvider = Mockery::mock(SpeechService::class);
    $serviceWithModel = Mockery::mock(SpeechService::class);

    $this->speechService
        ->shouldReceive('using')
        ->once()
        ->with('openai')
        ->andReturn($serviceWithProvider);

    $serviceWithProvider
        ->shouldReceive('model')
        ->once()
        ->with('tts-1-hd')
        ->andReturn($serviceWithModel);

    $result = $this->manager->speech('openai', 'tts-1-hd');

    expect($result)->toBe($serviceWithModel);
});

test('it executes with context', function () {
    $agent = new TestAgent;
    $context = new ExecutionContext(
        messages: [['role' => 'user', 'content' => 'Hello']],
        variables: ['user_name' => 'John'],
        metadata: ['session_id' => 'abc'],
    );
    $response = AgentResponse::text('Hello John');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->with('test-agent')
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->with($agent, 'Continue', $context, null, null)
        ->andReturn($response);

    $result = $this->manager->executeWithContext('test-agent', 'Continue', $context);

    expect($result)->toBe($response);
});

// ===========================================
// STREAMING TESTS
// ===========================================

test('it executes chat with stream true calls executor stream', function () {
    $agent = new TestAgent;
    $streamResponse = Mockery::mock(\Atlasphp\Atlas\Streaming\StreamResponse::class);

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->with('test-agent')
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('stream')
        ->once()
        ->with($agent, 'Hello', null, null)
        ->andReturn($streamResponse);

    $result = $this->manager->chat('test-agent', 'Hello', stream: true);

    expect($result)->toBe($streamResponse);
});

test('it executes chat with messages and stream true', function () {
    $agent = new TestAgent;
    $messages = [['role' => 'user', 'content' => 'Previous']];
    $streamResponse = Mockery::mock(\Atlasphp\Atlas\Streaming\StreamResponse::class);

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->with('test-agent')
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('stream')
        ->once()
        ->withArgs(function ($a, $input, $context) use ($agent, $messages) {
            return $a === $agent
                && $input === 'Hello'
                && $context instanceof ExecutionContext
                && $context->messages === $messages;
        })
        ->andReturn($streamResponse);

    $result = $this->manager->chat('test-agent', 'Hello', $messages, stream: true);

    expect($result)->toBe($streamResponse);
});

test('it streams with context', function () {
    $agent = new TestAgent;
    $context = new ExecutionContext(
        messages: [['role' => 'user', 'content' => 'Hello']],
        variables: ['user_name' => 'John'],
        metadata: ['session_id' => 'abc'],
    );
    $streamResponse = Mockery::mock(\Atlasphp\Atlas\Streaming\StreamResponse::class);

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->with('test-agent')
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('stream')
        ->once()
        ->with($agent, 'Continue', $context, null)
        ->andReturn($streamResponse);

    $result = $this->manager->streamWithContext('test-agent', 'Continue', $context);

    expect($result)->toBe($streamResponse);
});

test('it executes chat without stream returns agent response', function () {
    $agent = new TestAgent;
    $response = AgentResponse::text('Hello');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->with('test-agent')
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->with($agent, 'Hello', null, null, null)
        ->andReturn($response);

    $result = $this->manager->chat('test-agent', 'Hello', stream: false);

    expect($result)->toBe($response);
    expect($result)->toBeInstanceOf(AgentResponse::class);
});

test('it throws exception when streaming with schema', function () {
    $agent = new TestAgent;
    $schema = Mockery::mock(\Prism\Prism\Contracts\Schema::class);

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->with('test-agent')
        ->andReturn($agent);

    expect(fn () => $this->manager->chat('test-agent', 'Hello', schema: $schema, stream: true))
        ->toThrow(
            \InvalidArgumentException::class,
            'Streaming does not support structured output (schema). Use stream: false for structured responses.'
        );
});

// ===========================================
// RETRY TESTS
// ===========================================

test('it returns pending atlas request when calling withRetry', function () {
    $result = $this->manager->withRetry(3, 1000);

    expect($result)->toBeInstanceOf(\Atlasphp\Atlas\Providers\Support\PendingAtlasRequest::class);
});

test('it returns pending atlas request with array of delays', function () {
    $result = $this->manager->withRetry([100, 200, 300]);

    expect($result)->toBeInstanceOf(\Atlasphp\Atlas\Providers\Support\PendingAtlasRequest::class);
});

test('it returns pending atlas request with all retry parameters', function () {
    $whenCallback = fn ($e) => $e->getCode() === 429;

    $result = $this->manager->withRetry(3, 1000, $whenCallback, false);

    expect($result)->toBeInstanceOf(\Atlasphp\Atlas\Providers\Support\PendingAtlasRequest::class);
});
