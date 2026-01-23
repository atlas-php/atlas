<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Providers\Services\AtlasManager;
use Atlasphp\Atlas\Providers\Services\EmbeddingService;
use Atlasphp\Atlas\Providers\Services\ImageService;
use Atlasphp\Atlas\Providers\Services\SpeechService;
use Atlasphp\Atlas\Providers\Support\MessageContextBuilder;
use Atlasphp\Atlas\Providers\Support\PendingAtlasRequest;
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

test('withRetry returns new instance with config', function () {
    $request = new PendingAtlasRequest($this->manager);
    $newRequest = $request->withRetry(3, 1000);

    expect($newRequest)->not->toBe($request);
    expect($newRequest)->toBeInstanceOf(PendingAtlasRequest::class);
});

test('chat passes retry config to manager', function () {
    $agent = new TestAgent;
    $response = AgentResponse::text('Hello');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->with('test-agent')
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->withArgs(function ($a, $input, $context, $schema, $retry) use ($agent) {
            return $a === $agent
                && $input === 'Hello'
                && $retry !== null
                && $retry[0] === 3
                && $retry[1] === 1000;
        })
        ->andReturn($response);

    $result = (new PendingAtlasRequest($this->manager))
        ->withRetry(3, 1000)
        ->chat('test-agent', 'Hello');

    expect($result)->toBe($response);
});

test('embed passes retry config to service', function () {
    $embedding = [0.1, 0.2, 0.3];

    $this->embeddingService
        ->shouldReceive('generate')
        ->withArgs(function ($text, $options, $retry) {
            return $text === 'Hello'
                && $retry !== null
                && $retry[0] === 3;
        })
        ->andReturn($embedding);

    $result = (new PendingAtlasRequest($this->manager))
        ->withRetry(3, 1000)
        ->embed('Hello');

    expect($result)->toBe($embedding);
});

test('embedBatch passes retry config to service', function () {
    $embeddings = [[0.1, 0.2], [0.3, 0.4]];

    $this->embeddingService
        ->shouldReceive('generateBatch')
        ->withArgs(function ($texts, $options, $retry) {
            return $texts === ['Hello', 'World']
                && $retry !== null
                && $retry[0] === 3;
        })
        ->andReturn($embeddings);

    $result = (new PendingAtlasRequest($this->manager))
        ->withRetry(3, 1000)
        ->embedBatch(['Hello', 'World']);

    expect($result)->toBe($embeddings);
});

test('forMessages returns builder with retry config', function () {
    $messages = [['role' => 'user', 'content' => 'Hello']];

    $builder = (new PendingAtlasRequest($this->manager))
        ->withRetry(3, 1000)
        ->forMessages($messages);

    expect($builder)->toBeInstanceOf(MessageContextBuilder::class);
});

test('image applies retry to service', function () {
    $clonedService = Mockery::mock(ImageService::class);

    $this->imageService
        ->shouldReceive('withRetry')
        ->with(3, 1000, null, true)
        ->andReturn($clonedService);

    $result = (new PendingAtlasRequest($this->manager))
        ->withRetry(3, 1000)
        ->image();

    expect($result)->toBe($clonedService);
});

test('speech applies retry to service', function () {
    $clonedService = Mockery::mock(SpeechService::class);

    $this->speechService
        ->shouldReceive('withRetry')
        ->with(3, 1000, null, true)
        ->andReturn($clonedService);

    $result = (new PendingAtlasRequest($this->manager))
        ->withRetry(3, 1000)
        ->speech();

    expect($result)->toBe($clonedService);
});

test('image returns service without retry when not configured', function () {
    $request = new PendingAtlasRequest($this->manager);

    $result = $request->image();

    expect($result)->toBe($this->imageService);
});

test('speech returns service without retry when not configured', function () {
    $request = new PendingAtlasRequest($this->manager);

    $result = $request->speech();

    expect($result)->toBe($this->speechService);
});

test('image passes provider and model to manager', function () {
    $clonedService = Mockery::mock(ImageService::class);

    $this->imageService
        ->shouldReceive('using')
        ->with('anthropic')
        ->andReturn($clonedService);

    $clonedService
        ->shouldReceive('model')
        ->with('custom-model')
        ->andReturn($clonedService);

    // No retry configured, so original service is used
    $this->manager = Mockery::mock(AtlasManager::class);
    $this->manager->shouldReceive('image')
        ->with('anthropic', 'custom-model')
        ->andReturn($this->imageService);

    $request = new PendingAtlasRequest($this->manager);
    $result = $request->image('anthropic', 'custom-model');

    expect($result)->toBe($this->imageService);
});

test('speech passes provider and model to manager', function () {
    $clonedService = Mockery::mock(SpeechService::class);

    $this->speechService
        ->shouldReceive('using')
        ->with('elevenlabs')
        ->andReturn($clonedService);

    $clonedService
        ->shouldReceive('model')
        ->with('custom-voice')
        ->andReturn($clonedService);

    // No retry configured, so original service is used
    $this->manager = Mockery::mock(AtlasManager::class);
    $this->manager->shouldReceive('speech')
        ->with('elevenlabs', 'custom-voice')
        ->andReturn($this->speechService);

    $request = new PendingAtlasRequest($this->manager);
    $result = $request->speech('elevenlabs', 'custom-voice');

    expect($result)->toBe($this->speechService);
});

test('chat passes null retry config when withRetry not called', function () {
    $agent = new TestAgent;
    $response = AgentResponse::text('Hello');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->with('test-agent')
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->withArgs(function ($a, $input, $context, $schema, $retry) use ($agent) {
            return $a === $agent
                && $input === 'Hello'
                && $retry === null;
        })
        ->andReturn($response);

    $result = (new PendingAtlasRequest($this->manager))
        ->chat('test-agent', 'Hello');

    expect($result)->toBe($response);
});

test('embed passes null retry config when withRetry not called', function () {
    $embedding = [0.1, 0.2, 0.3];

    $this->embeddingService
        ->shouldReceive('generate')
        ->withArgs(function ($text, $options, $retry) {
            return $text === 'Hello'
                && $retry === null;
        })
        ->andReturn($embedding);

    $result = (new PendingAtlasRequest($this->manager))
        ->embed('Hello');

    expect($result)->toBe($embedding);
});

test('withRetry stores all parameters', function () {
    $when = fn () => true;
    $request = new PendingAtlasRequest($this->manager);
    $newRequest = $request->withRetry(5, 2000, $when, false);

    $response = AgentResponse::text('Hello');
    $agent = new TestAgent;

    $this->agentResolver
        ->shouldReceive('resolve')
        ->with('test-agent')
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->withArgs(function ($a, $input, $context, $schema, $retry) use ($when) {
            return $retry[0] === 5
                && $retry[1] === 2000
                && $retry[2] === $when
                && $retry[3] === false;
        })
        ->andReturn($response);

    $result = $newRequest->chat('test-agent', 'Hello');

    expect($result)->toBe($response);
});

test('withRetry accepts closure for sleep', function () {
    $sleep = fn (int $attempt): int => $attempt * 100;
    $request = new PendingAtlasRequest($this->manager);
    $newRequest = $request->withRetry(3, $sleep);

    $response = AgentResponse::text('Hello');
    $agent = new TestAgent;

    $this->agentResolver
        ->shouldReceive('resolve')
        ->with('test-agent')
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->withArgs(function ($a, $input, $context, $schema, $retry) use ($sleep) {
            return $retry[0] === 3
                && $retry[1] === $sleep;
        })
        ->andReturn($response);

    $result = $newRequest->chat('test-agent', 'Hello');

    expect($result)->toBe($response);
});

test('withRetry accepts array of delays', function () {
    $delays = [100, 200, 300];
    $request = new PendingAtlasRequest($this->manager);
    $newRequest = $request->withRetry($delays);

    $response = AgentResponse::text('Hello');
    $agent = new TestAgent;

    $this->agentResolver
        ->shouldReceive('resolve')
        ->with('test-agent')
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->withArgs(function ($a, $input, $context, $schema, $retry) use ($delays) {
            return $retry[0] === $delays;
        })
        ->andReturn($response);

    $result = $newRequest->chat('test-agent', 'Hello');

    expect($result)->toBe($response);
});
