<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Support\ModerationResult;
use Prism\Prism\ValueObjects\ModerationResult as PrismModerationResult;

test('it stores flagged status and categories', function () {
    $result = new ModerationResult(
        flagged: true,
        categories: ['violence' => true, 'hate' => false, 'sexual' => true],
        categoryScores: ['violence' => 0.95, 'hate' => 0.01, 'sexual' => 0.85],
    );

    expect($result->flagged)->toBeTrue();
    expect($result->categories)->toBe(['violence' => true, 'hate' => false, 'sexual' => true]);
    expect($result->categoryScores)->toBe(['violence' => 0.95, 'hate' => 0.01, 'sexual' => 0.85]);
});

test('flaggedCategories returns only flagged categories', function () {
    $result = new ModerationResult(
        flagged: true,
        categories: ['violence' => true, 'hate' => false, 'sexual' => true, 'harassment' => false],
        categoryScores: ['violence' => 0.95, 'hate' => 0.01, 'sexual' => 0.85, 'harassment' => 0.02],
    );

    expect($result->flaggedCategories())->toBe(['violence', 'sexual']);
});

test('flaggedCategories returns empty array when no categories flagged', function () {
    $result = new ModerationResult(
        flagged: false,
        categories: ['violence' => false, 'hate' => false],
        categoryScores: ['violence' => 0.01, 'hate' => 0.02],
    );

    expect($result->flaggedCategories())->toBe([]);
});

test('isCategoryFlagged returns true for flagged category', function () {
    $result = new ModerationResult(
        flagged: true,
        categories: ['violence' => true, 'hate' => false],
        categoryScores: ['violence' => 0.95, 'hate' => 0.01],
    );

    expect($result->isCategoryFlagged('violence'))->toBeTrue();
    expect($result->isCategoryFlagged('hate'))->toBeFalse();
});

test('isCategoryFlagged returns false for unknown category', function () {
    $result = new ModerationResult(
        flagged: true,
        categories: ['violence' => true],
        categoryScores: ['violence' => 0.95],
    );

    expect($result->isCategoryFlagged('unknown'))->toBeFalse();
});

test('getCategoryScore returns score for known category', function () {
    $result = new ModerationResult(
        flagged: true,
        categories: ['violence' => true, 'hate' => false],
        categoryScores: ['violence' => 0.95, 'hate' => 0.01],
    );

    expect($result->getCategoryScore('violence'))->toBe(0.95);
    expect($result->getCategoryScore('hate'))->toBe(0.01);
});

test('getCategoryScore returns null for unknown category', function () {
    $result = new ModerationResult(
        flagged: true,
        categories: ['violence' => true],
        categoryScores: ['violence' => 0.95],
    );

    expect($result->getCategoryScore('unknown'))->toBeNull();
});

test('fromPrismResult creates instance from Prism result', function () {
    $prismResult = new PrismModerationResult(
        flagged: true,
        categories: ['violence' => true, 'hate' => false],
        categoryScores: ['violence' => 0.95, 'hate' => 0.01],
    );

    $result = ModerationResult::fromPrismResult($prismResult);

    expect($result)->toBeInstanceOf(ModerationResult::class);
    expect($result->flagged)->toBeTrue();
    expect($result->categories)->toBe(['violence' => true, 'hate' => false]);
    expect($result->categoryScores)->toBe(['violence' => 0.95, 'hate' => 0.01]);
});
