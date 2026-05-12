<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Services;

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Embeddings\EmbeddingResolver;
use Atlasphp\Atlas\Embeddings\SearchResult;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Persistence\Concerns\ResolvesChunkModel;
use Atlasphp\Atlas\Persistence\Models\Chunk;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Runs similarity search against atlas_chunks.
 *
 * Embeds the query string, runs a pgvector cosine query scoped to the
 * given chunkable model class, optionally filters by a consumer-supplied
 * scope on the owner table, and hydrates the matched parent models.
 *
 * Requires PostgreSQL + pgvector. On other database drivers the vector
 * macros will not be registered and the query will fail loudly.
 */
class ChunkSearchService
{
    use ResolvesChunkModel;

    public function __construct(
        protected readonly AtlasConfig $config,
        protected readonly EmbeddingResolver $resolver,
    ) {}

    /**
     * @param  class-string<Model>  $chunkable
     * @param  array{limit?: int, min_similarity?: float|null, where?: Closure}  $options
     * @return Collection<int, SearchResult>
     */
    public function search(string $chunkable, string $query, array $options = []): Collection
    {
        /** @var Model $sample */
        $sample = new $chunkable;
        $morphClass = $sample->getMorphClass();

        $limit = (int) ($options['limit'] ?? 5);
        $minSimilarity = $options['min_similarity'] ?? null;
        $whereCallback = $options['where'] ?? null;

        if ($minSimilarity !== null && ($minSimilarity < 0.0 || $minSimilarity > 1.0)) {
            throw new \InvalidArgumentException(
                "min_similarity must be between 0.0 and 1.0, got {$minSimilarity}."
            );
        }

        $vector = $this->resolver->resolve($query);

        $chunkModel = $this->chunkModel();
        $tableName = (new $chunkModel)->getTable();
        // selectRaw inside selectVectorDistance overrides the implicit `*`,
        // so we ask for the chunks columns explicitly alongside the distance.
        $chunkQuery = $chunkModel::query()
            ->select("{$tableName}.*")
            ->where('chunkable_type', $morphClass)
            ->selectVectorDistance('embedding', $vector, 'distance')
            ->limit($limit);

        if ($minSimilarity !== null) {
            $chunkQuery->whereVectorSimilarTo('embedding', $vector, (float) $minSimilarity);
        } else {
            $chunkQuery->orderByVectorDistance('embedding', $vector);
        }

        if ($whereCallback instanceof Closure) {
            // Apply the consumer's scope as a subquery against the owner table
            // rather than materializing matching IDs into PHP. On a large owner
            // table this matters: pulling tens of thousands of IDs through
            // PHP before the chunk query runs would be a production hazard.
            $ownerBuilder = $chunkable::query()->select($sample->getKeyName());
            $whereCallback($ownerBuilder);
            $chunkQuery->whereIn('chunkable_id', $ownerBuilder);
        }

        /** @var Collection<int, Chunk> $rows */
        $rows = $chunkQuery->get();
        if ($rows->isEmpty()) {
            /** @var Collection<int, SearchResult> $empty */
            $empty = new Collection;

            return $empty;
        }

        $ownerIds = $rows->pluck('chunkable_id')->unique()->all();
        /** @var Collection<int, Model> $owners */
        $owners = $chunkable::query()->whereIn($sample->getKeyName(), $ownerIds)->get()->keyBy($sample->getKeyName());

        return $rows
            ->filter(fn (Chunk $row): bool => $owners->has($row->getAttribute('chunkable_id')))
            ->map(function (Chunk $row) use ($owners): SearchResult {
                /** @var Model $owner */
                $owner = $owners->get($row->getAttribute('chunkable_id'));
                $rawDistance = $row->getAttribute('distance');
                if ($rawDistance === null) {
                    throw new AtlasException(
                        'Chunk row returned without a distance column — VectorQueryMacros may not be registered.'
                    );
                }
                $ord = $row->getAttribute('ord');

                return new SearchResult(
                    record: $owner,
                    content: (string) $row->getAttribute('content'),
                    similarity: 1.0 - (float) $rawDistance,
                    headingPath: $row->getAttribute('heading_path'),
                    ord: $ord !== null ? (int) $ord : null,
                );
            })
            ->sortByDesc('similarity')
            ->values();
    }
}
