<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Pending\Concerns\HasRequestConfig;
use Atlasphp\Atlas\RequestConfig;

function createRequestConfigHelper(): object
{
    return new class
    {
        use HasRequestConfig;

        public function getRequestConfig(): ?RequestConfig
        {
            return $this->requestConfig;
        }

        public function getResolved(): RequestConfig
        {
            return $this->resolveRequestConfig();
        }
    };
}

it('withTimeout returns static for chaining', function () {
    $helper = createRequestConfigHelper();

    expect($helper->withTimeout(120))->toBe($helper);
});

it('withTimeout sets timeout on request config', function () {
    $helper = createRequestConfigHelper();
    $helper->withTimeout(120);

    expect($helper->getRequestConfig()->timeout)->toBe(120);
});

it('withRetry sets rate limit and errors', function () {
    $helper = createRequestConfigHelper();
    $helper->withRetry(rateLimit: 5, errors: 3);

    $config = $helper->getRequestConfig();
    expect($config->rateLimit)->toBe(5);
    expect($config->errors)->toBe(3);
});

it('withRetry preserves unspecified values', function () {
    config(['atlas.retry.rate_limit' => 3, 'atlas.retry.errors' => 2]);
    AtlasConfig::refresh();

    $helper = createRequestConfigHelper();
    $helper->withRetry(rateLimit: 10);

    $config = $helper->getRequestConfig();
    expect($config->rateLimit)->toBe(10);
    expect($config->errors)->toBe(2);
});

it('withoutRetry disables all retry', function () {
    $helper = createRequestConfigHelper();
    $helper->withoutRetry();

    $config = $helper->getRequestConfig();
    expect($config->rateLimit)->toBe(0);
    expect($config->errors)->toBe(0);
    expect($config->retryEnabled())->toBeFalse();
});

it('requestConfigPayload is null when no override was set', function () {
    expect(createRequestConfigHelper()->requestConfigPayload())->toBeNull();
});

it('serializes and restores an override across a queue payload round-trip', function () {
    $source = createRequestConfigHelper();
    $source->withRetry(rateLimit: 8, errors: 5)->withTimeout(99);

    $payload = $source->requestConfigPayload();
    expect($payload)->not->toBeNull();

    $restored = createRequestConfigHelper();
    $restored->applyRequestConfigPayload($payload);

    $config = $restored->getRequestConfig();
    expect($config->rateLimit)->toBe(8);
    expect($config->errors)->toBe(5);
    expect($config->timeout)->toBe(99);
    expect($config->timeoutExplicit)->toBeTrue();
});

it('applyRequestConfigPayload with null leaves config untouched (falls back to defaults)', function () {
    $helper = createRequestConfigHelper();
    $helper->applyRequestConfigPayload(null);

    expect($helper->getRequestConfig())->toBeNull();
});

it('resolveRequestConfig defaults from AtlasConfig', function () {
    config([
        'atlas.retry.timeout' => 45,
        'atlas.retry.rate_limit' => 7,
        'atlas.retry.errors' => 4,
    ]);
    AtlasConfig::refresh();

    $helper = createRequestConfigHelper();
    $config = $helper->getResolved();

    expect($config->timeout)->toBe(45);
    expect($config->rateLimit)->toBe(7);
    expect($config->errors)->toBe(4);
});
