<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools;

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Embeddings\Chunkable;
use Atlasphp\Atlas\Embeddings\EmbeddingResolver;
use Atlasphp\Atlas\Embeddings\VectorEmbeddable;
use Atlasphp\Atlas\Schema\Schema;
use Closure;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Agent tool for performing similarity searches against an Eloquent model.
 *
 * Built with `usingModel($class)`. The model can use either HasChunkedEmbeddings
 * (chunked search) or HasVectorEmbeddings (whole-record search) — the tool
 * auto-dispatches by inspecting the model's traits. Same JSON-shape result
 * for the agent either way (Collection<SearchResult>).
 */
class SimilaritySearch extends Tool
{
    protected string $toolName = 'similarity_search';

    protected string $toolDescription = 'Search for similar content using semantic similarity.';

    protected ?Closure $using;

    /**
     * @param  Closure|null  $using  Custom search callback receiving (string $query)
     */
    public function __construct(?Closure $using = null)
    {
        $this->using = $using;
    }

    /**
     * Create a SimilaritySearch tool from an Eloquent model.
     *
     * The model's traits determine the search mode:
     *   - HasChunkedEmbeddings / Chunkable → searches atlas_chunks
     *   - HasVectorEmbeddings / VectorEmbeddable → searches the model's embedding column
     *   - Chunkable wins if a model uses both
     *
     * The `query` callback receives an Eloquent Builder for the OWNER model
     * (even in chunk-mode, where it's applied as a subquery). Do NOT call
     * `->select(...)` inside the callback — the service expects to select
     * only the primary key from this builder.
     *
     * @param  class-string<Model>  $model
     * @param  string|null  $column  Embedding column. If null, resolved from the model's `embeddable()['column']` when it implements VectorEmbeddable; otherwise defaults to `'embedding'`. Only consulted on the legacy path; the unified path always reads from `embeddable()`.
     * @param  float|null  $minSimilarity  Cosine similarity floor (0.0–1.0). Null disables the floor.
     * @param  int  $limit  Max results returned to the agent.
     * @param  Closure|null  $query  Additional owner-scope constraints. Receives an Eloquent Builder.
     * @param  string|null  $embedProvider  Override the default embed provider (only respected for legacy whole-record path).
     * @param  string|null  $embedModel  Override the default embed model.
     */
    public static function usingModel(
        string $model,
        ?string $column = null,
        ?float $minSimilarity = null,
        int $limit = 10,
        ?Closure $query = null,
        ?string $embedProvider = null,
        ?string $embedModel = null,
    ): self {
        // For models that use one of atlas's standard embedding traits,
        // delegate to Atlas::similaritySearch() so we get auto-dispatch
        // (chunked vs whole-record) for free. The legacy custom-column /
        // explicit-provider path keeps its old behavior for callers that
        // configure SimilaritySearch on models without a standard trait.
        $usesStandardTraits = is_subclass_of($model, Chunkable::class)
            || is_subclass_of($model, VectorEmbeddable::class);

        if ($usesStandardTraits && $embedProvider === null && $embedModel === null) {
            $instance = new self(function (string $input) use ($model, $minSimilarity, $limit, $query) {
                // The predicate is intentionally `!== null` (not falsy) so
                // legitimate floor values like 0.0 and limit 0 pass through.
                return Atlas::similaritySearch($model, $input, array_filter([
                    'limit' => $limit,
                    'min_similarity' => $minSimilarity,
                    'where' => $query,
                ], fn ($v): bool => $v !== null));
            });
        } else {
            // Legacy path: explicit provider/model override or model lacks a
            // standard atlas trait. Runs the column-based query directly.
            //
            // Column resolution priority:
            //   1. Caller-supplied $column (if not null) — explicit wins.
            //   2. Model's embeddable()['column'] when VectorEmbeddable.
            //   3. 'embedding' fallback for non-trait models.
            // Without this fallback, a VectorEmbeddable model declaring a
            // non-default column would silently query the wrong column the
            // moment a consumer added embedProvider/embedModel — surprising
            // and hard to debug.
            $resolvedColumn = $column;
            if ($resolvedColumn === null) {
                $resolvedColumn = is_subclass_of($model, VectorEmbeddable::class)
                    ? (new $model)->embeddable()['column']
                    : 'embedding';
            }

            $instance = new self(function (string $input) use ($model, $resolvedColumn, $minSimilarity, $limit, $query, $embedProvider, $embedModel) {
                $resolver = app(EmbeddingResolver::class);

                $embedding = ($embedProvider || $embedModel)
                    ? $resolver->resolveUsing($input, $embedProvider, $embedModel)
                    : $resolver->resolve($input);

                $builder = $model::query();

                if ($query !== null) {
                    $query($builder);
                }

                if ($minSimilarity !== null) {
                    $builder->whereVectorSimilarTo($resolvedColumn, $embedding, $minSimilarity);
                } else {
                    $builder->orderByVectorDistance($resolvedColumn, $embedding);
                }

                return $builder->limit($limit)->get();
            });
        }

        $shortName = class_basename($model);
        $instance->toolDescription = "Search {$shortName} records by semantic similarity.";

        return $instance;
    }

    /**
     * Override the tool name.
     */
    public function withName(string $name): static
    {
        $this->toolName = $name;

        return $this;
    }

    /**
     * Override the tool description.
     */
    public function withDescription(string $description): static
    {
        $this->toolDescription = $description;

        return $this;
    }

    public function name(): string
    {
        return $this->toolName;
    }

    public function description(): string
    {
        return $this->toolDescription;
    }

    public function parameters(): array
    {
        return [
            Schema::string('query', 'The search query to find similar content.'),
        ];
    }

    public function handle(array $args, array $context): mixed
    {
        if ($this->using === null) {
            throw new RuntimeException('No search callback provided. Use the constructor or usingModel() to configure.');
        }

        return ($this->using)($args['query']);
    }
}
