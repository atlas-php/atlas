<?php

declare(strict_types=1);

use Atlasphp\Atlas\Responses\RerankResponse;
use Atlasphp\Atlas\Responses\RerankResult;

function makeRerankResponse(): RerankResponse
{
    return new RerankResponse([
        new RerankResult(2, 0.95, 'Third document'),
        new RerankResult(0, 0.80, 'First document'),
        new RerankResult(1, 0.40, 'Second document'),
    ]);
}

it('returns indexes in relevance order', function () {
    expect(makeRerankResponse()->indexes())->toBe([2, 0, 1]);
});

it('returns top result', function () {
    $top = makeRerankResponse()->top();

    expect($top)->not->toBeNull();
    expect($top->index)->toBe(2);
    expect($top->score)->toBe(0.95);
});

it('returns null for top when empty', function () {
    $response = new RerankResponse([]);

    expect($response->top())->toBeNull();
});

it('returns top N results', function () {
    $results = makeRerankResponse()->topN(2);

    expect($results)->toHaveCount(2);
    expect($results[0]->index)->toBe(2);
    expect($results[1]->index)->toBe(0);
});

it('filters results above score threshold', function () {
    $results = makeRerankResponse()->aboveScore(0.50);

    expect($results)->toHaveCount(2);
    expect($results[0]->score)->toBe(0.95);
    expect($results[1]->score)->toBe(0.80);
});

it('returns empty array when no results above threshold', function () {
    $results = makeRerankResponse()->aboveScore(0.99);

    expect($results)->toBe([]);
});

it('reorders original documents by relevance', function () {
    $docs = ['First document', 'Second document', 'Third document'];

    $reordered = makeRerankResponse()->reorder($docs);

    expect($reordered)->toBe(['Third document', 'First document', 'Second document']);
});

it('stores meta information', function () {
    $response = new RerankResponse([], ['id' => 'abc-123']);

    expect($response->meta)->toBe(['id' => 'abc-123']);
});
