<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Testing\Support\FakeResponseSequence;

test('it returns responses in sequence', function () {
    $response1 = AgentResponse::text('First');
    $response2 = AgentResponse::text('Second');

    $sequence = new FakeResponseSequence([$response1, $response2]);

    expect($sequence->next())->toBe($response1);
    expect($sequence->next())->toBe($response2);
});

test('it reports hasMore correctly', function () {
    $response = AgentResponse::text('Test');
    $sequence = new FakeResponseSequence([$response]);

    expect($sequence->hasMore())->toBeTrue();
    $sequence->next();
    expect($sequence->hasMore())->toBeFalse();
});

test('it returns empty response when sequence exhausted', function () {
    $sequence = new FakeResponseSequence([]);

    $result = $sequence->next();

    expect($result)->toBeInstanceOf(AgentResponse::class);
    expect($result->text)->toBeNull();
});

test('it returns whenEmpty response after exhaustion', function () {
    $fallback = AgentResponse::text('Fallback');
    $sequence = new FakeResponseSequence([]);
    $sequence->whenEmpty($fallback);

    $result = $sequence->next();

    expect($result)->toBe($fallback);
});

test('it pushes new responses', function () {
    $sequence = new FakeResponseSequence;
    $response = AgentResponse::text('Pushed');

    $sequence->push($response);

    expect($sequence->hasMore())->toBeTrue();
    expect($sequence->next())->toBe($response);
});

test('it resets to beginning', function () {
    $response = AgentResponse::text('Test');
    $sequence = new FakeResponseSequence([$response]);

    $sequence->next();
    expect($sequence->hasMore())->toBeFalse();

    $sequence->reset();
    expect($sequence->hasMore())->toBeTrue();
    expect($sequence->next())->toBe($response);
});

test('it reports isEmpty correctly', function () {
    $empty = new FakeResponseSequence;
    $notEmpty = new FakeResponseSequence([AgentResponse::text('Test')]);

    expect($empty->isEmpty())->toBeTrue();
    expect($notEmpty->isEmpty())->toBeFalse();
});

test('it counts responses', function () {
    $sequence = new FakeResponseSequence([
        AgentResponse::text('One'),
        AgentResponse::text('Two'),
        AgentResponse::text('Three'),
    ]);

    expect($sequence->count())->toBe(3);
});

test('it handles throwables', function () {
    $exception = new RuntimeException('Test error');
    $sequence = new FakeResponseSequence([$exception]);

    $result = $sequence->next();

    expect($result)->toBe($exception);
});
