<?php

declare(strict_types=1);

use Atlasphp\Atlas\Pipelines\PipelineRegistry;
use Atlasphp\Atlas\Pipelines\PipelineRunner;
use Atlasphp\Atlas\Providers\Contracts\EmbeddingProviderContract;
use Atlasphp\Atlas\Providers\Embedding\PrismEmbeddingProvider;
use Atlasphp\Atlas\Providers\Services\AtlasManager;
use Atlasphp\Atlas\Providers\Services\EmbeddingService;
use Atlasphp\Atlas\Providers\Services\ImageService;
use Atlasphp\Atlas\Providers\Services\PrismBuilder;
use Atlasphp\Atlas\Providers\Services\ProviderConfigService;
use Atlasphp\Atlas\Providers\Services\SpeechService;
use Atlasphp\Atlas\Providers\Services\UsageExtractorRegistry;

test('it registers foundation services', function () {
    expect($this->app->make(PipelineRegistry::class))->toBeInstanceOf(PipelineRegistry::class);
    expect($this->app->make(PipelineRunner::class))->toBeInstanceOf(PipelineRunner::class);
});

test('it registers provider services', function () {
    expect($this->app->make(ProviderConfigService::class))->toBeInstanceOf(ProviderConfigService::class);
    expect($this->app->make(PrismBuilder::class))->toBeInstanceOf(PrismBuilder::class);
    expect($this->app->make(EmbeddingService::class))->toBeInstanceOf(EmbeddingService::class);
    expect($this->app->make(ImageService::class))->toBeInstanceOf(ImageService::class);
    expect($this->app->make(SpeechService::class))->toBeInstanceOf(SpeechService::class);
    expect($this->app->make(UsageExtractorRegistry::class))->toBeInstanceOf(UsageExtractorRegistry::class);
    expect($this->app->make(AtlasManager::class))->toBeInstanceOf(AtlasManager::class);
});

test('it binds embedding provider contract', function () {
    $provider = $this->app->make(EmbeddingProviderContract::class);

    expect($provider)->toBeInstanceOf(PrismEmbeddingProvider::class);
});

test('it defines core pipelines', function () {
    $registry = $this->app->make(PipelineRegistry::class);
    $definitions = $registry->definitions();

    expect($definitions)->toHaveKey('agent.before_execute');
    expect($definitions)->toHaveKey('agent.after_execute');
    expect($definitions)->toHaveKey('agent.system_prompt.before_build');
    expect($definitions)->toHaveKey('agent.system_prompt.after_build');
    expect($definitions)->toHaveKey('tool.before_execute');
    expect($definitions)->toHaveKey('tool.after_execute');
});

test('it resolves services as singletons', function () {
    $registry1 = $this->app->make(PipelineRegistry::class);
    $registry2 = $this->app->make(PipelineRegistry::class);

    expect($registry1)->toBe($registry2);

    $manager1 = $this->app->make(AtlasManager::class);
    $manager2 = $this->app->make(AtlasManager::class);

    expect($manager1)->toBe($manager2);
});

test('config is published correctly', function () {
    expect(config('atlas.chat.provider'))->toBe('openai');
    expect(config('atlas.chat.model'))->toBe('gpt-4o');
    expect(config('atlas.embedding.provider'))->toBe('openai');
    expect(config('atlas.embedding.model'))->toBe('text-embedding-3-small');
    expect(config('atlas.embedding.dimensions'))->toBe(1536);
    expect(config('atlas.embedding.batch_size'))->toBe(100);
});

test('services use injected configuration', function () {
    $configService = $this->app->make(ProviderConfigService::class);

    expect($configService->getChatConfig()['provider'])->toBe('openai');
    expect($configService->getEmbeddingConfig()['model'])->toBe('text-embedding-3-small');
});
