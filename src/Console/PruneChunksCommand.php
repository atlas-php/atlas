<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Console;

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Embeddings\ChunkableRegistry;
use Atlasphp\Atlas\Persistence\Models\Chunk;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Purges orphan chunk rows whose owner record no longer exists.
 *
 * Schedule:
 *   Schedule::command('atlas:prune-chunks')->daily()->withoutOverlapping();
 *
 * Most chunk lifecycle is handled inline:
 *   - Eloquent `$model->delete()` fires the trait's deleting hook and
 *     removes chunks synchronously.
 *   - `$model->update($body)` flips content_hash, dispatch-on-save
 *     reconciles, the diff/insert/delete path inside
 *     ChunkContentService keeps chunks aligned with current content.
 *
 * This command handles the residual cases those two paths can't see:
 *   - DB-level FK CASCADE deletes that bypass Eloquent events entirely.
 *   - Raw `DB::table('owners')->delete()` calls.
 *   - Crash recovery: a chunk row written before its owner row was
 *     committed in a failed transaction.
 *
 * Daily cadence is the right tradeoff: orphan chunks waste disk and
 * may surface in similarity search until pruned, but they don't break
 * correctness. A daily run keeps drift bounded without paying for an
 * `atlas_chunks`-wide scan every minute.
 *
 * Soft-delete safety: `withoutGlobalScopes()` is applied so SoftDeletes
 * owners are NOT treated as orphans — the row still exists in the DB
 * and can be restored.
 */
class PruneChunksCommand extends Command
{
    protected $signature = 'atlas:prune-chunks
        {--model= : Only prune chunks for this fully-qualified model class (default: all registered)}';

    protected $description = 'Delete chunk rows whose owner record no longer exists.';

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

        /** @var class-string<Chunk> $chunkModel */
        $chunkModel = $config->model('chunk', Chunk::class);

        $totalDeleted = 0;

        foreach ($classes as $class) {
            /** @var class-string<Model> $class */
            /** @var Model $sample */
            $sample = new $class;
            $key = $sample->getKeyName();
            $morphClass = $sample->getMorphClass();

            $deleted = $chunkModel::query()
                ->where('chunkable_type', $morphClass)
                ->whereNotIn(
                    'chunkable_id',
                    $class::query()->withoutGlobalScopes()->select($key),
                )
                ->delete();

            if ($deleted > 0) {
                $totalDeleted += $deleted;
                $this->info('['.$class.'] pruned '.$deleted.' orphan chunk(s).');
            }
        }

        $this->info("Done. {$totalDeleted} orphan chunk(s) pruned.");

        return self::SUCCESS;
    }
}
