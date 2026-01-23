<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Agents\Support\PendingAgentRequest;
use Atlasphp\Atlas\Providers\Services\AtlasManager;
use Atlasphp\Atlas\Providers\Services\EmbeddingService;
use Atlasphp\Atlas\Providers\Services\ImageService;
use Atlasphp\Atlas\Providers\Services\SpeechService;
use Atlasphp\Atlas\Providers\Support\PendingEmbeddingRequest;
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

// ===========================================
// AGENT TESTS
// ===========================================

test('agent returns PendingAgentRequest with agent key', function () {
    $result = $this->manager->agent('test-agent');

    expect($result)->toBeInstanceOf(PendingAgentRequest::class);
});

test('agent returns PendingAgentRequest with agent class', function () {
    $result = $this->manager->agent(TestAgent::class);

    expect($result)->toBeInstanceOf(PendingAgentRequest::class);
});

test('agent returns PendingAgentRequest with agent instance', function () {
    $agent = new TestAgent;

    $result = $this->manager->agent($agent);

    expect($result)->toBeInstanceOf(PendingAgentRequest::class);
});

// ===========================================
// EMBEDDING TESTS
// ===========================================

test('embedding returns PendingEmbeddingRequest', function () {
    $result = $this->manager->embedding();

    expect($result)->toBeInstanceOf(PendingEmbeddingRequest::class);
});

test('embed generates embedding', function () {
    $embedding = [0.1, 0.2, 0.3];

    $this->embeddingService
        ->shouldReceive('generate')
        ->once()
        ->with('Hello')
        ->andReturn($embedding);

    $result = $this->manager->embed('Hello');

    expect($result)->toBe($embedding);
});

test('embedBatch generates batch embeddings', function () {
    $embeddings = [[0.1, 0.2], [0.3, 0.4]];

    $this->embeddingService
        ->shouldReceive('generateBatch')
        ->once()
        ->with(['Hello', 'World'])
        ->andReturn($embeddings);

    $result = $this->manager->embedBatch(['Hello', 'World']);

    expect($result)->toBe($embeddings);
});

test('embeddingDimensions returns dimensions', function () {
    $this->embeddingService
        ->shouldReceive('dimensions')
        ->once()
        ->andReturn(1536);

    $result = $this->manager->embeddingDimensions();

    expect($result)->toBe(1536);
});

// ===========================================
// IMAGE SERVICE TESTS
// ===========================================

test('image returns image service', function () {
    $result = $this->manager->image();

    expect($result)->toBe($this->imageService);
});

test('image returns image service with provider', function () {
    $clonedService = Mockery::mock(ImageService::class);

    $this->imageService
        ->shouldReceive('using')
        ->once()
        ->with('openai')
        ->andReturn($clonedService);

    $result = $this->manager->image('openai');

    expect($result)->toBe($clonedService);
});

test('image returns image service with provider and model', function () {
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

// ===========================================
// SPEECH SERVICE TESTS
// ===========================================

test('speech returns speech service', function () {
    $result = $this->manager->speech();

    expect($result)->toBe($this->speechService);
});

test('speech returns speech service with provider', function () {
    $clonedService = Mockery::mock(SpeechService::class);

    $this->speechService
        ->shouldReceive('using')
        ->once()
        ->with('openai')
        ->andReturn($clonedService);

    $result = $this->manager->speech('openai');

    expect($result)->toBe($clonedService);
});

test('speech returns speech service with provider and model', function () {
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
