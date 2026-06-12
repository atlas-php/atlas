<?php

declare(strict_types=1);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\BatchResultStatus;
use Atlasphp\Atlas\Enums\BatchStatus;
use Atlasphp\Atlas\Persistence\Models\BatchGroup;
use Atlasphp\Atlas\Persistence\Models\BatchJob;
use Atlasphp\Atlas\Persistence\Models\BatchResult;
use Atlasphp\Atlas\Responses\RequestCounts;

it('Atlas::batchGroup() creates a persisted group when persistence is enabled', function () {
    $group = Atlas::batchGroup('my-run');

    expect($group)->toBeInstanceOf(BatchGroup::class);
    expect(BatchGroup::find($group->id)?->label)->toBe('my-run');
});

it('BatchJob results() returns the job\'s result rows', function () {
    $job = BatchJob::create(['provider' => 'openai', 'modality' => 'text', 'batch_id' => 'b', 'status' => BatchStatus::Completed]);
    BatchResult::create(['batch_job_id' => $job->id, 'custom_id' => 'rec-1', 'status' => BatchResultStatus::Succeeded]);
    BatchResult::create(['batch_job_id' => $job->id, 'custom_id' => 'rec-2', 'status' => BatchResultStatus::Errored]);

    expect($job->results)->toHaveCount(2);
    expect($job->results->pluck('custom_id')->sort()->values()->all())->toBe(['rec-1', 'rec-2']);
});

it('BatchJob applyStatus updates the status and counts columns', function () {
    $job = BatchJob::create(['provider' => 'openai', 'modality' => 'text', 'batch_id' => 'b', 'status' => BatchStatus::Validating]);

    $job->applyStatus(BatchStatus::InProgress, new RequestCounts(total: 5, succeeded: 2, failed: 1, processing: 2));

    $fresh = $job->fresh();
    expect($fresh->status)->toBe(BatchStatus::InProgress);
    expect($fresh->total)->toBe(5);
    expect($fresh->succeeded)->toBe(2);
    expect($fresh->failed)->toBe(1);
    expect($fresh->processing)->toBe(2);
});

it('BatchResult batchJob() returns its parent job', function () {
    $job = BatchJob::create(['provider' => 'openai', 'modality' => 'text', 'batch_id' => 'b', 'status' => BatchStatus::Completed]);
    $result = BatchResult::create(['batch_job_id' => $job->id, 'custom_id' => 'a', 'status' => BatchResultStatus::Succeeded]);

    expect($result->batchJob)->not->toBeNull();
    expect($result->batchJob->id)->toBe($job->id);
});

it('BatchJob scopeForProvider filters by provider', function () {
    $openai = BatchJob::create(['provider' => 'openai', 'modality' => 'text', 'batch_id' => 'a', 'status' => BatchStatus::Completed]);
    BatchJob::create(['provider' => 'anthropic', 'modality' => 'text', 'batch_id' => 'b', 'status' => BatchStatus::Completed]);

    $ids = BatchJob::query()->forProvider('openai')->pluck('id')->all();

    expect($ids)->toBe([$openai->id]);
});
