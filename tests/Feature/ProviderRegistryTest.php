<?php

declare(strict_types=1);

use Atlasphp\Atlas\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Exceptions\ProviderNotFoundException;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Providers\ProviderConfig;

function createTestDriverInstance(?ProviderConfig $config = null, ?HttpClient $http = null): Driver
{
    $config ??= new ProviderConfig(apiKey: 'test', baseUrl: 'https://api.test.com');
    $http ??= app(HttpClient::class);

    return new class($config, $http) extends Driver
    {
        public function capabilities(): ProviderCapabilities
        {
            return new ProviderCapabilities;
        }

        public function name(): string
        {
            return 'test';
        }
    };
}

beforeEach(function () {
    $this->registry = app(ProviderRegistryContract::class);
});

it('registers and resolves a provider', function () {
    $this->registry->register('test', fn ($app, $config) => createTestDriverInstance());

    $driver = $this->registry->resolve('test');

    expect($driver)->toBeInstanceOf(Driver::class);
    expect($driver->name())->toBe('test');
});

it('caches resolved instances', function () {
    $callCount = 0;

    $this->registry->register('test', function ($app, $config) use (&$callCount) {
        $callCount++;

        return createTestDriverInstance();
    });

    $first = $this->registry->resolve('test');
    $second = $this->registry->resolve('test');

    expect($first)->toBe($second);
    expect($callCount)->toBe(1);
});

it('throws ProviderNotFoundException for unknown key', function () {
    $this->registry->resolve('unknown');
})->throws(ProviderNotFoundException::class, 'No provider registered for key [unknown].');

it('returns true for registered keys', function () {
    $this->registry->register('test', fn ($app, $config) => createTestDriverInstance());

    expect($this->registry->has('test'))->toBeTrue();
});

it('returns false for unregistered keys', function () {
    expect($this->registry->has('nonexistent'))->toBeFalse();
});

it('returns all registered keys', function () {
    $this->registry->register('openai', fn ($app, $config) => createTestDriverInstance());
    $this->registry->register('anthropic', fn ($app, $config) => createTestDriverInstance());

    expect($this->registry->available())->toBe(['openai', 'anthropic']);
});

it('clears cache when re-registering a key', function () {
    $this->registry->register('test', fn ($app, $config) => createTestDriverInstance());
    $first = $this->registry->resolve('test');

    $this->registry->register('test', fn ($app, $config) => createTestDriverInstance());
    $second = $this->registry->resolve('test');

    expect($first)->not->toBe($second);
});

it('passes app and config to factory closures', function () {
    $receivedConfig = null;

    $this->registry->register('openai', function ($app, $config) use (&$receivedConfig) {
        $receivedConfig = $config;

        return createTestDriverInstance();
    });

    $this->registry->resolve('openai');

    expect($receivedConfig)->toBeArray();
    expect($receivedConfig)->toHaveKey('api_key');
});
