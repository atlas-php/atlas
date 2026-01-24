<?php

declare(strict_types=1);

use Atlasphp\Atlas\Testing\Support\FakeResponseSequence;
use Illuminate\Support\Collection;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Text\Response as PrismResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

test('emptyResponse returns valid PrismResponse', function () {
    $response = FakeResponseSequence::emptyResponse();

    expect($response)->toBeInstanceOf(PrismResponse::class);
    expect($response->text)->toBe('');
    expect($response->finishReason)->toBe(FinishReason::Stop);
    expect($response->toolCalls)->toBe([]);
    expect($response->usage->promptTokens)->toBe(0);
    expect($response->usage->completionTokens)->toBe(0);
});

test('push and next work with PrismResponse', function () {
    $response1 = new PrismResponse(
        steps: new Collection([]),
        text: 'First response',
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(10, 5),
        meta: new Meta('id-1', 'gpt-4'),
        messages: new Collection([]),
    );

    $response2 = new PrismResponse(
        steps: new Collection([]),
        text: 'Second response',
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(15, 10),
        meta: new Meta('id-2', 'gpt-4'),
        messages: new Collection([]),
    );

    $sequence = new FakeResponseSequence;
    $sequence->push($response1);
    $sequence->push($response2);

    expect($sequence->count())->toBe(2);
    expect($sequence->hasMore())->toBeTrue();

    $first = $sequence->next();
    expect($first)->toBe($response1);
    expect($first->text)->toBe('First response');

    $second = $sequence->next();
    expect($second)->toBe($response2);
    expect($second->text)->toBe('Second response');

    expect($sequence->hasMore())->toBeFalse();
});

test('whenEmpty returns fallback when sequence exhausted', function () {
    $fallback = new PrismResponse(
        steps: new Collection([]),
        text: 'Fallback response',
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(5, 3),
        meta: new Meta('fallback-id', 'gpt-4'),
        messages: new Collection([]),
    );

    $sequence = new FakeResponseSequence;
    $sequence->whenEmpty($fallback);

    // Sequence is empty, should return fallback
    $response = $sequence->next();
    expect($response)->toBe($fallback);
    expect($response->text)->toBe('Fallback response');
});

test('next returns emptyResponse when sequence exhausted and no fallback', function () {
    $sequence = new FakeResponseSequence;

    $response = $sequence->next();

    expect($response)->toBeInstanceOf(PrismResponse::class);
    expect($response->text)->toBe('');
});

test('reset rewinds sequence to beginning', function () {
    $response = new PrismResponse(
        steps: new Collection([]),
        text: 'Repeatable',
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(10, 5),
        meta: new Meta('id', 'gpt-4'),
        messages: new Collection([]),
    );

    $sequence = new FakeResponseSequence([$response]);

    $first = $sequence->next();
    expect($first->text)->toBe('Repeatable');
    expect($sequence->hasMore())->toBeFalse();

    $sequence->reset();
    expect($sequence->hasMore())->toBeTrue();

    $second = $sequence->next();
    expect($second->text)->toBe('Repeatable');
});

test('isEmpty returns correct value', function () {
    $sequence = new FakeResponseSequence;
    expect($sequence->isEmpty())->toBeTrue();

    $sequence->push(FakeResponseSequence::emptyResponse());
    expect($sequence->isEmpty())->toBeFalse();
});

test('push supports exception for error testing', function () {
    $exception = new RuntimeException('API Error');

    $sequence = new FakeResponseSequence;
    $sequence->push($exception);

    expect($sequence->count())->toBe(1);

    $result = $sequence->next();
    expect($result)->toBe($exception);
});
