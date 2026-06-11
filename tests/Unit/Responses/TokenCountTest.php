<?php

declare(strict_types=1);

use Atlasphp\Atlas\Responses\TokenCount;

it('exposes its fields', function () {
    $count = new TokenCount(
        inputTokens: 1234,
        estimated: false,
        provider: 'anthropic',
        model: 'claude-sonnet-4-5',
    );

    expect($count->inputTokens)->toBe(1234)
        ->and($count->estimated)->toBeFalse()
        ->and($count->provider)->toBe('anthropic')
        ->and($count->model)->toBe('claude-sonnet-4-5')
        ->and($count->breakdown)->toBe([]);
});

it('omits an empty breakdown from the array form', function () {
    $count = new TokenCount(10, true, 'xai', 'grok-3');

    expect($count->toArray())->toBe([
        'input_tokens' => 10,
        'estimated' => true,
        'provider' => 'xai',
        'model' => 'grok-3',
    ]);
});

it('includes a non-empty breakdown in the array form', function () {
    $count = new TokenCount(100, false, 'google', 'gemini-2.5-flash', ['cached_tokens' => 30]);

    expect($count->toArray())->toBe([
        'input_tokens' => 100,
        'estimated' => false,
        'provider' => 'google',
        'model' => 'gemini-2.5-flash',
        'breakdown' => ['cached_tokens' => 30],
    ]);
});
