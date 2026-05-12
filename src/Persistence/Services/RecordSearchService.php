<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Services;

use Atlasphp\Atlas\Embeddings\EmbeddingResolver;
use Atlasphp\Atlas\Embeddings\SearchResult;
use Atlasphp\Atlas\Embeddings\VectorEmbeddable;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Similarity search against models using whole-record embeddings.
 *
 * Pairs with HasVectorEmbeddings — the model declares its embeddable column
 * via embeddable()['column'] and the source text via embeddable()['source'].
 * This service embeds the query string, runs a pgvector cosine query over
 * that column, and wraps the matched models in SearchResult value objects.
 *
 * For chunked-embedding search (one record → many chunks), use
 * ChunkSearchService instead. Atlas::similaritySearch() dispatches between
 * the two based on which trait the model uses.
 */
class RecordSearchService
{
    public function __construct(
        protected readonly EmbeddingResolver $resolver,
    ) {}

    /**
     * @param  class-string<Model>  $model
     * @param  array{limit?: int, min_similarity?: float|null, where?: Closure}  $options
     * @return Collection<int, SearchResult>
     */
    public function search(string $model, string $query, array $options = []): Collection
    {
        $sample = new $model;
        if (! $sample instanceof VectorEmbeddable) {
            throw new AtlasException(
                "[{$model}] is not searchable as a record — implement VectorEmbeddable on the model."
            );
        }
        $config = $sample->embeddable();
        $column = $config['column'];
        $tableName = $sample->getTable();

        $limit = (int) ($options['limit'] ?? 5);
        $minSimilarity = $options['min_similarity'] ?? null;
        $whereCallback = $options['where'] ?? null;

        if ($minSimilarity !== null && ($minSimilarity < 0.0 || $minSimilarity > 1.0)) {
            throw new \InvalidArgumentException(
                "min_similarity must be between 0.0 and 1.0, got {$minSimilarity}."
            );
        }

        $vector = $this->resolver->resolve($query);

        // selectVectorDistance uses selectRaw which overrides the implicit `*`,
        // so we ask for the owner table's columns explicitly alongside distance.
        $builder = $model::query()
            ->select("{$tableName}.*")
            ->selectVectorDistance($column, $vector, 'distance')
            ->limit($limit);

        if ($minSimilarity !== null) {
            $builder->whereVectorSimilarTo($column, $vector, (float) $minSimilarity);
        } else {
            $builder->orderByVectorDistance($column, $vector);
        }

        if ($whereCallback instanceof Closure) {
            $whereCallback($builder);
        }

        /** @var Collection<int, Model> $rows */
        $rows = $builder->get();

        return $rows->map(function (Model $record): SearchResult {
            if (! $record instanceof VectorEmbeddable) {
                throw new AtlasException(
                    'Record search returned a model that no longer implements VectorEmbeddable: '.$record::class
                );
            }
            $rawDistance = $record->getAttribute('distance');
            if ($rawDistance === null) {
                throw new AtlasException(
                    'Record row returned without a distance column — VectorQueryMacros may not be registered.'
                );
            }

            return new SearchResult(
                record: $record,
                content: $record->getEmbeddableContent(),
                similarity: 1.0 - (float) $rawDistance,
            );
        })->sortByDesc('similarity')->values();
    }
}
