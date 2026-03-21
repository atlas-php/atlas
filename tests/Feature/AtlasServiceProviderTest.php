<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasManager;
use Atlasphp\Atlas\Providers\Cohere\CohereDriver;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Google\GoogleDriver;
use Atlasphp\Atlas\Providers\Jina\JinaDriver;
use Atlasphp\Atlas\Providers\OpenAi\OpenAiDriver;
use Atlasphp\Atlas\Providers\Xai\XaiDriver;

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
    expect(config('atlas.defaults'))->not->toBeNull();
    expect(config('atlas.providers'))->not->toBeNull();
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

it('registers the xai provider factory', function () {
    $registry = $this->app->make(ProviderRegistryContract::class);

    expect($registry->has('xai'))->toBeTrue();
});

it('resolves xai to XaiDriver', function () {
    config()->set('atlas.providers.xai', [
        'api_key' => 'test-key',
        'url' => 'https://api.x.ai/v1',
    ]);

    $registry = $this->app->make(ProviderRegistryContract::class);
    $driver = $registry->resolve('xai');

    expect($driver)->toBeInstanceOf(XaiDriver::class);
    expect($driver->name())->toBe('xai');
});

it('registers the google provider factory', function () {
    $registry = $this->app->make(ProviderRegistryContract::class);

    expect($registry->has('google'))->toBeTrue();
});

it('resolves google to GoogleDriver', function () {
    config()->set('atlas.providers.google', [
        'api_key' => 'test-key',
        'url' => 'https://generativelanguage.googleapis.com',
    ]);

    $registry = $this->app->make(ProviderRegistryContract::class);
    $driver = $registry->resolve('google');

    expect($driver)->toBeInstanceOf(GoogleDriver::class);
    expect($driver->name())->toBe('google');
});

it('registers the cohere provider factory', function () {
    $registry = $this->app->make(ProviderRegistryContract::class);

    expect($registry->has('cohere'))->toBeTrue();
});

it('resolves cohere to CohereDriver', function () {
    config()->set('atlas.providers.cohere', [
        'api_key' => 'test-key',
        'url' => 'https://api.cohere.com',
    ]);

    $registry = $this->app->make(ProviderRegistryContract::class);
    $driver = $registry->resolve('cohere');

    expect($driver)->toBeInstanceOf(CohereDriver::class);
    expect($driver->name())->toBe('cohere');
});

it('registers the jina provider factory', function () {
    $registry = $this->app->make(ProviderRegistryContract::class);

    expect($registry->has('jina'))->toBeTrue();
});

it('resolves jina to JinaDriver', function () {
    config()->set('atlas.providers.jina', [
        'api_key' => 'test-key',
        'url' => 'https://api.jina.ai',
    ]);

    $registry = $this->app->make(ProviderRegistryContract::class);
    $driver = $registry->resolve('jina');

    expect($driver)->toBeInstanceOf(JinaDriver::class);
    expect($driver->name())->toBe('jina');
});
