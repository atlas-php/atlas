<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Batch;

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Enums\BatchStatus;
use Atlasphp\Atlas\Events\BatchCompleted;
use Atlasphp\Atlas\Events\BatchFailed;
use Atlasphp\Atlas\Events\BatchGroupCompleted;
use Atlasphp\Atlas\Events\BatchSubmitted;
use Atlasphp\Atlas\Persistence\Models\BatchGroup;
use Atlasphp\Atlas\Persistence\Models\BatchJob;
use Atlasphp\Atlas\Persistence\Models\BatchResult;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Requests\Batch;
use Atlasphp\Atlas\Responses\BatchResult as BatchResultData;
use Atlasphp\Atlas\Responses\EmbeddingsResponse;
use Atlasphp\Atlas\Responses\RequestCounts;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Class BatchService
 *
 * Orchestrates tracked batch jobs: submits and persists them, and syncs their
 * state from the provider — the single hydration path shared by the poll
 * command (and any future webhook). Keeps the synchronous submission and the
 * deferred result ingestion cohesive in one place.
 */
class BatchService
{
    public function __construct(
        private readonly ProviderRegistryContract $registry,
        private readonly Dispatcher $events,
        private readonly AtlasConfig $config,
    ) {}

    /**
     * Submit a batch to the provider and persist a tracking record.
     */
    public function submitAndTrack(Batch $batch, ?BatchGroup $group = null): BatchJob
    {
        $response = $this->registry->resolve($batch->provider)->batch($batch);

        /** @var class-string<BatchJob> $model */
        $model = $this->config->model('batch_job', BatchJob::class);

        $job = $model::create([
            'batch_group_id' => $group?->getKey(),
            'provider' => $batch->provider,
            'modality' => $batch->modality->value,
            'batch_id' => $response->batchId,
            'status' => $response->status,
            'total' => $response->counts->total,
            'succeeded' => $response->counts->succeeded,
            'failed' => $response->counts->failed,
            'processing' => $response->counts->processing,
            'input_file_id' => $response->inputFileId,
            'output_file_id' => $response->outputFileId,
            'submitted_at' => now(),
        ]);

        $this->events->dispatch(new BatchSubmitted($job));

        return $job;
    }

    /**
     * Advance a tracked job by syncing its state from the provider.
     *
     * No-op on already-terminal jobs (idempotent). On completion, fetches and
     * stores every line result, rolls up usage, and fires events.
     */
    public function syncFromProvider(BatchJob $job): BatchJob
    {
        if ($job->status->isTerminal() || $job->batch_id === null) {
            return $job;
        }

        $driver = $this->registry->resolve($job->provider);
        $response = $driver->batchStatus($job->batch_id);

        if ($response->status->isSuccessful()) {
            // Fetch results BEFORE writing any terminal status. If this transport
            // call throws, the job stays non-terminal and the next poll retries —
            // never orphaned as "completed" with no results.
            $results = iterator_to_array($driver->batchResults($job->batch_id), false);

            if ($results === []) {
                // Provider reports success but results aren't available yet (some
                // providers, e.g. Gemini, deliver inline results shortly after the
                // state flip). Keep the job open and retry on the next poll.
                $job->applyStatus(BatchStatus::InProgress, $response->counts);

                return $job->refresh();
            }

            $this->hydrate($job, $response->counts, $results);
        } elseif ($response->status->isTerminal()) {
            $job->markFailed($response->status, $response->error);
            $this->events->dispatch(new BatchFailed($job));
            $this->checkGroup($job);
        } else {
            $job->applyStatus($response->status, $response->counts);
        }

        return $job->refresh();
    }

    /**
     * Store per-line results, roll up usage, and mark the job completed.
     *
     * The terminal "completed" status, the final counts, the rolled-up usage,
     * and every result row commit together in one transaction or not at all — so
     * the job only becomes terminal once its results are durably stored. A crash
     * mid-hydration rolls back, leaving the job non-terminal for the next poll
     * (no duplicate rows, no double-counted usage, no orphaned completion).
     *
     * @param  array<int, BatchResultData>  $results
     */
    private function hydrate(BatchJob $job, RequestCounts $counts, array $results): void
    {
        /** @var class-string<BatchResult> $model */
        $model = $this->config->model('batch_result', BatchResult::class);

        DB::transaction(function () use ($job, $model, $counts, $results): void {
            $usage = new Usage(0, 0);

            foreach ($results as $result) {
                $model::create([
                    'batch_job_id' => $job->getKey(),
                    'custom_id' => $result->customId,
                    'status' => $result->status,
                    'response' => $this->serializeResponse($result->response),
                    'usage' => $result->usage?->toArray(),
                    'error' => $result->error?->getMessage(),
                ]);

                if ($result->usage !== null) {
                    $usage = $usage->merge($result->usage);
                }
            }

            $job->markCompleted($counts, $usage);
        });

        $this->events->dispatch(new BatchCompleted($job));
        $this->checkGroup($job);
    }

    /**
     * Normalize a parsed line response into a storable array.
     *
     * @return array<string, mixed>|null
     */
    private function serializeResponse(?object $response): ?array
    {
        if ($response instanceof TextResponse) {
            return [
                'text' => $response->text,
                'finish_reason' => $response->finishReason->value,
            ];
        }

        if ($response instanceof EmbeddingsResponse) {
            return ['embedding' => $response->embeddings[0] ?? []];
        }

        return null;
    }

    /**
     * Fire the group-completed event once all of a group's jobs are terminal.
     */
    private function checkGroup(BatchJob $job): void
    {
        if ($job->batch_group_id === null) {
            return;
        }

        $group = $job->group;

        if ($group !== null && $group->isComplete()) {
            $this->events->dispatch(new BatchGroupCompleted($group));
        }
    }
}
