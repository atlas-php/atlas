<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\RequestConfig;

it('creates from AtlasConfig defaults', function () {
    config([
        'atlas.retry.timeout' => 45,
        'atlas.retry.rate_limit' => 5,
        'atlas.retry.errors' => 3,
    ]);

    $atlasConfig = AtlasConfig::fromConfig();
    $requestConfig = RequestConfig::fromAtlasConfig($atlasConfig);

    expect($requestConfig->timeout)->toBe(45);
    expect($requestConfig->rateLimit)->toBe(5);
    expect($requestConfig->errors)->toBe(3);
});

it('withTimeout returns new instance with updated timeout', function () {
    $config = new RequestConfig(60, 3, 2);
    $updated = $config->withTimeout(120);

    expect($updated)->not->toBe($config);
    expect($updated->timeout)->toBe(120);
    expect($updated->rateLimit)->toBe(3);
    expect($updated->errors)->toBe(2);
});

it('withRetry overrides specified values only', function () {
    $config = new RequestConfig(60, 3, 2);

    $updated = $config->withRetry(rateLimit: 10);
    expect($updated->rateLimit)->toBe(10);
    expect($updated->errors)->toBe(2);

    $updated = $config->withRetry(errors: 5);
    expect($updated->rateLimit)->toBe(3);
    expect($updated->errors)->toBe(5);

    $updated = $config->withRetry(rateLimit: 7, errors: 4);
    expect($updated->rateLimit)->toBe(7);
    expect($updated->errors)->toBe(4);
});

it('withoutRetry disables all retry', function () {
    $config = new RequestConfig(60, 3, 2);
    $updated = $config->withoutRetry();

    expect($updated->timeout)->toBe(60);
    expect($updated->rateLimit)->toBe(0);
    expect($updated->errors)->toBe(0);
});

it('retryEnabled returns true when any retry is configured', function () {
    expect((new RequestConfig(60, 3, 0))->retryEnabled())->toBeTrue();
    expect((new RequestConfig(60, 0, 2))->retryEnabled())->toBeTrue();
    expect((new RequestConfig(60, 3, 2))->retryEnabled())->toBeTrue();
});

it('retryEnabled returns false when all retry is disabled', function () {
    expect((new RequestConfig(60, 0, 0))->retryEnabled())->toBeFalse();
});

it('is immutable across all overrides', function () {
    $original = new RequestConfig(60, 3, 2);

    $a = $original->withTimeout(120);
    $b = $original->withRetry(rateLimit: 10);
    $c = $original->withoutRetry();

    expect($original->timeout)->toBe(60);
    expect($original->rateLimit)->toBe(3);
    expect($original->errors)->toBe(2);
});
