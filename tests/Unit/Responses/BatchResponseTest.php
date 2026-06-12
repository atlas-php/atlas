<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\BatchResultStatus;
use Atlasphp\Atlas\Enums\BatchStatus;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Responses\BatchResponse;
use Atlasphp\Atlas\Responses\BatchResult;
use Atlasphp\Atlas\Responses\RequestCounts;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;

it('exposes the batch id, status and counts', function () {
    $response = new BatchResponse(
        batchId: 'batch_123',
        status: BatchStatus::InProgress,
        counts: new RequestCounts(total: 10, processing: 10),
        inputFileId: 'file_in',
    );

    expect($response->batchId)->toBe('batch_123');
    expect($response->status)->toBe(BatchStatus::InProgress);
    expect($response->counts->total)->toBe(10);
    expect($response->inputFileId)->toBe('file_in');
    expect($response->isTerminal())->toBeFalse();
    expect($response->isSuccessful())->toBeFalse();
});

it('reports terminal and successful from its status', function () {
    $completed = new BatchResponse('b', BatchStatus::Completed, new RequestCounts);
    $failed = new BatchResponse('b', BatchStatus::Failed, new RequestCounts);

    expect($completed->isTerminal())->toBeTrue();
    expect($completed->isSuccessful())->toBeTrue();
    expect($failed->isTerminal())->toBeTrue();
    expect($failed->isSuccessful())->toBeFalse();
});

it('round-trips request counts through an array', function () {
    $counts = new RequestCounts(total: 5, succeeded: 3, failed: 1, processing: 1);

    $array = $counts->toArray();
    expect($array)->toBe(['total' => 5, 'succeeded' => 3, 'failed' => 1, 'processing' => 1]);

    $restored = RequestCounts::fromArray($array);
    expect($restored->total)->toBe(5);
    expect($restored->succeeded)->toBe(3);
    expect($restored->failed)->toBe(1);
    expect($restored->processing)->toBe(1);
});

it('defaults request counts to zero from a null array', function () {
    $counts = RequestCounts::fromArray(null);

    expect($counts->total)->toBe(0);
    expect($counts->succeeded)->toBe(0);
});

it('holds a parsed response on a successful result', function () {
    $text = new TextResponse('A dog on a beach.', new Usage(10, 5), FinishReason::Stop);
    $result = new BatchResult('img-1', BatchResultStatus::Succeeded, response: $text, usage: new Usage(10, 5));

    expect($result->customId)->toBe('img-1');
    expect($result->succeeded())->toBeTrue();
    expect($result->response)->toBe($text);
    expect($result->error)->toBeNull();
});

it('holds an error on a failed result', function () {
    $error = new RuntimeException('image unreadable');
    $result = new BatchResult('img-2', BatchResultStatus::Errored, error: $error);

    expect($result->succeeded())->toBeFalse();
    expect($result->response)->toBeNull();
    expect($result->error)->toBe($error);
});
