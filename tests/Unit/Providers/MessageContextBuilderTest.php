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

test('it creates with messages', function () {
    $messages = [
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi there'],
    ];

    $builder = new MessageContextBuilder($this->manager, $messages);

    expect($builder->getMessages())->toBe($messages);
    expect($builder->getVariables())->toBe([]);
    expect($builder->getMetadata())->toBe([]);
});

test('it adds variables immutably', function () {
    $messages = [['role' => 'user', 'content' => 'Hello']];
    $variables = ['user_name' => 'John'];

    $builder = new MessageContextBuilder($this->manager, $messages);
    $newBuilder = $builder->withVariables($variables);

    expect($builder->getVariables())->toBe([]);
    expect($newBuilder->getVariables())->toBe($variables);
    expect($newBuilder->getMessages())->toBe($messages);
});

test('it adds metadata immutably', function () {
    $messages = [['role' => 'user', 'content' => 'Hello']];
    $metadata = ['session_id' => 'abc123'];

    $builder = new MessageContextBuilder($this->manager, $messages);
    $newBuilder = $builder->withMetadata($metadata);

    expect($builder->getMetadata())->toBe([]);
    expect($newBuilder->getMetadata())->toBe($metadata);
    expect($newBuilder->getMessages())->toBe($messages);
});

test('it chains multiple operations', function () {
    $messages = [['role' => 'user', 'content' => 'Hello']];
    $variables = ['user_name' => 'John'];
    $metadata = ['session_id' => 'abc123'];

    $builder = new MessageContextBuilder($this->manager, $messages);
    $newBuilder = $builder
        ->withVariables($variables)
        ->withMetadata($metadata);

    expect($newBuilder->getMessages())->toBe($messages);
    expect($newBuilder->getVariables())->toBe($variables);
    expect($newBuilder->getMetadata())->toBe($metadata);

    // Original should remain unchanged
    expect($builder->getVariables())->toBe([]);
    expect($builder->getMetadata())->toBe([]);
});

test('it executes chat with context', function () {
    $agent = new TestAgent;
    $messages = [['role' => 'user', 'content' => 'Hello']];
    $variables = ['user_name' => 'John'];
    $metadata = ['session_id' => 'abc123'];
    $response = AgentResponse::text('Hello John');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->with('test-agent')
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($a, $input, $context, $schema) use ($agent, $messages, $variables, $metadata) {
            return $a === $agent
                && $input === 'Continue'
                && $context instanceof ExecutionContext
                && $context->messages === $messages
                && $context->variables === $variables
                && $context->metadata === $metadata
                && $schema === null;
        })
        ->andReturn($response);

    $builder = new MessageContextBuilder($this->manager, $messages);
    $result = $builder
        ->withVariables($variables)
        ->withMetadata($metadata)
        ->chat('test-agent', 'Continue');

    expect($result)->toBe($response);
});

test('it executes chat with schema', function () {
    $agent = new TestAgent;
    $messages = [['role' => 'user', 'content' => 'Hello']];
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
        ->withArgs(function ($a, $input, $context, $s) use ($agent, $schema) {
            return $a === $agent
                && $input === 'Extract'
                && $context instanceof ExecutionContext
                && $s === $schema;
        })
        ->andReturn($response);

    $builder = new MessageContextBuilder($this->manager, $messages);
    $result = $builder->chat('test-agent', 'Extract', $schema);

    expect($result)->toBe($response);
});

test('it returns messages', function () {
    $messages = [
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi'],
    ];

    $builder = new MessageContextBuilder($this->manager, $messages);

    expect($builder->getMessages())->toBe($messages);
});

test('it returns variables', function () {
    $variables = ['user_name' => 'John', 'account' => 'premium'];

    $builder = (new MessageContextBuilder($this->manager, []))
        ->withVariables($variables);

    expect($builder->getVariables())->toBe($variables);
});

test('it returns metadata', function () {
    $metadata = ['session_id' => 'abc', 'trace_id' => 'xyz'];

    $builder = (new MessageContextBuilder($this->manager, []))
        ->withMetadata($metadata);

    expect($builder->getMetadata())->toBe($metadata);
});

test('it preserves immutability across chain', function () {
    $messages = [['role' => 'user', 'content' => 'Hello']];

    $builder1 = new MessageContextBuilder($this->manager, $messages);
    $builder2 = $builder1->withVariables(['name' => 'John']);
    $builder3 = $builder2->withMetadata(['id' => '123']);
    $builder4 = $builder3->withVariables(['name' => 'Jane']);

    expect($builder1->getVariables())->toBe([]);
    expect($builder1->getMetadata())->toBe([]);

    expect($builder2->getVariables())->toBe(['name' => 'John']);
    expect($builder2->getMetadata())->toBe([]);

    expect($builder3->getVariables())->toBe(['name' => 'John']);
    expect($builder3->getMetadata())->toBe(['id' => '123']);

    expect($builder4->getVariables())->toBe(['name' => 'Jane']);
    expect($builder4->getMetadata())->toBe(['id' => '123']);
});

// ===========================================
// STREAMING TESTS
// ===========================================

test('it executes chat with stream true', function () {
    $agent = new TestAgent;
    $messages = [['role' => 'user', 'content' => 'Hello']];
    $variables = ['user_name' => 'John'];
    $metadata = ['session_id' => 'abc123'];
    $streamResponse = Mockery::mock(\Atlasphp\Atlas\Streaming\StreamResponse::class);

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->with('test-agent')
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('stream')
        ->once()
        ->withArgs(function ($a, $input, $context) use ($agent, $messages, $variables, $metadata) {
            return $a === $agent
                && $input === 'Continue'
                && $context instanceof ExecutionContext
                && $context->messages === $messages
                && $context->variables === $variables
                && $context->metadata === $metadata;
        })
        ->andReturn($streamResponse);

    $builder = new MessageContextBuilder($this->manager, $messages);
    $result = $builder
        ->withVariables($variables)
        ->withMetadata($metadata)
        ->chat('test-agent', 'Continue', stream: true);

    expect($result)->toBe($streamResponse);
    expect($result)->toBeInstanceOf(\Atlasphp\Atlas\Streaming\StreamResponse::class);
});

test('it executes chat without stream returns agent response', function () {
    $agent = new TestAgent;
    $messages = [['role' => 'user', 'content' => 'Hello']];
    $response = AgentResponse::text('Hello');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->with('test-agent')
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($a, $input, $context, $schema) use ($agent) {
            return $a === $agent
                && $input === 'Continue'
                && $context instanceof ExecutionContext
                && $schema === null;
        })
        ->andReturn($response);

    $builder = new MessageContextBuilder($this->manager, $messages);
    $result = $builder->chat('test-agent', 'Continue', stream: false);

    expect($result)->toBe($response);
    expect($result)->toBeInstanceOf(AgentResponse::class);
});

test('it does not support schema with streaming', function () {
    // Schema is not passed when streaming, streaming always uses stream path
    $agent = new TestAgent;
    $messages = [['role' => 'user', 'content' => 'Hello']];
    $streamResponse = Mockery::mock(\Atlasphp\Atlas\Streaming\StreamResponse::class);

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->with('test-agent')
        ->andReturn($agent);

    // When stream: true, it should call stream() not execute()
    $this->agentExecutor
        ->shouldReceive('stream')
        ->once()
        ->andReturn($streamResponse);

    $builder = new MessageContextBuilder($this->manager, $messages);
    $result = $builder->chat('test-agent', 'Extract', stream: true);

    expect($result)->toBe($streamResponse);
});

// ===========================================
// RETRY TESTS
// ===========================================

test('it adds retry immutably', function () {
    $messages = [['role' => 'user', 'content' => 'Hello']];

    $builder = new MessageContextBuilder($this->manager, $messages);
    $newBuilder = $builder->withRetry(3, 1000);

    // New builder should be a different instance
    expect($newBuilder)->not->toBe($builder);
    expect($newBuilder)->toBeInstanceOf(MessageContextBuilder::class);

    // Messages should be preserved
    expect($newBuilder->getMessages())->toBe($messages);
});

test('it chains retry with other operations', function () {
    $messages = [['role' => 'user', 'content' => 'Hello']];
    $variables = ['user_name' => 'John'];
    $metadata = ['session_id' => 'abc123'];

    $builder = new MessageContextBuilder($this->manager, $messages);
    $newBuilder = $builder
        ->withVariables($variables)
        ->withRetry(3, 1000)
        ->withMetadata($metadata);

    expect($newBuilder->getMessages())->toBe($messages);
    expect($newBuilder->getVariables())->toBe($variables);
    expect($newBuilder->getMetadata())->toBe($metadata);
});

test('it passes retry to execute when chatting', function () {
    $agent = new TestAgent;
    $messages = [['role' => 'user', 'content' => 'Hello']];
    $response = AgentResponse::text('Hello');
    $retryConfig = [3, 1000, null, true];

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->with('test-agent')
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($a, $input, $context, $schema, $retry) use ($agent, $retryConfig) {
            return $a === $agent
                && $input === 'Continue'
                && $context instanceof ExecutionContext
                && $schema === null
                && $retry === $retryConfig;
        })
        ->andReturn($response);

    $builder = new MessageContextBuilder($this->manager, $messages);
    $result = $builder
        ->withRetry(3, 1000, null, true)
        ->chat('test-agent', 'Continue');

    expect($result)->toBe($response);
});

test('it passes retry to stream when streaming', function () {
    $agent = new TestAgent;
    $messages = [['role' => 'user', 'content' => 'Hello']];
    $streamResponse = Mockery::mock(\Atlasphp\Atlas\Streaming\StreamResponse::class);
    $retryConfig = [3, 1000, null, true];

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->with('test-agent')
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('stream')
        ->once()
        ->withArgs(function ($a, $input, $context, $retry) use ($agent, $retryConfig) {
            return $a === $agent
                && $input === 'Continue'
                && $context instanceof ExecutionContext
                && $retry === $retryConfig;
        })
        ->andReturn($streamResponse);

    $builder = new MessageContextBuilder($this->manager, $messages);
    $result = $builder
        ->withRetry(3, 1000, null, true)
        ->chat('test-agent', 'Continue', stream: true);

    expect($result)->toBe($streamResponse);
});

test('it accepts retry with array of delays', function () {
    $agent = new TestAgent;
    $messages = [['role' => 'user', 'content' => 'Hello']];
    $response = AgentResponse::text('Hello');
    $delays = [100, 200, 300];

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->with('test-agent')
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($a, $input, $context, $schema, $retry) use ($delays) {
            return $retry[0] === $delays;
        })
        ->andReturn($response);

    $builder = new MessageContextBuilder($this->manager, $messages);
    $result = $builder
        ->withRetry($delays)
        ->chat('test-agent', 'Continue');

    expect($result)->toBe($response);
});

test('it accepts retry with closure for sleep', function () {
    $agent = new TestAgent;
    $messages = [['role' => 'user', 'content' => 'Hello']];
    $response = AgentResponse::text('Hello');
    $sleepFn = fn ($attempt) => $attempt * 100;

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->with('test-agent')
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($a, $input, $context, $schema, $retry) use ($sleepFn) {
            return $retry[0] === 3 && $retry[1] === $sleepFn;
        })
        ->andReturn($response);

    $builder = new MessageContextBuilder($this->manager, $messages);
    $result = $builder
        ->withRetry(3, $sleepFn)
        ->chat('test-agent', 'Continue');

    expect($result)->toBe($response);
});
