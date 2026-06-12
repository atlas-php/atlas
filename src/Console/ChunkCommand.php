<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Console;

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Embeddings\ChunkableRegistry;
use Atlasphp\Atlas\Persistence\Models\Chunk;
use Atlasphp\Atlas\Queue\Jobs\ChunkContentJob;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Sweeps registered chunkable models for dirty rows and dispatches
 * reconciler jobs. Also prunes orphan chunk rows on every tick.
 *
 * As of the dispatch-on-save release, the trait's `saved` hook is
 * the primary trigger and this command is a backstop for rows that
 * bypass model events (raw `DB::table()->update()`, mass factory
 * seeds, prior data, queue outages) plus the legacy code path for
 * consumers running with `atlas.embeddings.dispatch_on_save = false`.
 *
 * Recommended schedule:
 *   Schedule::command('atlas:chunk')->hourly()->withoutOverlapping();
 *
 * The previously-recommended every-minute cadence still works; it's
 * just unnecessary now that dispatch-on-save handles the hot path.
 * Consumers wanting to split the orphan scan onto a different
 * cadence can run `atlas:chunk --skip-orphans` here and schedule
 * `atlas:prune-chunks` (daily) separately.
 *
 * Mechanics: scans each registered chunkable model for rows whose
 * `content_hash` differs from `indexed_hash`, filters out rows
 * inside the settle window or past the failure cap, and dispatches
 * a `ChunkContentJob` per remaining row (capped at `sweep_batch`
 * per tick). Orphan chunks (chunk rows whose owner no longer
 * exists) are deleted unless `--skip-orphans` is passed.
 *
 * Scale note: the dirty predicate (`IS DISTINCT FROM`) is not
 * served by a regular btree on `(content_hash, indexed_hash)`.
 * Consumers expecting >1M-row tables should add a partial index
 * — see ChunkedEmbeddingColumns docblock for the recommended DDL.
 */
class ChunkCommand extends Command
{
    protected $signature = 'atlas:chunk
        {--model= : Only sweep this fully-qualified model class (default: all registered)}
        {--skip-orphans : Skip the orphan-chunk purge step (run atlas:prune-chunks separately)}';

    protected $description = 'Sweep dirty chunkable records and dispatch embedding reconciliation jobs.';

    public function handle(ChunkableRegistry $registry, AtlasConfig $config): int
    {
        $only = $this->option('model');
        $skipOrphans = (bool) $this->option('skip-orphans');
        $classes = $registry->all();

        if ($only !== null) {
            if (! $registry->has((string) $only)) {
                $this->error("Model [{$only}] is not registered as chunkable.");

                return self::FAILURE;
            }
            $classes = [(string) $only];
        }

        if (empty($classes)) {
            $this->info('No chunkable models registered. Nothing to do.');

            return self::SUCCESS;
        }

        $batch = $config->chunkSweepBatch;
        $settle = $config->chunkSweepSettle;
        $maxFailures = $config->chunkMaxFailures;
        $queue = $config->queue;

        $totalDispatched = 0;
        $totalOrphansDeleted = 0;

        /** @var class-string<Chunk> $chunkModel */
        $chunkModel = $config->model('chunk', Chunk::class);

        // Postgres treats NULL-vs-value as distinct under `IS DISTINCT
        // FROM`, which is the semantics we need (a fresh row has
        // indexed_hash = NULL and content_hash = some-hash, and must be
        // picked up). Other drivers don't have that operator; COALESCE
        // approximates it. Detect the driver from the chunk model's own
        // connection — the sweep queries run against it, and it may be a
        // separate atlas.persistence.connection that differs from the default.
        $isPostgres = (new $chunkModel)->getConnection()->getDriverName() === 'pgsql';
        $dirtyPredicate = $isPostgres
            ? 'content_hash IS DISTINCT FROM indexed_hash'
            : "COALESCE(content_hash, '') <> COALESCE(indexed_hash, '')";

        foreach ($classes as $class) {
            /** @var class-string<Model> $class */
            /** @var Model $sample */
            $sample = new $class;
            $key = $sample->getKeyName();
            $morphClass = $sample->getMorphClass();

            // Polymorphic relations can't carry FK cascades, so chunks for
            // a hard-deleted owner can outlive the owner — most commonly
            // when a consumer mass-deletes (Eloquent skips model events on
            // query-builder delete). Prune those orphans unless the caller
            // opted out (e.g. running this hourly + atlas:prune-chunks daily).
            //
            // Use withoutGlobalScopes() so SoftDeletes-using owners are NOT
            // treated as orphans — the row still exists in the DB and can be
            // restored. Without this guard the sweep would prune chunks the
            // moment an owner is soft-deleted, losing the embedding work.
            if (! $skipOrphans) {
                $orphanDeleted = $chunkModel::query()
                    ->where('chunkable_type', $morphClass)
                    ->whereNotIn(
                        'chunkable_id',
                        $class::query()->withoutGlobalScopes()->select($key),
                    )
                    ->delete();
                if ($orphanDeleted > 0) {
                    $totalOrphansDeleted += $orphanDeleted;
                    $this->info('['.$class.'] pruned '.$orphanDeleted.' orphan chunk(s).');
                }
            }

            $ids = $class::query()
                ->whereRaw($dirtyPredicate)
                ->where('updated_at', '<', now()->subSeconds($settle))
                ->where('index_failure_count', '<', $maxFailures)
                ->orderBy('updated_at', 'asc')
                ->limit($batch)
                ->pluck($key)
                ->all();

            foreach ($ids as $id) {
                ChunkContentJob::dispatch($class, $id)->onQueue($queue);
                $totalDispatched++;
            }

            if (! empty($ids)) {
                $this->info('['.$class.'] dispatched '.count($ids).' job(s).');
            }
        }

        $summary = "Done. {$totalDispatched} job(s) dispatched";
        if ($totalOrphansDeleted > 0) {
            $summary .= "; {$totalOrphansDeleted} orphan chunk(s) pruned";
        }
        $this->info($summary.'.');

        return self::SUCCESS;
    }
}
