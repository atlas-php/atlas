<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\OpenAi\OpenAiDriver;
use Atlasphp\Atlas\Providers\ProviderConfig;

it('returns openai as name', function () {
    $driver = new OpenAiDriver(
        config: ProviderConfig::fromArray(['api_key' => 'test', 'url' => 'https://api.openai.com/v1']),
        http: app(HttpClient::class),
    );

    expect($driver->name())->toBe('openai');
});

it('reports correct capabilities', function () {
    $driver = new OpenAiDriver(
        config: ProviderConfig::fromArray(['api_key' => 'test', 'url' => 'https://api.openai.com/v1']),
        http: app(HttpClient::class),
    );

    $cap = $driver->capabilities();

    expect($cap->supports('text'))->toBeTrue();
    expect($cap->supports('stream'))->toBeTrue();
    expect($cap->supports('structured'))->toBeTrue();
    expect($cap->supports('image'))->toBeTrue();
    expect($cap->supports('imageToText'))->toBeFalse();
    expect($cap->supports('audio'))->toBeTrue();
    expect($cap->supports('audioToText'))->toBeTrue();
    expect($cap->supports('video'))->toBeFalse();
    expect($cap->supports('videoToText'))->toBeFalse();
    expect($cap->supports('embed'))->toBeTrue();
    expect($cap->supports('moderate'))->toBeTrue();
    expect($cap->supports('vision'))->toBeTrue();
    expect($cap->supports('toolCalling'))->toBeTrue();
    expect($cap->supports('providerTools'))->toBeTrue();
    expect($cap->supports('models'))->toBeTrue();
    expect($cap->supports('voices'))->toBeTrue();
});

it('lists models via provider handler', function () {
    Http::fake([
        'api.openai.com/v1/models' => Http::response([
            'data' => [
                ['id' => 'gpt-4o', 'object' => 'model'],
                ['id' => 'gpt-4o-mini', 'object' => 'model'],
            ],
        ]),
    ]);

    $driver = new OpenAiDriver(
        config: ProviderConfig::fromArray(['api_key' => 'test', 'url' => 'https://api.openai.com/v1']),
        http: app(HttpClient::class),
    );

    $models = $driver->models();

    expect($models->models)->toContain('gpt-4o');
    expect($models->models)->toContain('gpt-4o-mini');
});

it('lists voices via provider handler', function () {
    $driver = new OpenAiDriver(
        config: ProviderConfig::fromArray(['api_key' => 'test', 'url' => 'https://api.openai.com/v1']),
        http: app(HttpClient::class),
    );

    $voices = $driver->voices();

    expect($voices->voices)->toContain('alloy');
    expect($voices->voices)->toContain('nova');
    expect($voices->voices)->toContain('shimmer');
});
