<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Console;

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Embeddings\ChunkableRegistry;
use Atlasphp\Atlas\Persistence\Models\Chunk;
use Atlasphp\Atlas\Queue\Jobs\ChunkContentJob;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Sweeps registered chunkable models for dirty rows and dispatches reconciler jobs.
 *
 * Schedule:
 *   Schedule::command('atlas:chunk')->everyMinute()->withoutOverlapping();
 *
 * The settle period (config: embeddings.sweep_settle) prevents re-embedding
 * mid-edit: a row whose updated_at is more recent than NOW() - settle is
 * not eligible. After max_failures consecutive failures, a row is excluded
 * from sweeps and shows up via the model's index_failure_count column.
 */
class ChunkCommand extends Command
{
    protected $signature = 'atlas:chunk
        {--model= : Only sweep this fully-qualified model class (default: all registered)}';

    protected $description = 'Sweep dirty chunkable records and dispatch embedding reconciliation jobs.';

    public function handle(ChunkableRegistry $registry, AtlasConfig $config): int
    {
        $only = $this->option('model');
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
        // PG supports IS DISTINCT FROM and has a partial index keyed on it
        // (see ChunkedEmbeddingColumns::add); use the same predicate so the
        // index can be applied. Other drivers fall back to COALESCE.
        $isPostgres = DB::connection()->getDriverName() === 'pgsql';
        $dirtyPredicate = $isPostgres
            ? 'content_hash IS DISTINCT FROM indexed_hash'
            : "COALESCE(content_hash, '') <> COALESCE(indexed_hash, '')";

        /** @var class-string<Chunk> $chunkModel */
        $chunkModel = $config->model('chunk', Chunk::class);

        foreach ($classes as $class) {
            /** @var class-string<Model> $class */
            /** @var Model $sample */
            $sample = new $class;
            $key = $sample->getKeyName();
            $morphClass = $sample->getMorphClass();

            // Polymorphic relations can't carry FK cascades, so chunks for
            // a hard-deleted owner can outlive the owner — most commonly
            // when a consumer mass-deletes (Eloquent skips model events on
            // query-builder delete). Prune those orphans on every sweep.
            //
            // Use withoutGlobalScopes() so SoftDeletes-using owners are NOT
            // treated as orphans — the row still exists in the DB and can be
            // restored. Without this guard the sweep would prune chunks the
            // moment an owner is soft-deleted, losing the embedding work.
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
