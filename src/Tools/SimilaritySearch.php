<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools;

use Atlasphp\Atlas\Embeddings\EmbeddingResolver;
use Atlasphp\Atlas\Schema\Schema;
use Closure;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Agent tool for performing similarity searches against Eloquent model vector columns.
 *
 * Can be constructed with a custom closure or built from an Eloquent model class
 * using the `usingModel()` factory. Accepts a text query from the agent and
 * returns matching records ordered by vector similarity.
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
     * @param  class-string<Model>  $model
     * @param  Closure|null  $query  Additional query constraints callback
     */
    public static function usingModel(
        string $model,
        string $column = 'embedding',
        float $minSimilarity = 0.5,
        int $limit = 10,
        ?Closure $query = null,
        ?string $embedProvider = null,
        ?string $embedModel = null,
    ): self {
        $instance = new self(function (string $input) use ($model, $column, $minSimilarity, $limit, $query, $embedProvider, $embedModel) {
            $resolver = app(EmbeddingResolver::class);

            $embedding = ($embedProvider || $embedModel)
                ? $resolver->resolveUsing($input, $embedProvider, $embedModel)
                : $resolver->resolve($input);

            $builder = $model::query();

            if ($query !== null) {
                $query($builder);
            }

            return $builder
                ->whereVectorSimilarTo($column, $embedding, $minSimilarity)
                ->limit($limit)
                ->get();
        });

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
