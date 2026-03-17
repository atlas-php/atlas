<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Services\AgentExtensionRegistry;
use Atlasphp\Atlas\AtlasServiceProvider;
use Atlasphp\Atlas\Pipelines\Middleware\CacheEmbeddings;
use Atlasphp\Atlas\Pipelines\PipelineRegistry;
use Atlasphp\Atlas\Pipelines\PipelineRunner;

test('it registers AgentExtensionRegistry as singleton', function () {
    $instance1 = app(AgentExtensionRegistry::class);
    $instance2 = app(AgentExtensionRegistry::class);

    expect($instance1)->toBeInstanceOf(AgentExtensionRegistry::class);
    expect($instance1)->toBe($instance2);
});

test('it registers PipelineRegistry as singleton', function () {
    $instance1 = app(PipelineRegistry::class);
    $instance2 = app(PipelineRegistry::class);

    expect($instance1)->toBeInstanceOf(PipelineRegistry::class);
    expect($instance1)->toBe($instance2);
});

test('it registers PipelineRunner as singleton', function () {
    $instance1 = app(PipelineRunner::class);
    $instance2 = app(PipelineRunner::class);

    expect($instance1)->toBeInstanceOf(PipelineRunner::class);
    expect($instance1)->toBe($instance2);
});

test('it registers CacheEmbeddings middleware when cache is enabled', function () {
    config(['atlas.embeddings.cache.enabled' => true]);

    // Re-boot the provider to trigger registerEmbeddingCacheMiddleware
    $provider = new AtlasServiceProvider(app());
    $provider->boot();

    $registry = app(PipelineRegistry::class);
    $handlers = $registry->getWithPriority('embeddings.before_embeddings');

    $cacheHandlers = array_filter(
        $handlers,
        fn (array $entry) => $entry['handler'] === CacheEmbeddings::class
    );

    expect($cacheHandlers)->not->toBeEmpty();

    $cacheHandler = array_values($cacheHandlers)[0];
    expect($cacheHandler['priority'])->toBe(100);
});

test('it does not register CacheEmbeddings middleware when cache is disabled', function () {
    config(['atlas.embeddings.cache.enabled' => false]);

    $registry = app(PipelineRegistry::class);
    $handlers = $registry->getWithPriority('embeddings.before_embeddings');

    $cacheHandlers = array_filter(
        $handlers,
        fn (array $entry) => $entry['handler'] === CacheEmbeddings::class
    );

    expect($cacheHandlers)->toBeEmpty();
});

test('it does not register CacheEmbeddings middleware by default', function () {
    // Default config has cache disabled
    $registry = app(PipelineRegistry::class);
    $handlers = $registry->getWithPriority('embeddings.before_embeddings');

    $cacheHandlers = array_filter(
        $handlers,
        fn (array $entry) => $entry['handler'] === CacheEmbeddings::class
    );

    expect($cacheHandlers)->toBeEmpty();
});

test('it registers make:tool artisan command', function () {
    $this->artisan('make:tool', ['name' => 'TestProbe'])
        ->assertExitCode(0);

    // Clean up
    $path = app_path('Tools/TestProbe.php');
    if (file_exists($path)) {
        unlink($path);
    }
});

test('it registers make:agent artisan command', function () {
    $this->artisan('make:agent', ['name' => 'TestProbe'])
        ->assertExitCode(0);

    // Clean up
    $path = app_path('Agents/TestProbe.php');
    if (file_exists($path)) {
        unlink($path);
    }
});
