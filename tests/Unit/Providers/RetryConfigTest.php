<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Support\RetryConfig;

test('it creates config with times and defaults', function () {
    $config = new RetryConfig(3);

    expect($config->times)->toBe(3);
    expect($config->sleepMilliseconds)->toBe(0);
    expect($config->when)->toBeNull();
    expect($config->throw)->toBeTrue();
});

test('it creates config with all parameters', function () {
    $when = fn () => true;
    $config = new RetryConfig(3, 1000, $when, false);

    expect($config->times)->toBe(3);
    expect($config->sleepMilliseconds)->toBe(1000);
    expect($config->when)->toBe($when);
    expect($config->throw)->toBeFalse();
});

test('it creates config with array of delays', function () {
    $config = new RetryConfig([100, 200, 300]);

    expect($config->times)->toBe([100, 200, 300]);
    expect($config->isEnabled())->toBeTrue();
});

test('it creates config with closure for sleep', function () {
    $sleep = fn (int $attempt): int => $attempt * 100;
    $config = new RetryConfig(3, $sleep);

    expect($config->sleepMilliseconds)->toBe($sleep);
});

test('none factory creates disabled config', function () {
    $config = RetryConfig::none();

    expect($config->times)->toBe(0);
    expect($config->isEnabled())->toBeFalse();
});

test('exponential factory creates backoff config', function () {
    $config = RetryConfig::exponential(3, 100);

    expect($config->times)->toBe(3);
    expect($config->sleepMilliseconds)->toBeInstanceOf(Closure::class);
    expect($config->isEnabled())->toBeTrue();

    // Test the closure generates exponential delays
    $closure = $config->sleepMilliseconds;
    expect($closure(1))->toBe(100);  // 100 * 2^0 = 100
    expect($closure(2))->toBe(200);  // 100 * 2^1 = 200
    expect($closure(3))->toBe(400);  // 100 * 2^2 = 400
});

test('exponential factory uses default base delay', function () {
    $config = RetryConfig::exponential(3);

    $closure = $config->sleepMilliseconds;
    expect($closure(1))->toBe(100);  // default 100ms base
});

test('fixed factory creates fixed delay config', function () {
    $config = RetryConfig::fixed(3, 1000);

    expect($config->times)->toBe(3);
    expect($config->sleepMilliseconds)->toBe(1000);
    expect($config->isEnabled())->toBeTrue();
});

test('toArray returns correct structure', function () {
    $when = fn () => true;
    $config = new RetryConfig(3, 1000, $when, false);

    $array = $config->toArray();

    expect($array)->toBe([3, 1000, $when, false]);
});

test('isEnabled returns true for positive times', function () {
    $config = new RetryConfig(3);

    expect($config->isEnabled())->toBeTrue();
});

test('isEnabled returns false for zero times', function () {
    $config = new RetryConfig(0);

    expect($config->isEnabled())->toBeFalse();
});

test('isEnabled returns true for non-empty array', function () {
    $config = new RetryConfig([100, 200]);

    expect($config->isEnabled())->toBeTrue();
});

test('isEnabled returns false for empty array', function () {
    $config = new RetryConfig([]);

    expect($config->isEnabled())->toBeFalse();
});
