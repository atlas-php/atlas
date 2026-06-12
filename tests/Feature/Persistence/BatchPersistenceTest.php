<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Batch\BatchService;
use Atlasphp\Atlas\Enums\BatchResultStatus;
use Atlasphp\Atlas\Enums\BatchStatus;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Events\BatchCompleted;
use Atlasphp\Atlas\Events\BatchFailed;
use Atlasphp\Atlas\Events\BatchGroupCompleted;
use Atlasphp\Atlas\Events\BatchSubmitted;
use Atlasphp\Atlas\Persistence\Models\BatchGroup;
use Atlasphp\Atlas\Persistence\Models\BatchJob;
use Atlasphp\Atlas\Persistence\Models\BatchResult;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Requests\Batch;
use Atlasphp\Atlas\Requests\BatchLine;
use Atlasphp\Atlas\Requests\EmbedRequest;
use Atlasphp\Atlas\Responses\BatchResponse;
use Atlasphp\Atlas\Responses\BatchResult as BatchResultData;
use Atlasphp\Atlas\Responses\EmbeddingsResponse;
use Atlasphp\Atlas\Responses\RequestCounts;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;
use Illuminate\Contracts\Events\Dispatcher;

function batchService(ProviderRegistryContract $registry): BatchService
{
    return new BatchService($registry, app(Dispatcher::class), app(AtlasConfig::class));
}

function embedBatchRequest(): Batch
{
    return new Batch('openai', Modality::Embed, [
        new BatchLine('a', new EmbedRequest('text-embedding-3-small', 'one')),
    ]);
}

it('submits and persists a tracked batch job', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('batch')->once()
        ->andReturn(new BatchResponse('batch_1', BatchStatus::Validating, new RequestCounts(total: 1, processing: 1), inputFileId: 'file_in'));

    $registry = Mockery::mock(ProviderRegistryContract::class);
    $registry->shouldReceive('resolve')->with('openai')->andReturn($driver);

    $job = batchService($registry)->submitAndTrack(embedBatchRequest());

    expect($job)->toBeInstanceOf(BatchJob::class);
    expect($job->batch_id)->toBe('batch_1');
    expect($job->provider)->toBe('openai');
    expect($job->modality)->toBe('embed');
    expect($job->status)->toBe(BatchStatus::Validating);
    expect($job->total)->toBe(1);
    expect($job->input_file_id)->toBe('file_in');
    expect($job->submitted_at)->not->toBeNull();
});

it('hydrates results, rolls up usage, and fires BatchCompleted on sync', function () {
    Event::fake([BatchCompleted::class]);

    $job = BatchJob::create([
        'provider' => 'openai', 'modality' => 'embed', 'batch_id' => 'batch_1', 'status' => BatchStatus::InProgress,
    ]);

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('batchStatus')->once()->with('batch_1')
        ->andReturn(new BatchResponse('batch_1', BatchStatus::Completed, new RequestCounts(total: 2, succeeded: 2)));
    $driver->shouldReceive('batchResults')->once()->with('batch_1')->andReturn([
        new BatchResultData('a', BatchResultStatus::Succeeded, response: new EmbeddingsResponse([[0.1]], new Usage(3, 0)), usage: new Usage(3, 0)),
        new BatchResultData('b', BatchResultStatus::Succeeded, response: new EmbeddingsResponse([[0.2]], new Usage(4, 0)), usage: new Usage(4, 0)),
    ]);

    $registry = Mockery::mock(ProviderRegistryContract::class);
    $registry->shouldReceive('resolve')->with('openai')->andReturn($driver);

    $synced = batchService($registry)->syncFromProvider($job);

    expect($synced->status)->toBe(BatchStatus::Completed);
    expect($synced->completed_at)->not->toBeNull();
    expect($synced->usage)->toBe(['input_tokens' => 7, 'output_tokens' => 0]);

    $results = BatchResult::where('batch_job_id', $job->id)->orderBy('custom_id')->get();
    expect($results)->toHaveCount(2);
    expect($results[0]->custom_id)->toBe('a');
    expect($results[0]->status)->toBe(BatchResultStatus::Succeeded);
    expect($results[0]->response)->toBe(['embedding' => [0.1]]);

    Event::assertDispatched(BatchCompleted::class);
});

it('stores text responses with finish reason', function () {
    $job = BatchJob::create(['provider' => 'openai', 'modality' => 'text', 'batch_id' => 'b', 'status' => BatchStatus::InProgress]);

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('batchStatus')->andReturn(new BatchResponse('b', BatchStatus::Completed, new RequestCounts(total: 1, succeeded: 1)));
    $driver->shouldReceive('batchResults')->andReturn([
        new BatchResultData('img-1', BatchResultStatus::Succeeded, response: new TextResponse('A dog.', new Usage(5, 2), FinishReason::Stop), usage: new Usage(5, 2)),
    ]);

    $registry = Mockery::mock(ProviderRegistryContract::class);
    $registry->shouldReceive('resolve')->andReturn($driver);

    batchService($registry)->syncFromProvider($job);

    $result = BatchResult::where('batch_job_id', $job->id)->first();
    expect($result->response)->toBe(['text' => 'A dog.', 'finish_reason' => 'stop']);
});

it('marks the job failed and fires BatchFailed on a terminal failure', function () {
    Event::fake([BatchFailed::class]);

    $job = BatchJob::create(['provider' => 'openai', 'modality' => 'embed', 'batch_id' => 'b', 'status' => BatchStatus::InProgress]);

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('batchStatus')->andReturn(new BatchResponse('b', BatchStatus::Expired, new RequestCounts(total: 1)));

    $registry = Mockery::mock(ProviderRegistryContract::class);
    $registry->shouldReceive('resolve')->andReturn($driver);

    $synced = batchService($registry)->syncFromProvider($job);

    expect($synced->status)->toBe(BatchStatus::Expired);
    Event::assertDispatched(BatchFailed::class);
});

it('keeps a successful job open and preserves counts when results are not yet available', function () {
    // Provider flips to success but inline results aren't ready yet (the Gemini
    // race). The job must stay non-terminal, store no results, and NOT have its
    // counts zeroed by the transient empty response.
    $job = BatchJob::create(['provider' => 'google', 'modality' => 'text', 'batch_id' => 'b', 'status' => BatchStatus::InProgress, 'total' => 5, 'succeeded' => 0]);

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('batchStatus')->andReturn(new BatchResponse('b', BatchStatus::Completed, new RequestCounts(total: 0)));
    $driver->shouldReceive('batchResults')->andReturn([]);

    $registry = Mockery::mock(ProviderRegistryContract::class);
    $registry->shouldReceive('resolve')->andReturn($driver);

    $synced = batchService($registry)->syncFromProvider($job);

    expect($synced->status)->toBe(BatchStatus::InProgress);
    expect($synced->total)->toBe(5); // preserved, not overwritten with 0
    expect(BatchResult::where('batch_job_id', $job->id)->count())->toBe(0);
});

it('is idempotent — a terminal job is not re-synced', function () {
    $job = BatchJob::create(['provider' => 'openai', 'modality' => 'embed', 'batch_id' => 'b', 'status' => BatchStatus::Completed]);

    $registry = Mockery::mock(ProviderRegistryContract::class);
    $registry->shouldNotReceive('resolve');

    expect(batchService($registry)->syncFromProvider($job)->status)->toBe(BatchStatus::Completed);
});

it('fires BatchGroupCompleted when the last job in a group completes', function () {
    Event::fake([BatchGroupCompleted::class]);

    $group = BatchGroup::create(['label' => 'captions']);
    BatchJob::create(['batch_group_id' => $group->id, 'provider' => 'openai', 'modality' => 'text', 'batch_id' => 'done', 'status' => BatchStatus::Completed]);
    $pending = BatchJob::create(['batch_group_id' => $group->id, 'provider' => 'openai', 'modality' => 'text', 'batch_id' => 'b2', 'status' => BatchStatus::InProgress]);

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('batchStatus')->andReturn(new BatchResponse('b2', BatchStatus::Completed, new RequestCounts(total: 1, succeeded: 1)));
    $driver->shouldReceive('batchResults')->andReturn([new BatchResultData('x', BatchResultStatus::Succeeded)]);

    $registry = Mockery::mock(ProviderRegistryContract::class);
    $registry->shouldReceive('resolve')->andReturn($driver);

    batchService($registry)->syncFromProvider($pending);

    expect($group->fresh()->isComplete())->toBeTrue();
    expect($group->progress()['completed_jobs'])->toBe(2);
    Event::assertDispatched(BatchGroupCompleted::class);
});

it('the poll command advances open jobs and skips when none', function () {
    // No open jobs → friendly no-op.
    $this->artisan('atlas:batch-poll')->expectsOutputToContain('No open batch jobs')->assertSuccessful();

    $job = BatchJob::create(['provider' => 'openai', 'modality' => 'embed', 'batch_id' => 'b', 'status' => BatchStatus::InProgress]);

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('batchStatus')->andReturn(new BatchResponse('b', BatchStatus::Completed, new RequestCounts(total: 1, succeeded: 1)));
    $driver->shouldReceive('batchResults')->andReturn([new BatchResultData('x', BatchResultStatus::Succeeded)]);

    $registry = Mockery::mock(ProviderRegistryContract::class);
    $registry->shouldReceive('resolve')->andReturn($driver);
    app()->instance(ProviderRegistryContract::class, $registry);

    $this->artisan('atlas:batch-poll')->expectsOutputToContain('Polled 1 batch job')->assertSuccessful();

    expect($job->fresh()->status)->toBe(BatchStatus::Completed);
});

it('prunes batch jobs and their results older than the retention window', function () {
    config()->set('atlas.batch.retention_days', 90);

    $old = BatchJob::create(['provider' => 'openai', 'modality' => 'embed', 'batch_id' => 'old', 'status' => BatchStatus::Completed]);
    $old->forceFill(['created_at' => now()->subDays(120)])->save();
    BatchResult::create(['batch_job_id' => $old->id, 'custom_id' => 'a', 'status' => BatchResultStatus::Succeeded]);

    $recent = BatchJob::create(['provider' => 'openai', 'modality' => 'embed', 'batch_id' => 'new', 'status' => BatchStatus::Completed]);
    $recent->forceFill(['created_at' => now()->subDays(10)])->save();

    $this->artisan('atlas:batch-prune')->expectsOutputToContain('Pruned 1 batch job')->assertSuccessful();

    expect(BatchJob::find($old->id))->toBeNull();
    expect(BatchJob::find($recent->id))->not->toBeNull();
    expect(BatchResult::where('batch_job_id', $old->id)->count())->toBe(0);
});

it('honors a --days override when pruning', function () {
    $job = BatchJob::create(['provider' => 'openai', 'modality' => 'embed', 'batch_id' => 'x', 'status' => BatchStatus::Completed]);
    $job->forceFill(['created_at' => now()->subDays(20)])->save();

    $this->artisan('atlas:batch-prune', ['--days' => 7])->assertSuccessful();

    expect(BatchJob::find($job->id))->toBeNull();
});

it('updates counts and stays open on a non-terminal status', function () {
    $job = BatchJob::create(['provider' => 'openai', 'modality' => 'embed', 'batch_id' => 'b', 'status' => BatchStatus::Validating]);

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('batchStatus')->andReturn(new BatchResponse('b', BatchStatus::InProgress, new RequestCounts(total: 3, processing: 3)));
    // batchResults is NOT called for a non-successful status.

    $registry = Mockery::mock(ProviderRegistryContract::class);
    $registry->shouldReceive('resolve')->andReturn($driver);

    $synced = batchService($registry)->syncFromProvider($job);

    expect($synced->status)->toBe(BatchStatus::InProgress);
    expect($synced->total)->toBe(3);
    expect($synced->processing)->toBe(3);
});

it('the poll command filters by provider', function () {
    BatchJob::create(['provider' => 'openai', 'modality' => 'embed', 'batch_id' => 'oa', 'status' => BatchStatus::InProgress]);
    $anthropic = BatchJob::create(['provider' => 'anthropic', 'modality' => 'text', 'batch_id' => 'an', 'status' => BatchStatus::InProgress]);

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('batchStatus')->once()->with('oa')->andReturn(new BatchResponse('oa', BatchStatus::InProgress, new RequestCounts(total: 1, processing: 1)));

    $registry = Mockery::mock(ProviderRegistryContract::class);
    $registry->shouldReceive('resolve')->with('openai')->andReturn($driver);
    app()->instance(ProviderRegistryContract::class, $registry);

    $this->artisan('atlas:batch-poll', ['--provider' => 'openai'])->expectsOutputToContain('Polled 1 batch job')->assertSuccessful();

    expect($anthropic->fresh()->status)->toBe(BatchStatus::InProgress); // untouched
});

it('prune is a clean no-op when nothing is old enough', function () {
    BatchJob::create(['provider' => 'openai', 'modality' => 'text', 'batch_id' => 'recent', 'status' => BatchStatus::Completed]);

    $this->artisan('atlas:batch-prune')->expectsOutputToContain('Pruned 0 batch job(s)')->assertSuccessful();

    expect(BatchJob::count())->toBe(1);
});

it('prunes jobs, results and groups across multiple chunks', function () {
    config()->set('atlas.batch.retention_days', 90);

    foreach (['j1', 'j2'] as $bid) {
        $job = BatchJob::create(['provider' => 'openai', 'modality' => 'text', 'batch_id' => $bid, 'status' => BatchStatus::Completed]);
        $job->forceFill(['created_at' => now()->subDays(120)])->save();
        BatchResult::create(['batch_job_id' => $job->id, 'custom_id' => 'a', 'status' => BatchResultStatus::Succeeded]);
    }
    foreach (['g1', 'g2'] as $label) {
        BatchGroup::create(['label' => $label])->forceFill(['created_at' => now()->subDays(120)])->save();
    }

    // chunk=1 forces the do/while loops to iterate more than once for both jobs and groups.
    $this->artisan('atlas:batch-prune', ['--chunk' => 1])
        ->expectsOutputToContain('Pruned 2 batch job(s) and 2 empty group(s)')
        ->assertSuccessful();

    expect(BatchJob::count())->toBe(0);
    expect(BatchResult::count())->toBe(0);
    expect(BatchGroup::count())->toBe(0);
});

it('BatchSubmitted fires on submit', function () {
    Event::fake([BatchSubmitted::class]);

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('batch')->andReturn(new BatchResponse('b', BatchStatus::Validating, new RequestCounts(total: 1)));
    $registry = Mockery::mock(ProviderRegistryContract::class);
    $registry->shouldReceive('resolve')->andReturn($driver);

    batchService($registry)->submitAndTrack(embedBatchRequest());

    Event::assertDispatched(BatchSubmitted::class);
});
