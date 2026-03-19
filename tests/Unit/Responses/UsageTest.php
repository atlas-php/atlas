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
