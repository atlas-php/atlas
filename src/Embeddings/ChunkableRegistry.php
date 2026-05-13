<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Embeddings;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Registry of Eloquent model classes participating in chunked embeddings.
 *
 * Models register themselves either via the HasChunkedEmbeddings trait's
 * boot hook (which fires the first time the model is touched) or — and
 * this is what the artisan sweep relies on — via an explicit call in
 * the consumer's AppServiceProvider::boot():
 *
 *   Atlas::registerChunkable(\App\Models\Project::class);
 *
 * The atlas:chunk command iterates this registry to find dirty rows.
 * An empty registry means the command is a no-op, not an error.
 */
class ChunkableRegistry
{
    /** @var array<class-string<Model>, true> */
    protected array $classes = [];

    /**
     * Register a model class for chunked embedding sweeps.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function register(string $modelClass): void
    {
        if (! is_subclass_of($modelClass, Model::class)) {
            throw new InvalidArgumentException(
                "Chunkable [{$modelClass}] must extend ".Model::class
            );
        }

        if (! is_subclass_of($modelClass, Chunkable::class)) {
            throw new InvalidArgumentException(
                "Chunkable [{$modelClass}] must implement ".Chunkable::class.'. Add the HasChunkedEmbeddings trait and `implements Chunkable` to the model.'
            );
        }

        $this->classes[$modelClass] = true;
    }

    /**
     * @return array<int, class-string<Model>>
     */
    public function all(): array
    {
        return array_keys($this->classes);
    }

    public function has(string $modelClass): bool
    {
        return isset($this->classes[$modelClass]);
    }

    public function clear(): void
    {
        $this->classes = [];
    }
}
