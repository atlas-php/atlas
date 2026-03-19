<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\Xai\XaiDriver;
use Illuminate\Support\Facades\Http;

it('returns xai as name', function () {
    $driver = new XaiDriver(
        config: ProviderConfig::fromArray(['api_key' => 'test', 'url' => 'https://api.x.ai/v1']),
        http: app(HttpClient::class),
    );

    expect($driver->name())->toBe('xai');
});

it('reports correct capabilities', function () {
    $driver = new XaiDriver(
        config: ProviderConfig::fromArray(['api_key' => 'test', 'url' => 'https://api.x.ai/v1']),
        http: app(HttpClient::class),
    );

    $cap = $driver->capabilities();

    expect($cap->supports('text'))->toBeTrue();
    expect($cap->supports('stream'))->toBeTrue();
    expect($cap->supports('structured'))->toBeTrue();
    expect($cap->supports('image'))->toBeTrue();
    expect($cap->supports('imageToText'))->toBeFalse();
    expect($cap->supports('audio'))->toBeTrue();
    expect($cap->supports('audioToText'))->toBeFalse();
    expect($cap->supports('video'))->toBeTrue();
    expect($cap->supports('videoToText'))->toBeFalse();
    expect($cap->supports('embed'))->toBeFalse();
    expect($cap->supports('moderate'))->toBeFalse();
    expect($cap->supports('vision'))->toBeTrue();
    expect($cap->supports('toolCalling'))->toBeTrue();
    expect($cap->supports('providerTools'))->toBeTrue();
    expect($cap->supports('models'))->toBeTrue();
    expect($cap->supports('voices'))->toBeTrue();
});

it('lists models via provider handler', function () {
    Http::fake([
        'api.x.ai/v1/models' => Http::response([
            'data' => [
                ['id' => 'grok-3', 'object' => 'model'],
                ['id' => 'grok-3-mini', 'object' => 'model'],
            ],
        ]),
    ]);

    $driver = new XaiDriver(
        config: ProviderConfig::fromArray(['api_key' => 'test', 'url' => 'https://api.x.ai/v1']),
        http: app(HttpClient::class),
    );

    $models = $driver->models();

    expect($models->models)->toContain('grok-3');
    expect($models->models)->toContain('grok-3-mini');
});

it('lists voices via provider handler', function () {
    $driver = new XaiDriver(
        config: ProviderConfig::fromArray(['api_key' => 'test', 'url' => 'https://api.x.ai/v1']),
        http: app(HttpClient::class),
    );

    $voices = $driver->voices();

    expect($voices->voices)->toHaveCount(5);
    expect($voices->voices)->toContain('ara');
    expect($voices->voices)->toContain('eve');
    expect($voices->voices)->toContain('leo');
    expect($voices->voices)->toContain('rex');
    expect($voices->voices)->toContain('sal');
});
