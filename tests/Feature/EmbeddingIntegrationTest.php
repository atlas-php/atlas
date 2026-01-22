<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Foundation\Services\PipelineRunner;
use Atlasphp\Atlas\Providers\Contracts\EmbeddingProviderContract;
use Atlasphp\Atlas\Providers\Facades\Atlas;
use Atlasphp\Atlas\Providers\Services\AtlasManager;
use Atlasphp\Atlas\Providers\Services\EmbeddingService;
use Atlasphp\Atlas\Providers\Services\ImageService;
use Atlasphp\Atlas\Providers\Services\SpeechService;

beforeEach(function () {
    // Mock the embedding provider to avoid real API calls
    $mockProvider = Mockery::mock(EmbeddingProviderContract::class);

    $mockProvider->shouldReceive('generate')
        ->andReturnUsing(function (string $text) {
            // Return a deterministic fake embedding
            return array_fill(0, 1536, 0.1);
        });

    $mockProvider->shouldReceive('generateBatch')
        ->andReturnUsing(function (array $texts) {
            return array_map(
                fn () => array_fill(0, 1536, 0.1),
                $texts,
            );
        });

    $mockProvider->shouldReceive('dimensions')
        ->andReturn(1536);

    $mockProvider->shouldReceive('provider')
        ->andReturn('openai');

    $mockProvider->shouldReceive('model')
        ->andReturn('text-embedding-3-small');

    $this->app->instance(EmbeddingProviderContract::class, $mockProvider);

    // Rebind EmbeddingService to use the mocked provider
    $this->app->singleton(EmbeddingService::class, function ($app) use ($mockProvider) {
        return new EmbeddingService(
            $mockProvider,
            $app->make(PipelineRunner::class),
        );
    });

    // Rebind AtlasManager to use the new EmbeddingService
    $this->app->singleton(AtlasManager::class, function ($app) {
        return new AtlasManager(
            $app->make(AgentResolver::class),
            $app->make(AgentExecutorContract::class),
            $app->make(EmbeddingService::class),
            $app->make(ImageService::class),
            $app->make(SpeechService::class),
        );
    });
});

test('it generates embedding via facade', function () {
    $embedding = Atlas::embed('test text');

    expect($embedding)->toBeArray();
    expect(count($embedding))->toBe(1536);
    expect($embedding[0])->toBe(0.1);
});

test('it generates batch embeddings via facade', function () {
    $embeddings = Atlas::embedBatch(['text 1', 'text 2', 'text 3']);

    expect($embeddings)->toBeArray();
    expect(count($embeddings))->toBe(3);

    foreach ($embeddings as $embedding) {
        expect(count($embedding))->toBe(1536);
    }
});

test('it returns configured dimensions via facade', function () {
    $dimensions = Atlas::embeddingDimensions();

    expect($dimensions)->toBe(1536);
});

test('it generates embedding via manager', function () {
    $manager = $this->app->make(AtlasManager::class);

    $embedding = $manager->embed('test text');

    expect($embedding)->toBeArray();
    expect(count($embedding))->toBe(1536);
});

test('it generates batch embeddings via manager', function () {
    $manager = $this->app->make(AtlasManager::class);

    $embeddings = $manager->embedBatch(['text 1', 'text 2']);

    expect($embeddings)->toBeArray();
    expect(count($embeddings))->toBe(2);
});

test('facade provides access to image service', function () {
    $imageService = Atlas::image();

    expect($imageService)->toBeInstanceOf(\Atlasphp\Atlas\Providers\Services\ImageService::class);
});

test('facade provides access to speech service', function () {
    $speechService = Atlas::speech();

    expect($speechService)->toBeInstanceOf(\Atlasphp\Atlas\Providers\Services\SpeechService::class);
});
