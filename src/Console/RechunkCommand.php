<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Console;

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Embeddings\ChunkableRegistry;
use Atlasphp\Atlas\Queue\Jobs\ChunkContentJob;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Marks chunkable model rows dirty so the next atlas:chunk sweep re-chunks them.
 *
 * Usage modes:
 *
 *   php artisan atlas:rechunk
 *     → marks every row of every registered chunkable class dirty. Use after
 *       a global change (new embedding model, new chunker, dimension swap).
 *
 *   php artisan atlas:rechunk --all
 *     → identical to no-arg form, kept as an explicit synonym.
 *
 *   php artisan atlas:rechunk "App\Models\Project"
 *     → marks every row of one class dirty. Use after deploying a new
 *       chunker, changing chunk_size, or any other class-scoped change.
 *
 *   php artisan atlas:rechunk "App\Models\Project" 42
 *     → marks only row id=42 dirty. Use to fix one record's chunks after
 *       investigating bad retrieval results for it.
 *
 * Flags:
 *
 *   --reset-failures
 *     Also zero out `index_failure_count` and `last_index_error`. Without
 *     this, rows past the failure cap (`atlas.embeddings.max_failures`) are
 *     silently skipped by both the sweep and the job — the row stays
 *     "dirty" but never reindexes. The command reports the poisoned count
 *     when this flag is omitted so the operator can see why nothing moved.
 *
 *   --dispatch
 *     Dispatch ChunkContentJob for every dirty target immediately, bypassing
 *     the sweep's `sweep_batch` per-tick cap and the cron wait. Useful when
 *     you want immediate, visible progress instead of trusting the next
 *     scheduled atlas:chunk tick.
 *
 * The update is wrapped in `Model::withoutTimestamps()` so `updated_at` is
 * not bumped — the settle window protects in-flight user edits, not
 * operator rechunks, and bumping `updated_at` would otherwise push every
 * row into the 60-second settle window on the next sweep tick.
 */
class RechunkCommand extends Command
{
    protected $signature = 'atlas:rechunk
        {class? : Fully-qualified model class to mark dirty (omit to mark all registered classes)}
        {id? : Optional model ID — if provided, only that row is marked dirty}
        {--all : Mark every registered chunkable class dirty (synonym for omitting class)}
        {--reset-failures : Also reset index_failure_count so previously-skipped rows are picked up}
        {--dispatch : Dispatch ChunkContentJob for every dirty target immediately instead of waiting for the next atlas:chunk tick}';

    protected $description = 'Mark chunkable rows dirty (whole class or a single ID) so the next sweep re-chunks them.';

    public function handle(ChunkableRegistry $registry, AtlasConfig $config): int
    {
        $class = $this->argument('class');
        $id = $this->argument('id');
        $all = (bool) $this->option('all');
        $resetFailures = (bool) $this->option('reset-failures');
        $dispatch = (bool) $this->option('dispatch');

        if ($id !== null && ($class === null || $all)) {
            $this->error('Cannot pass an id without a single class argument.');

            return self::FAILURE;
        }

        if ($class !== null && $all) {
            $this->error('Pass either a class argument or --all, not both.');

            return self::FAILURE;
        }

        $targets = $this->resolveTargets($registry, $class === null || $all ? null : (string) $class);
        if ($targets === null) {
            return self::FAILURE;
        }

        if (empty($targets)) {
            $this->warn('No chunkable models registered. Nothing to do.');

            return self::SUCCESS;
        }

        $updates = ['indexed_hash' => null];
        if ($resetFailures) {
            $updates['index_failure_count'] = 0;
            $updates['last_index_error'] = null;
        }

        $maxFailures = $config->chunkMaxFailures;

        $totalMarked = 0;
        $totalPoisoned = 0;

        foreach ($targets as $targetClass) {
            $result = $this->rechunkOne($targetClass, $id, $updates, $resetFailures, $maxFailures);
            $totalMarked += $result['marked'];
            $totalPoisoned += $result['poisoned'];
        }

        if (count($targets) > 1) {
            $this->newLine();
            $this->info("Total: {$totalMarked} row(s) marked dirty across ".count($targets).' class(es).');
        }

        if ($totalPoisoned > 0 && ! $resetFailures) {
            $this->newLine();
            $this->warn("{$totalPoisoned} row(s) past the failure cap (index_failure_count >= {$maxFailures}) will be skipped by the sweep.");
            $this->warn('Pass --reset-failures to rechunk them as well.');
        }

        if ($dispatch) {
            $this->newLine();
            $this->dispatchAll($targets, $id, $resetFailures, $maxFailures, $config->queue);
        } elseif ($totalMarked > 0) {
            $this->newLine();
            $this->line('Next: wait for the atlas:chunk cron tick, or pass --dispatch to push jobs now.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, class-string<Model>>|null
     */
    protected function resolveTargets(ChunkableRegistry $registry, ?string $class): ?array
    {
        if ($class === null) {
            return $registry->all();
        }

        if (! class_exists($class)) {
            $this->error("Class [{$class}] does not exist.");

            return null;
        }

        if (! $registry->has($class)) {
            $this->error("Class [{$class}] is not registered as chunkable. Register it via Atlas::registerChunkable() in your service provider.");

            return null;
        }

        return [$class];
    }

    /**
     * @param  class-string<Model>  $targetClass
     * @param  array<string, mixed>  $updates
     * @return array{marked: int, poisoned: int}
     */
    protected function rechunkOne(string $targetClass, int|string|null $id, array $updates, bool $resetFailures, int $maxFailures): array
    {
        $poisonedCount = 0;
        if (! $resetFailures) {
            $poisonQuery = $targetClass::query()->where('index_failure_count', '>=', $maxFailures);
            if ($id !== null) {
                $poisonQuery->whereKey($id);
            }
            $poisonedCount = $poisonQuery->count();
        }

        // Skip the timestamp bump so freshly-rechunked rows are immediately
        // sweep-eligible — the settle window protects in-flight user edits,
        // not operator rechunks.
        $count = $targetClass::withoutTimestamps(function () use ($targetClass, $id, $updates): int {
            $query = $targetClass::query();
            if ($id !== null) {
                $query->whereKey($id);
            }

            return $query->update($updates);
        });

        if ($id !== null) {
            if ($count === 0) {
                $this->warn("[{$targetClass}] No row found with id [{$id}].");

                return ['marked' => 0, 'poisoned' => 0];
            }
            $this->info("[{$targetClass}] Marked id={$id} dirty.");
        } else {
            $line = "[{$targetClass}] Marked {$count} row(s) dirty";
            if ($poisonedCount > 0) {
                $line .= " ({$poisonedCount} past failure cap)";
            }
            $line .= '.';
            $this->info($line);
        }

        return ['marked' => $count, 'poisoned' => $poisonedCount];
    }

    /**
     * @param  array<int, class-string<Model>>  $targets
     */
    protected function dispatchAll(array $targets, int|string|null $id, bool $resetFailures, int $maxFailures, string $queue): void
    {
        $this->info('Dispatching ChunkContentJob(s)...');

        $totalDispatched = 0;
        foreach ($targets as $targetClass) {
            /** @var Model $sample */
            $sample = new $targetClass;
            $key = $sample->getKeyName();

            $query = $targetClass::query()->whereNull('indexed_hash');
            if (! $resetFailures) {
                $query->where('index_failure_count', '<', $maxFailures);
            }
            if ($id !== null) {
                $query->whereKey($id);
            }

            $ids = $query->pluck($key)->all();
            foreach ($ids as $rowId) {
                ChunkContentJob::dispatch($targetClass, $rowId)->onQueue($queue);
            }

            $this->info('['.$targetClass.'] '.count($ids).' job(s) dispatched.');
            $totalDispatched += count($ids);
        }

        $this->newLine();
        $this->info("Total: {$totalDispatched} job(s) dispatched. Monitor Horizon for processing.");
    }
}
