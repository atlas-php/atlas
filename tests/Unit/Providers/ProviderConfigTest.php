<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\ProviderConfig;

it('creates from a full config array', function () {
    $config = ProviderConfig::fromArray([
        'api_key' => 'sk-test',
        'url' => 'https://api.openai.com/v1',
        'organization' => 'org-123',
        'timeout' => 30,
        'reasoning_timeout' => 120,
        'media_timeout' => 60,
    ]);

    expect($config->apiKey)->toBe('sk-test');
    expect($config->baseUrl)->toBe('https://api.openai.com/v1');
    expect($config->organization)->toBe('org-123');
    expect($config->timeout)->toBe(30);
    expect($config->reasoningTimeout)->toBe(120);
    expect($config->mediaTimeout)->toBe(60);
});

it('uses default timeout values when not provided', function () {
    $config = ProviderConfig::fromArray([
        'api_key' => 'sk-test',
        'url' => 'https://api.test.com',
    ]);

    expect($config->timeout)->toBe(60);
    expect($config->reasoningTimeout)->toBe(300);
    expect($config->mediaTimeout)->toBe(120);
});

it('captures extra keys', function () {
    $config = ProviderConfig::fromArray([
        'api_key' => 'sk-test',
        'url' => 'https://api.test.com',
        'version' => '2024-10-22',
        'custom_option' => true,
    ]);

    expect($config->extra)->toBe([
        'version' => '2024-10-22',
        'custom_option' => true,
    ]);
});

it('accepts base_url with preference over url', function () {
    $config = ProviderConfig::fromArray([
        'api_key' => 'sk-test',
        'base_url' => 'https://base.test.com/v1',
        'url' => 'https://url.test.com/v1',
    ]);

    expect($config->baseUrl)->toBe('https://base.test.com/v1');
});

it('falls back to url when base_url is not set', function () {
    $config = ProviderConfig::fromArray([
        'api_key' => 'sk-test',
        'url' => 'https://url.test.com/v1',
    ]);

    expect($config->baseUrl)->toBe('https://url.test.com/v1');
});

it('extracts capability overrides from config', function () {
    $config = ProviderConfig::fromArray([
        'api_key' => 'sk-test',
        'url' => 'https://api.test.com',
        'capabilities' => ['structured' => false, 'vision' => false],
    ]);

    expect($config->capabilityOverrides)->toBe(['structured' => false, 'vision' => false]);
});

it('excludes driver and capabilities from extra', function () {
    $config = ProviderConfig::fromArray([
        'api_key' => 'sk-test',
        'url' => 'https://api.test.com',
        'driver' => 'chat_completions',
        'capabilities' => ['structured' => false],
        'version' => '1.0',
    ]);

    expect($config->extra)->toBe(['version' => '1.0']);
    expect($config->extra)->not->toHaveKey('driver');
    expect($config->extra)->not->toHaveKey('capabilities');
});
