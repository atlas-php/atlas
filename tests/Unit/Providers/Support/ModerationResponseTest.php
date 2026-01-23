<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Support\ModerationResponse;
use Atlasphp\Atlas\Providers\Support\ModerationResult;
use Prism\Prism\Moderation\Response as PrismResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\ModerationResult as PrismModerationResult;

test('it stores results and metadata', function () {
    $results = [
        new ModerationResult(true, ['violence' => true], ['violence' => 0.95]),
        new ModerationResult(false, ['violence' => false], ['violence' => 0.01]),
    ];

    $response = new ModerationResponse(
        results: $results,
        id: 'mod-123',
        model: 'omni-moderation-latest',
    );

    expect($response->results)->toBe($results);
    expect($response->id)->toBe('mod-123');
    expect($response->model)->toBe('omni-moderation-latest');
});

test('isFlagged returns true when any result is flagged', function () {
    $results = [
        new ModerationResult(false, ['violence' => false], ['violence' => 0.01]),
        new ModerationResult(true, ['violence' => true], ['violence' => 0.95]),
    ];

    $response = new ModerationResponse($results, 'mod-123', 'model');

    expect($response->isFlagged())->toBeTrue();
});

test('isFlagged returns false when no results are flagged', function () {
    $results = [
        new ModerationResult(false, ['violence' => false], ['violence' => 0.01]),
        new ModerationResult(false, ['hate' => false], ['hate' => 0.02]),
    ];

    $response = new ModerationResponse($results, 'mod-123', 'model');

    expect($response->isFlagged())->toBeFalse();
});

test('firstFlagged returns first flagged result', function () {
    $result1 = new ModerationResult(false, ['violence' => false], ['violence' => 0.01]);
    $result2 = new ModerationResult(true, ['violence' => true], ['violence' => 0.95]);
    $result3 = new ModerationResult(true, ['hate' => true], ['hate' => 0.85]);

    $response = new ModerationResponse([$result1, $result2, $result3], 'mod-123', 'model');

    expect($response->firstFlagged())->toBe($result2);
});

test('firstFlagged returns null when no results are flagged', function () {
    $results = [
        new ModerationResult(false, ['violence' => false], ['violence' => 0.01]),
    ];

    $response = new ModerationResponse($results, 'mod-123', 'model');

    expect($response->firstFlagged())->toBeNull();
});

test('flagged returns all flagged results', function () {
    $result1 = new ModerationResult(false, ['violence' => false], ['violence' => 0.01]);
    $result2 = new ModerationResult(true, ['violence' => true], ['violence' => 0.95]);
    $result3 = new ModerationResult(true, ['hate' => true], ['hate' => 0.85]);

    $response = new ModerationResponse([$result1, $result2, $result3], 'mod-123', 'model');

    expect($response->flagged())->toBe([$result2, $result3]);
});

test('flagged returns empty array when no results are flagged', function () {
    $results = [
        new ModerationResult(false, ['violence' => false], ['violence' => 0.01]),
    ];

    $response = new ModerationResponse($results, 'mod-123', 'model');

    expect($response->flagged())->toBe([]);
});

test('categories aggregates all categories with max flag status', function () {
    $results = [
        new ModerationResult(false, ['violence' => false, 'hate' => true], ['violence' => 0.01, 'hate' => 0.80]),
        new ModerationResult(true, ['violence' => true, 'sexual' => false], ['violence' => 0.95, 'sexual' => 0.02]),
    ];

    $response = new ModerationResponse($results, 'mod-123', 'model');

    $categories = $response->categories();

    expect($categories['violence'])->toBeTrue();
    expect($categories['hate'])->toBeTrue();
    expect($categories['sexual'])->toBeFalse();
});

test('categoryScores aggregates max scores across all results', function () {
    $results = [
        new ModerationResult(false, ['violence' => false, 'hate' => true], ['violence' => 0.01, 'hate' => 0.80]),
        new ModerationResult(true, ['violence' => true, 'hate' => false], ['violence' => 0.95, 'hate' => 0.50]),
    ];

    $response = new ModerationResponse($results, 'mod-123', 'model');

    $scores = $response->categoryScores();

    expect($scores['violence'])->toBe(0.95);
    expect($scores['hate'])->toBe(0.80);
});

test('fromPrismResponse creates instance from Prism response', function () {
    $prismResults = [
        new PrismModerationResult(true, ['violence' => true], ['violence' => 0.95]),
        new PrismModerationResult(false, ['violence' => false], ['violence' => 0.01]),
    ];

    $meta = new Meta(
        id: 'mod-123',
        model: 'omni-moderation-latest',
    );

    $prismResponse = new PrismResponse($prismResults, $meta);

    $response = ModerationResponse::fromPrismResponse($prismResponse);

    expect($response)->toBeInstanceOf(ModerationResponse::class);
    expect($response->id)->toBe('mod-123');
    expect($response->model)->toBe('omni-moderation-latest');
    expect($response->results)->toHaveCount(2);
    expect($response->results[0])->toBeInstanceOf(ModerationResult::class);
    expect($response->results[0]->flagged)->toBeTrue();
    expect($response->results[1]->flagged)->toBeFalse();
});
