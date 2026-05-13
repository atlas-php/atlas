<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Queue\Jobs;

use Atlasphp\Atlas\Embeddings\Chunkable;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Persistence\Services\ChunkContentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queue job that reconciles a single record's chunked embeddings.
 *
 * Dispatched by the atlas:chunk sweep command per dirty row. Tries are
 * conservative — the sweep will pick up failures again on the next pass,
 * up to max_failures, so worker-level retry isn't load-bearing.
 *
 * The worker process is responsible for catching its own exceptions
 * (service handles that, and increments the record's failure counter);
 * the job re-raises so Laravel records the failure normally.
 */
class ChunkContentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly int|string $modelId,
    ) {}

    public function handle(ChunkContentService $service): void
    {
        $modelClass = $this->modelClass;
        $model = $modelClass::query()->find($this->modelId);
        if ($model === null) {
            return;
        }

        if (! $model instanceof Chunkable) {
            throw new AtlasException(
                "[{$modelClass}] dispatched to ChunkContentJob but does not implement ".Chunkable::class.'.'
            );
        }

        $service->reconcile($model);
    }
}
