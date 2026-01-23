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
use Atlasphp\Atlas\Providers\Support\PendingImageRequest;
use Atlasphp\Atlas\Providers\Support\PendingSpeechRequest;
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

test('embeddings returns PendingEmbeddingRequest', function () {
    $result = $this->manager->embeddings();

    expect($result)->toBeInstanceOf(PendingEmbeddingRequest::class);
});

// ===========================================
// IMAGE SERVICE TESTS
// ===========================================

test('image returns PendingImageRequest', function () {
    $result = $this->manager->image();

    expect($result)->toBeInstanceOf(PendingImageRequest::class);
});

test('image returns PendingImageRequest with provider', function () {
    $result = $this->manager->image('openai');

    expect($result)->toBeInstanceOf(PendingImageRequest::class);
});

test('image returns PendingImageRequest with provider and model', function () {
    $result = $this->manager->image('openai', 'dall-e-3');

    expect($result)->toBeInstanceOf(PendingImageRequest::class);
});

// ===========================================
// SPEECH SERVICE TESTS
// ===========================================

test('speech returns PendingSpeechRequest', function () {
    $result = $this->manager->speech();

    expect($result)->toBeInstanceOf(PendingSpeechRequest::class);
});

test('speech returns PendingSpeechRequest with provider', function () {
    $result = $this->manager->speech('openai');

    expect($result)->toBeInstanceOf(PendingSpeechRequest::class);
});

test('speech returns PendingSpeechRequest with provider and model', function () {
    $result = $this->manager->speech('openai', 'tts-1-hd');

    expect($result)->toBeInstanceOf(PendingSpeechRequest::class);
});
