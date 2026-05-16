<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Queue\Jobs;

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Embeddings\Chunkable;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Persistence\Services\ChunkContentService;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queue job that reconciles a single record's chunked embeddings.
 *
 * Dispatched from two paths:
 *   1. The trait's `saved` hook (on-demand, primary path).
 *   2. The `atlas:chunk` safety-net sweep (backstop for rows that
 *      bypassed Eloquent saves).
 *
 * Debounce semantics. The job implements `ShouldBeUnique` keyed by
 * `modelClass:modelId`, so concurrent dispatches for the same row
 * collapse into a single queued job. On execution the job checks
 * `updated_at` against the settle window — if the row was edited
 * within `sweep_settle` seconds, the job releases itself forward
 * until the window passes since the last edit. The unique lock
 * stays held across releases (Laravel only frees it once the job
 * completes without releasing), so additional saves during the
 * debounce period no-op at dispatch time. Net effect: chunking
 * happens exactly once, `sweep_settle` seconds after the LAST
 * edit, no matter how many saves came before.
 *
 * Idempotency. The handler also short-circuits when
 * `content_hash === indexed_hash` (another job already reconciled,
 * or content reverted) and when the failure cap is reached.
 * ChunkContentService::reconcile()'s in-transaction content_hash
 * recheck is a third layer of safety against stale writes.
 *
 * Retry budget. `retryUntil()` gives the job up to one hour of
 * self-releases before Laravel marks it failed — a safety valve
 * for never-ending edit storms. After that the row stays dirty
 * and the safety-net sweep will pick it up.
 */
class ChunkContentJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Preserved from v3.1.0 for backward-compat surface stability —
     * `retryUntil()` is the authoritative budget (see the docblock),
     * and the worker checks `retryUntil` first regardless of `$tries`.
     * Kept so Horizon/Pulse dashboards and consumer tests that
     * introspect `$tries` continue to see a non-null value.
     */
    public int $tries = 1;

    public int $timeout = 300;

    /**
     * Unique-lock TTL in seconds. Long enough that an extended edit
     * burst doesn't bypass debouncing by letting the lock expire,
     * short enough that a crashed worker doesn't block dispatches
     * forever.
     */
    public int $uniqueFor = 3600;

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly int|string $modelId,
    ) {}

    /**
     * Per-row unique key. Two dispatches for the same model collapse
     * into one queued job; the second dispatch is silently dropped
     * (returns the existing job's payload).
     */
    public function uniqueId(): string
    {
        return $this->modelClass.':'.$this->modelId;
    }

    /**
     * Allow self-releases to keep the job alive past the default
     * `$tries` limit. After this timestamp Laravel marks the job
     * failed; the row is then picked up by the safety-net sweep.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHour();
    }

    public function handle(ChunkContentService $service): void
    {
        $modelClass = $this->modelClass;
        $model = $modelClass::query()->find($this->modelId);

        // Owner is gone — orphans are cleaned up by the sweep / prune
        // command. Nothing to do here.
        if ($model === null) {
            return;
        }

        if (! $model instanceof Chunkable) {
            throw new AtlasException(
                "[{$modelClass}] dispatched to ChunkContentJob but does not implement ".Chunkable::class.'.'
            );
        }

        // Idempotent short-circuit: an earlier dispatch already reconciled
        // this row, or content reverted to the indexed state. Exiting here
        // is cheaper than going through the chunker + embed batch path.
        if ($model->getAttribute('content_hash') === $model->getAttribute('indexed_hash')) {
            return;
        }

        $config = app(AtlasConfig::class);

        // Poison-row guard. After `max_failures` consecutive failures the
        // row is excluded so the worker doesn't churn on a permanently
        // broken record. Operators can clear `index_failure_count` and
        // `last_index_error` to retry, or run `atlas:rechunk` to force.
        if ((int) ($model->getAttribute('index_failure_count') ?? 0) >= $config->chunkMaxFailures) {
            return;
        }

        // Debounce against the LAST edit. The dispatch-on-save delay only
        // postpones the FIRST job in a burst; if the user keeps saving,
        // we want chunking to wait until they stop. Re-release the same
        // job until `sweep_settle` seconds have passed since the most
        // recent edit. The unique lock is preserved across releases, so
        // dispatches from intermediate saves continue to no-op.
        $settle = $config->chunkSweepSettle;
        $updatedAt = $model->getAttribute('updated_at');
        if ($updatedAt instanceof DateTimeInterface) {
            $secondsSinceEdit = max(0, now()->getTimestamp() - $updatedAt->getTimestamp());
            if ($secondsSinceEdit < $settle) {
                $this->release($settle - $secondsSinceEdit);

                return;
            }
        }

        $service->reconcile($model);
    }
}
