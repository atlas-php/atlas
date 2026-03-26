<?php

declare(strict_types=1);

use Atlasphp\Atlas\Exceptions\AuthenticationException;
use Atlasphp\Atlas\Exceptions\AuthorizationException;
use Atlasphp\Atlas\Exceptions\ProviderException;
use Atlasphp\Atlas\Exceptions\RateLimitException;
use Atlasphp\Atlas\Http\RetryDecider;
use Atlasphp\Atlas\RequestConfig;

function decider(): RetryDecider
{
    return new RetryDecider;
}

function configWithRetry(int $rateLimit = 3, int $errors = 2): RequestConfig
{
    return new RequestConfig(60, $rateLimit, $errors);
}

// ─── shouldRetry ──────────────────────────────────────────────

it('retries rate limit exceptions within limit', function () {
    $e = new RateLimitException('openai', 'gpt-4o', 5);

    expect(decider()->shouldRetry($e, configWithRetry(rateLimit: 3), attempt: 1))->toBeTrue();
    expect(decider()->shouldRetry($e, configWithRetry(rateLimit: 3), attempt: 3))->toBeTrue();
});

it('stops retrying rate limit when attempts exhausted', function () {
    $e = new RateLimitException('openai', 'gpt-4o', 5);

    expect(decider()->shouldRetry($e, configWithRetry(rateLimit: 3), attempt: 4))->toBeFalse();
});

it('does not retry rate limit when rate_limit is zero', function () {
    $e = new RateLimitException('openai', 'gpt-4o', 5);

    expect(decider()->shouldRetry($e, configWithRetry(rateLimit: 0), attempt: 1))->toBeFalse();
});

it('retries transient 500 errors within limit', function () {
    $e = new ProviderException('openai', 'gpt-4o', 500, 'Internal Server Error');

    expect(decider()->shouldRetry($e, configWithRetry(errors: 2), attempt: 1))->toBeTrue();
    expect(decider()->shouldRetry($e, configWithRetry(errors: 2), attempt: 2))->toBeTrue();
});

it('retries 502, 503, 504 errors', function () {
    foreach ([502, 503, 504] as $code) {
        $e = new ProviderException('openai', 'gpt-4o', $code, 'Error');
        expect(decider()->shouldRetry($e, configWithRetry(errors: 2), attempt: 1))->toBeTrue();
    }
});

it('retries connection failures (status 0)', function () {
    $e = new ProviderException('openai', 'gpt-4o', 0, 'Connection timed out');

    expect(decider()->shouldRetry($e, configWithRetry(errors: 2), attempt: 1))->toBeTrue();
});

it('stops retrying transient errors when attempts exhausted', function () {
    $e = new ProviderException('openai', 'gpt-4o', 500, 'Error');

    expect(decider()->shouldRetry($e, configWithRetry(errors: 2), attempt: 3))->toBeFalse();
});

it('does not retry transient errors when errors is zero', function () {
    $e = new ProviderException('openai', 'gpt-4o', 500, 'Error');

    expect(decider()->shouldRetry($e, configWithRetry(errors: 0), attempt: 1))->toBeFalse();
});

it('never retries 401 authentication errors', function () {
    $e = new AuthenticationException('openai');

    expect(decider()->shouldRetry($e, configWithRetry(), attempt: 1))->toBeFalse();
});

it('never retries 403 authorization errors', function () {
    $e = new AuthorizationException('openai', 'gpt-4o');

    expect(decider()->shouldRetry($e, configWithRetry(), attempt: 1))->toBeFalse();
});

it('never retries client errors (4xx other than 429)', function () {
    $e = new ProviderException('openai', 'gpt-4o', 400, 'Bad request');

    expect(decider()->shouldRetry($e, configWithRetry(), attempt: 1))->toBeFalse();
});

it('never retries unknown exceptions', function () {
    $e = new RuntimeException('Something unexpected');

    expect(decider()->shouldRetry($e, configWithRetry(), attempt: 1))->toBeFalse();
});

// ─── waitMicroseconds ─────────────────────────────────────────

it('uses Retry-After header for rate limits', function () {
    $e = new RateLimitException('openai', 'gpt-4o', retryAfter: 5);

    expect(decider()->waitMicroseconds($e, attempt: 1))->toBe(5_000_000);
});

it('caps rate limit wait at 60 seconds', function () {
    $e = new RateLimitException('openai', 'gpt-4o', retryAfter: 120);

    expect(decider()->waitMicroseconds($e, attempt: 1))->toBe(60_000_000);
});

it('defaults to 1 second when no Retry-After header', function () {
    $e = new RateLimitException('openai', 'gpt-4o', retryAfter: null);

    expect(decider()->waitMicroseconds($e, attempt: 1))->toBe(1_000_000);
});

it('uses exponential backoff for transient errors', function () {
    $e = new ProviderException('openai', 'gpt-4o', 500, 'Error');

    // Backoff is randomized (jitter), so we verify the cap increases with attempts
    $wait1 = decider()->waitMicroseconds($e, attempt: 1);
    expect($wait1)->toBeLessThanOrEqual(1_000 * 1_000); // max: 500ms * 2^1 = 1000ms

    $maxWait3 = 500 * (2 ** 3) * 1_000; // 4000ms in microseconds
    $wait3 = decider()->waitMicroseconds($e, attempt: 3);
    expect($wait3)->toBeLessThanOrEqual($maxWait3);
});

it('caps backoff at 10 seconds', function () {
    $e = new ProviderException('openai', 'gpt-4o', 500, 'Error');

    // At attempt 10, 500 * 2^10 = 512000ms which exceeds cap
    $wait = decider()->waitMicroseconds($e, attempt: 10);
    expect($wait)->toBeLessThanOrEqual(10_000 * 1_000); // 10 seconds in microseconds
});
