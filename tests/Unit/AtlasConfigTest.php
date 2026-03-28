<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Exceptions\ProviderNotFoundException;

it('creates from config with all properties', function () {
    config([
        'atlas.defaults' => ['text' => ['provider' => 'openai', 'model' => 'gpt-4o']],
        'atlas.providers' => ['openai' => ['api_key' => 'sk-test']],
        'atlas.queue' => 'ai',
        'atlas.retry.timeout' => 30,
        'atlas.retry.rate_limit' => 5,
        'atlas.retry.errors' => 1,
        'atlas.persistence.enabled' => true,
        'atlas.persistence.table_prefix' => 'my_',
    ]);

    $config = AtlasConfig::fromConfig();

    expect($config->defaults)->toBe(['text' => ['provider' => 'openai', 'model' => 'gpt-4o']]);
    expect($config->providers)->toBe(['openai' => ['api_key' => 'sk-test']]);
    expect($config->queue)->toBe('ai');
    expect($config->retryTimeout)->toBe(30);
    expect($config->retryRateLimit)->toBe(5);
    expect($config->retryErrors)->toBe(1);
    expect($config->persistenceEnabled)->toBeTrue();
    expect($config->tablePrefix)->toBe('my_');
});

it('returns sensible defaults when config keys are missing', function () {
    config(['atlas' => []]);

    $config = AtlasConfig::fromConfig();

    expect($config->defaults)->toBe([]);
    expect($config->providers)->toBe([]);
    expect($config->queue)->toBe('default');
    expect($config->retryTimeout)->toBe(60);
    expect($config->retryRateLimit)->toBe(3);
    expect($config->retryErrors)->toBe(2);
    expect($config->persistenceEnabled)->toBeFalse();
    expect($config->tablePrefix)->toBe('atlas_');
    expect($config->messageLimit)->toBe(50);
    expect($config->storageDisk)->toBeNull();
    expect($config->storagePrefix)->toBe('atlas');
    expect($config->embeddingDimensions)->toBe(1536);
});

it('forProvider returns provider config', function () {
    config(['atlas.providers' => ['openai' => ['api_key' => 'sk-test', 'url' => 'https://api.openai.com']]]);

    $config = AtlasConfig::fromConfig();

    expect($config->forProvider('openai'))->toBe(['api_key' => 'sk-test', 'url' => 'https://api.openai.com']);
});

it('forProvider throws for unknown provider', function () {
    config(['atlas.providers' => []]);

    $config = AtlasConfig::fromConfig();
    $config->forProvider('unknown');
})->throws(ProviderNotFoundException::class);

it('hasProvider checks existence', function () {
    config(['atlas.providers' => ['openai' => ['api_key' => 'sk-test']]]);

    $config = AtlasConfig::fromConfig();

    expect($config->hasProvider('openai'))->toBeTrue();
    expect($config->hasProvider('unknown'))->toBeFalse();
});

it('defaultFor returns provider and model', function () {
    config(['atlas.defaults' => ['text' => ['provider' => 'openai', 'model' => 'gpt-4o']]]);

    $config = AtlasConfig::fromConfig();

    expect($config->defaultFor('text'))->toBe(['provider' => 'openai', 'model' => 'gpt-4o']);
});

it('defaultFor returns null when no default configured', function () {
    config(['atlas.defaults' => []]);

    $config = AtlasConfig::fromConfig();

    expect($config->defaultFor('text'))->toBeNull();
});

it('defaultFor returns null when provider is empty', function () {
    config(['atlas.defaults' => ['text' => ['provider' => null, 'model' => 'gpt-4o']]]);

    $config = AtlasConfig::fromConfig();

    expect($config->defaultFor('text'))->toBeNull();
});

it('model returns override when configured', function () {
    config(['atlas.persistence.models' => ['conversation' => 'App\\Models\\MyConversation']]);

    $config = AtlasConfig::fromConfig();

    expect($config->model('conversation', 'DefaultConversation'))->toBe('App\\Models\\MyConversation');
});

it('model returns default when not overridden', function () {
    config(['atlas.persistence.models' => []]);

    $config = AtlasConfig::fromConfig();

    expect($config->model('conversation', 'DefaultConversation'))->toBe('DefaultConversation');
});

it('reads storage config', function () {
    config([
        'atlas.storage.disk' => 's3',
        'atlas.storage.prefix' => 'media',
    ]);

    $config = AtlasConfig::fromConfig();

    expect($config->storageDisk)->toBe('s3');
    expect($config->storagePrefix)->toBe('media');
});

it('reads cache config', function () {
    config([
        'atlas.cache.store' => 'redis',
        'atlas.cache.prefix' => 'ai',
        'atlas.cache.ttl' => ['models' => 3600, 'voices' => 1800, 'embeddings' => 600],
    ]);

    $config = AtlasConfig::fromConfig();

    expect($config->cacheStore)->toBe('redis');
    expect($config->cachePrefix)->toBe('ai');
    expect($config->cacheTtl)->toBe(['models' => 3600, 'voices' => 1800, 'embeddings' => 600]);
});
