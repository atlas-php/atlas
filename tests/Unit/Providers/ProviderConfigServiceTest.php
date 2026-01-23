<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Exceptions\ProviderException;
use Atlasphp\Atlas\Providers\Services\ProviderConfigService;
use Illuminate\Config\Repository;

beforeEach(function () {
    $this->config = new Repository([
        'atlas' => [
            'providers' => [
                'openai' => [
                    'api_key' => 'test-key',
                    'url' => 'https://api.openai.com/v1',
                    'timeout' => 60,
                ],
                'anthropic' => [
                    'api_key' => 'anthropic-key',
                    'version' => '2023-06-01',
                ],
            ],
            'chat' => [
                'provider' => 'openai',
                'model' => 'gpt-4o',
            ],
            'embedding' => [
                'provider' => 'openai',
                'model' => 'text-embedding-3-small',
                'dimensions' => 1536,
                'batch_size' => 100,
            ],
            'image' => [
                'provider' => 'openai',
                'model' => 'dall-e-3',
            ],
            'speech' => [
                'provider' => 'openai',
                'model' => 'tts-1',
                'transcription_model' => 'whisper-1',
            ],
        ],
    ]);

    $this->service = new ProviderConfigService($this->config);
});

test('it returns provider config', function () {
    $config = $this->service->getProviderConfig('openai');

    expect($config)->toBeArray();
    expect($config['api_key'])->toBe('test-key');
});

test('it throws when provider not configured', function () {
    expect(fn () => $this->service->getProviderConfig('unknown'))
        ->toThrow(ProviderException::class);
});

test('it returns all providers', function () {
    $providers = $this->service->getProviders();

    expect($providers)->toHaveKey('openai');
    expect($providers)->toHaveKey('anthropic');
});

test('it checks if provider exists', function () {
    expect($this->service->hasProvider('openai'))->toBeTrue();
    expect($this->service->hasProvider('unknown'))->toBeFalse();
});

test('it returns provider timeout', function () {
    $timeout = $this->service->getTimeout('openai');

    expect($timeout)->toBe(60);
});

test('it returns default timeout when not configured', function () {
    $timeout = $this->service->getTimeout('anthropic');

    expect($timeout)->toBe(30);
});

test('it returns chat config', function () {
    $config = $this->service->getChatConfig();

    expect($config)->toBe([
        'provider' => 'openai',
        'model' => 'gpt-4o',
    ]);
});

test('it returns embedding config', function () {
    $config = $this->service->getEmbeddingConfig();

    expect($config)->toBe([
        'provider' => 'openai',
        'model' => 'text-embedding-3-small',
        'dimensions' => 1536,
        'batch_size' => 100,
    ]);
});

test('it returns image config', function () {
    $config = $this->service->getImageConfig();

    expect($config)->toBe([
        'provider' => 'openai',
        'model' => 'dall-e-3',
    ]);
});

test('it returns speech config', function () {
    $config = $this->service->getSpeechConfig();

    expect($config)->toBe([
        'provider' => 'openai',
        'model' => 'tts-1',
        'transcription_model' => 'whisper-1',
    ]);
});

test('it returns defaults for missing atlas config', function () {
    $config = new Repository([]);
    $service = new ProviderConfigService($config);

    expect($service->getChatConfig())->toBe([
        'provider' => 'openai',
        'model' => 'gpt-4o',
    ]);
    expect($service->getEmbeddingConfig())->toBe([
        'provider' => 'openai',
        'model' => 'text-embedding-3-small',
        'dimensions' => 1536,
        'batch_size' => 100,
    ]);
    expect($service->getImageConfig())->toBe([
        'provider' => 'openai',
        'model' => 'dall-e-3',
    ]);
    expect($service->getSpeechConfig())->toBe([
        'provider' => 'openai',
        'model' => 'tts-1',
        'transcription_model' => 'whisper-1',
    ]);
});

test('it throws when provider config is not an array', function () {
    $config = new Repository([
        'atlas' => [
            'providers' => [
                'invalid-provider' => 'not-an-array',
            ],
        ],
    ]);
    $service = new ProviderConfigService($config);

    expect(fn () => $service->getProviderConfig('invalid-provider'))
        ->toThrow(ProviderException::class, "Invalid configuration 'provider' for provider 'invalid-provider': Configuration must be an array.");
});

// ===========================================
// RETRY CONFIG TESTS
// ===========================================

test('getRetryConfig returns null when retry is disabled', function () {
    $config = new Repository([
        'atlas' => [
            'retry' => [
                'enabled' => false,
            ],
        ],
    ]);
    $service = new ProviderConfigService($config);

    expect($service->getRetryConfig())->toBeNull();
});

test('getRetryConfig returns null when retry config is missing', function () {
    $config = new Repository([]);
    $service = new ProviderConfigService($config);

    expect($service->getRetryConfig())->toBeNull();
});

test('getRetryConfig returns fixed delay config when enabled with fixed strategy', function () {
    $config = new Repository([
        'atlas' => [
            'retry' => [
                'enabled' => true,
                'times' => 3,
                'strategy' => 'fixed',
                'delay_ms' => 1000,
            ],
        ],
    ]);
    $service = new ProviderConfigService($config);

    $retry = $service->getRetryConfig();

    expect($retry)->toBeArray();
    expect($retry[0])->toBe(3);       // times
    expect($retry[1])->toBe(1000);    // sleepMilliseconds (fixed)
    expect($retry[2])->toBeNull();    // when
    expect($retry[3])->toBeTrue();    // throw
});

test('getRetryConfig returns exponential delay config when enabled with exponential strategy', function () {
    $config = new Repository([
        'atlas' => [
            'retry' => [
                'enabled' => true,
                'times' => 3,
                'strategy' => 'exponential',
                'delay_ms' => 100,
            ],
        ],
    ]);
    $service = new ProviderConfigService($config);

    $retry = $service->getRetryConfig();

    expect($retry)->toBeArray();
    expect($retry[0])->toBe(3);              // times
    expect($retry[1])->toBeInstanceOf(Closure::class);  // sleepMilliseconds (closure)
    expect($retry[2])->toBeNull();           // when
    expect($retry[3])->toBeTrue();           // throw

    // Test the exponential backoff closure
    $sleep = $retry[1];
    expect($sleep(1))->toBe(100);   // 100 * 2^0 = 100
    expect($sleep(2))->toBe(200);   // 100 * 2^1 = 200
    expect($sleep(3))->toBe(400);   // 100 * 2^2 = 400
});

test('getRetryConfig uses default values when partially configured', function () {
    $config = new Repository([
        'atlas' => [
            'retry' => [
                'enabled' => true,
            ],
        ],
    ]);
    $service = new ProviderConfigService($config);

    $retry = $service->getRetryConfig();

    expect($retry)->toBeArray();
    expect($retry[0])->toBe(3);      // default times
    expect($retry[1])->toBe(1000);   // default delay_ms with fixed strategy
    expect($retry[2])->toBeNull();   // when
    expect($retry[3])->toBeTrue();   // throw
});
