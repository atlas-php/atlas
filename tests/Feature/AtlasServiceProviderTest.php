<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasManager;
use Atlasphp\Atlas\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\OpenAi\OpenAiDriver;

it('registers ProviderRegistryContract as a singleton', function () {
    $first = $this->app->make(ProviderRegistryContract::class);
    $second = $this->app->make(ProviderRegistryContract::class);

    expect($first)->toBeInstanceOf(ProviderRegistryContract::class);
    expect($first)->toBe($second);
});

it('registers AtlasManager as a singleton', function () {
    $first = $this->app->make(AtlasManager::class);
    $second = $this->app->make(AtlasManager::class);

    expect($first)->toBeInstanceOf(AtlasManager::class);
    expect($first)->toBe($second);
});

it('merges the atlas config', function () {
    expect(config('atlas.default.provider'))->not->toBeNull();
});

it('registers the openai provider factory', function () {
    $registry = $this->app->make(ProviderRegistryContract::class);

    expect($registry->has('openai'))->toBeTrue();
});

it('resolves openai to OpenAiDriver', function () {
    config()->set('atlas.providers.openai', [
        'api_key' => 'test-key',
        'url' => 'https://api.openai.com/v1',
    ]);

    $registry = $this->app->make(ProviderRegistryContract::class);
    $driver = $registry->resolve('openai');

    expect($driver)->toBeInstanceOf(OpenAiDriver::class);
    expect($driver->name())->toBe('openai');
});
