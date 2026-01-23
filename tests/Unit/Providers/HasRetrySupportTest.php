<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Support\HasRetrySupport;

/**
 * Test class that uses the HasRetrySupport trait.
 */
class TestRetryClass
{
    use HasRetrySupport;

    /**
     * Expose the protected method for testing.
     */
    public function exposeGetRetryArray(): ?array
    {
        return $this->getRetryArray();
    }
}

test('withRetry returns a clone with config', function () {
    $instance = new TestRetryClass;
    $clone = $instance->withRetry(3, 1000);

    expect($clone)->not->toBe($instance);
    expect($clone)->toBeInstanceOf(TestRetryClass::class);
});

test('withRetry stores times and sleep', function () {
    $instance = new TestRetryClass;
    $clone = $instance->withRetry(3, 1000);

    $config = $clone->exposeGetRetryArray();

    expect($config)->toBeArray();
    expect($config[0])->toBe(3);
    expect($config[1])->toBe(1000);
    expect($config[2])->toBeNull();
    expect($config[3])->toBeTrue();
});

test('withRetry stores all parameters', function () {
    $when = fn () => true;
    $instance = new TestRetryClass;
    $clone = $instance->withRetry(5, 2000, $when, false);

    $config = $clone->exposeGetRetryArray();

    expect($config[0])->toBe(5);
    expect($config[1])->toBe(2000);
    expect($config[2])->toBe($when);
    expect($config[3])->toBeFalse();
});

test('withRetry accepts closure for sleep', function () {
    $sleep = fn (int $attempt): int => $attempt * 100;
    $instance = new TestRetryClass;
    $clone = $instance->withRetry(3, $sleep);

    $config = $clone->exposeGetRetryArray();

    expect($config[0])->toBe(3);
    expect($config[1])->toBe($sleep);
    expect($config[1](1))->toBe(100);
    expect($config[1](2))->toBe(200);
});

test('withRetry accepts array of delays for times', function () {
    $delays = [100, 200, 300];
    $instance = new TestRetryClass;
    $clone = $instance->withRetry($delays);

    $config = $clone->exposeGetRetryArray();

    expect($config[0])->toBe($delays);
});

test('getRetryArray returns null when no retry configured', function () {
    $instance = new TestRetryClass;

    expect($instance->exposeGetRetryArray())->toBeNull();
});

test('original instance is not modified by withRetry', function () {
    $instance = new TestRetryClass;
    $instance->withRetry(3, 1000);

    // Original should still have null config
    expect($instance->exposeGetRetryArray())->toBeNull();
});

test('chained withRetry calls update config', function () {
    $instance = new TestRetryClass;
    $clone1 = $instance->withRetry(3, 1000);
    $clone2 = $clone1->withRetry(5, 2000);

    expect($clone1->exposeGetRetryArray()[0])->toBe(3);
    expect($clone2->exposeGetRetryArray()[0])->toBe(5);
});
