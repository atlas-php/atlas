<?php

declare(strict_types=1);

use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Exceptions\ProviderNotFoundException;
use Atlasphp\Atlas\Providers\ChatCompletions\ChatCompletionsDriver;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\ResponsesDriver;

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

    expect($this->registry->available())->toContain('openai');
    expect($this->registry->available())->toContain('anthropic');
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

it('resolves chat_completions driver from config', function () {
    config()->set('atlas.providers.ollama', [
        'driver' => 'chat_completions',
        'api_key' => 'ollama',
        'base_url' => 'http://localhost:11434/v1',
    ]);

    $driver = $this->registry->resolve('ollama');

    expect($driver)->toBeInstanceOf(ChatCompletionsDriver::class);
    expect($driver->name())->toBe('chat_completions');
});

it('resolves responses driver from config', function () {
    config()->set('atlas.providers.ollama-responses', [
        'driver' => 'responses',
        'api_key' => 'ollama',
        'base_url' => 'http://localhost:11434/v1',
    ]);

    $driver = $this->registry->resolve('ollama-responses');

    expect($driver)->toBeInstanceOf(ResponsesDriver::class);
    expect($driver->name())->toBe('responses');
});

it('resolves custom driver class from config', function () {
    config()->set('atlas.providers.custom', [
        'driver' => get_class(createTestDriverInstance()),
        'api_key' => 'test',
        'url' => 'https://custom.test.com',
    ]);

    $driver = $this->registry->resolve('custom');

    expect($driver)->toBeInstanceOf(Driver::class);
});

it('throws AtlasException for unknown driver string', function () {
    config()->set('atlas.providers.bad', [
        'driver' => 'not_a_real_driver',
        'api_key' => 'test',
    ]);

    $this->registry->resolve('bad');
})->throws(AtlasException::class, "Unknown driver 'not_a_real_driver' for provider 'bad'.");

it('caches config-resolved driver instances', function () {
    config()->set('atlas.providers.ollama', [
        'driver' => 'chat_completions',
        'api_key' => 'ollama',
        'base_url' => 'http://localhost:11434/v1',
    ]);

    $first = $this->registry->resolve('ollama');
    $second = $this->registry->resolve('ollama');

    expect($first)->toBe($second);
});
