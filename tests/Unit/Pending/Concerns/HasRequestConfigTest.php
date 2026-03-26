<?php

declare(strict_types=1);

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

it('resolveRequestConfig defaults from AtlasConfig', function () {
    config([
        'atlas.retry.timeout' => 45,
        'atlas.retry.rate_limit' => 7,
        'atlas.retry.errors' => 4,
    ]);

    $helper = createRequestConfigHelper();
    $config = $helper->getResolved();

    expect($config->timeout)->toBe(45);
    expect($config->rateLimit)->toBe(7);
    expect($config->errors)->toBe(4);
});
