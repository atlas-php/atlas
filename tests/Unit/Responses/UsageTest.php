<?php

declare(strict_types=1);

use Atlasphp\Atlas\Responses\Usage;

it('calculates total tokens', function () {
    $usage = new Usage(inputTokens: 100, outputTokens: 50);

    expect($usage->totalTokens())->toBe(150);
});

it('merges two usage objects', function () {
    $a = new Usage(inputTokens: 100, outputTokens: 50);
    $b = new Usage(inputTokens: 200, outputTokens: 75);

    $merged = $a->merge($b);

    expect($merged->inputTokens)->toBe(300);
    expect($merged->outputTokens)->toBe(125);
});

it('merges reasoning tokens when present', function () {
    $a = new Usage(100, 50, reasoningTokens: 10);
    $b = new Usage(200, 75, reasoningTokens: 20);

    $merged = $a->merge($b);

    expect($merged->reasoningTokens)->toBe(30);
});

it('keeps reasoning tokens null when both are null', function () {
    $a = new Usage(100, 50);
    $b = new Usage(200, 75);

    $merged = $a->merge($b);

    expect($merged->reasoningTokens)->toBeNull();
    expect($merged->cachedTokens)->toBeNull();
});

it('merges cached tokens when one is present', function () {
    $a = new Usage(100, 50, cachedTokens: 25);
    $b = new Usage(200, 75);

    $merged = $a->merge($b);

    expect($merged->cachedTokens)->toBe(25);
});

it('builds a zero usage from null', function () {
    $usage = Usage::fromArray(null);

    expect($usage->inputTokens)->toBe(0)
        ->and($usage->outputTokens)->toBe(0)
        ->and($usage->reasoningTokens)->toBeNull()
        ->and($usage->cachedTokens)->toBeNull()
        ->and($usage->cacheWriteTokens)->toBeNull()
        ->and($usage->totalTokens())->toBe(0);
});

it('builds a full usage from an array', function () {
    $usage = Usage::fromArray([
        'input_tokens' => 100,
        'output_tokens' => 50,
        'reasoning_tokens' => 10,
        'cached_tokens' => 25,
        'cache_write_tokens' => 5,
    ]);

    expect($usage->inputTokens)->toBe(100)
        ->and($usage->outputTokens)->toBe(50)
        ->and($usage->reasoningTokens)->toBe(10)
        ->and($usage->cachedTokens)->toBe(25)
        ->and($usage->cacheWriteTokens)->toBe(5);
});

it('defaults missing keys when building from a partial array', function () {
    $usage = Usage::fromArray(['input_tokens' => 100]);

    expect($usage->inputTokens)->toBe(100)
        ->and($usage->outputTokens)->toBe(0)
        ->and($usage->reasoningTokens)->toBeNull()
        ->and($usage->cachedTokens)->toBeNull()
        ->and($usage->cacheWriteTokens)->toBeNull();
});

it('builds a zero usage from an empty array', function () {
    $usage = Usage::fromArray([]);

    expect($usage->inputTokens)->toBe(0)
        ->and($usage->outputTokens)->toBe(0)
        ->and($usage->reasoningTokens)->toBeNull();
});

it('round-trips through toArray and fromArray', function () {
    $original = new Usage(100, 50, reasoningTokens: 10, cachedTokens: 25, cacheWriteTokens: 5);

    $restored = Usage::fromArray($original->toArray());

    expect($restored)->toEqual($original);
});

it('round-trips a usage with only the required fields', function () {
    $original = new Usage(100, 50);

    $restored = Usage::fromArray($original->toArray());

    expect($restored)->toEqual($original)
        ->and($restored->reasoningTokens)->toBeNull()
        ->and($restored->cachedTokens)->toBeNull()
        ->and($restored->cacheWriteTokens)->toBeNull();
});
