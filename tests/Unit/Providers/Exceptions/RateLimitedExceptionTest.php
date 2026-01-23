<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Exceptions\ProviderException;
use Atlasphp\Atlas\Providers\Exceptions\RateLimitedException;
use Prism\Prism\Exceptions\PrismRateLimitedException;

test('it extends ProviderException', function () {
    $exception = new RateLimitedException('Rate limit exceeded');

    expect($exception)->toBeInstanceOf(ProviderException::class);
});

test('it stores rate limits and retry after', function () {
    $rateLimits = [['limit' => 100, 'remaining' => 0]];
    $retryAfter = 60;

    $exception = new RateLimitedException(
        message: 'Rate limit exceeded',
        rateLimits: $rateLimits,
        retryAfter: $retryAfter,
    );

    expect($exception->rateLimits())->toBe($rateLimits);
    expect($exception->retryAfter())->toBe($retryAfter);
    expect($exception->getMessage())->toBe('Rate limit exceeded');
});

test('it creates from Prism exception', function () {
    $rateLimits = [];
    $retryAfter = 30;

    $prismException = new PrismRateLimitedException($rateLimits, $retryAfter);
    $atlasException = RateLimitedException::fromPrism($prismException);

    expect($atlasException)->toBeInstanceOf(RateLimitedException::class);
    expect($atlasException->retryAfter())->toBe(30);
    expect($atlasException->rateLimits())->toBe([]);
    expect($atlasException->getPrevious())->toBe($prismException);
});

test('it handles null retry after', function () {
    $exception = new RateLimitedException(
        message: 'Rate limit exceeded',
        rateLimits: [],
        retryAfter: null,
    );

    expect($exception->retryAfter())->toBeNull();
});
