<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Google\GoogleDriver;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;

it('returns google as the driver name', function () {
    $driver = new GoogleDriver(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://generativelanguage.googleapis.com']),
        http: app(HttpClient::class),
    );

    expect($driver->name())->toBe('google');
});

it('returns correct capabilities matrix', function () {
    $driver = new GoogleDriver(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://generativelanguage.googleapis.com']),
        http: app(HttpClient::class),
    );

    $capabilities = $driver->capabilities();

    expect($capabilities->text)->toBeTrue();
    expect($capabilities->stream)->toBeTrue();
    expect($capabilities->structured)->toBeTrue();
    expect($capabilities->image)->toBeTrue();
    expect($capabilities->imageToText)->toBeFalse();
    expect($capabilities->audio)->toBeFalse();
    expect($capabilities->audioToText)->toBeFalse();
    expect($capabilities->video)->toBeFalse();
    expect($capabilities->videoToText)->toBeFalse();
    expect($capabilities->embed)->toBeTrue();
    expect($capabilities->moderate)->toBeFalse();
    expect($capabilities->rerank)->toBeFalse();
    expect($capabilities->vision)->toBeTrue();
    expect($capabilities->toolCalling)->toBeTrue();
    expect($capabilities->providerTools)->toBeTrue();
    expect($capabilities->models)->toBeTrue();
    expect($capabilities->voices)->toBeFalse();
});
