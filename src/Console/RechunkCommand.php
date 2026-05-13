<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Console;

use Atlasphp\Atlas\Embeddings\ChunkableRegistry;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Marks chunkable model rows dirty so the next atlas:chunk sweep re-chunks them.
 *
 * Two usage modes:
 *
 *   php artisan atlas:rechunk "App\Models\Project"
 *     → marks every row of the class dirty. Use after deploying a new
 *       chunker, changing chunk_size, or any other change that should
 *       rebuild every record's chunks from scratch.
 *
 *   php artisan atlas:rechunk "App\Models\Project" 42
 *     → marks only row id=42 dirty. Use to fix one record's chunks after
 *       investigating bad retrieval results for it.
 */
class RechunkCommand extends Command
{
    protected $signature = 'atlas:rechunk
        {class : Fully-qualified model class to mark dirty}
        {id? : Optional model ID — if provided, only that row is marked dirty}
        {--reset-failures : Also reset index_failure_count so previously-skipped rows are picked up}';

    protected $description = 'Mark chunkable rows dirty (whole class or a single ID) so the next sweep re-chunks them.';

    public function handle(ChunkableRegistry $registry): int
    {
        $class = (string) $this->argument('class');
        $id = $this->argument('id');

        if (! class_exists($class)) {
            $this->error("Class [{$class}] does not exist.");

            return self::FAILURE;
        }

        if (! $registry->has($class)) {
            $this->error("Class [{$class}] is not registered as chunkable. Register it via Atlas::registerChunkable() in your service provider.");

            return self::FAILURE;
        }

        $updates = ['indexed_hash' => null];
        if ((bool) $this->option('reset-failures')) {
            $updates['index_failure_count'] = 0;
            $updates['last_index_error'] = null;
        }

        /** @var class-string<Model> $class */
        $query = $class::query();

        if ($id !== null) {
            $query->whereKey($id);
        }

        $count = $query->update($updates);

        if ($id !== null) {
            if ($count === 0) {
                $this->warn("No row found for [{$class}] with id [{$id}].");

                return self::SUCCESS;
            }
            $this->info("Marked [{$class}] id={$id} dirty.");
        } else {
            $this->info("Marked {$count} row(s) of [{$class}] dirty.");
        }

        return self::SUCCESS;
    }
}
