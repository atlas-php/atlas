<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Concerns;

use Atlasphp\Atlas\Embeddings\EmbeddingResolver;
use Atlasphp\Atlas\Embeddings\VectorEmbeddable;
use Atlasphp\Atlas\Embeddings\VectorQueryMacros;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Provides vector embedding support for Eloquent models.
 *
 * Adds auto-embedding on save when source fields change, manual embedding
 * generation, and a `similarTo` Eloquent scope for similarity queries.
 *
 * @phpstan-require-extends Model
 *
 * @phpstan-require-implements VectorEmbeddable
 */
trait HasVectorEmbeddings
{
    /**
     * Define the embeddable column and source field(s).
     *
     * Override in model to customize. Source can be a string (single field)
     * or array of field names (concatenated with double newline).
     *
     * @return array{column: string, source: string|array<int, string>}
     */
    public function embeddable(): array
    {
        return ['column' => 'embedding', 'source' => 'content'];
    }

    /**
     * Whether auto-embedding on save is enabled.
     *
     * Models can set `protected bool $autoEmbed = false` to disable.
     */
    public function isAutoEmbedEnabled(): bool
    {
        return ! property_exists($this, 'autoEmbed') || $this->autoEmbed !== false;
    }

    /**
     * Boot the trait — register saving event for auto-embedding.
     */
    public static function bootHasVectorEmbeddings(): void
    {
        static::saving(function (self $model): void {
            if ($model->isAutoEmbedEnabled() && $model->shouldGenerateEmbedding()) {
                $model->generateEmbedding();
            }
        });
    }

    /**
     * Extract text content from the configured source field(s).
     */
    public function getEmbeddableContent(): string
    {
        $source = $this->embeddable()['source'];
        $fields = is_array($source) ? $source : [$source];

        $parts = array_filter(
            array_map(fn (string $field): string => trim((string) $this->getAttribute($field)), $fields),
            fn (string $value): bool => $value !== '',
        );

        return implode("\n\n", $parts);
    }

    /**
     * Determine if the embedding should be (re)generated.
     *
     * The trait being applied to a model is itself the opt-in — using the
     * trait means the consumer wants embeddings on save. Disable per-model
     * with `protected bool $autoEmbed = false;` if needed.
     */
    public function shouldGenerateEmbedding(): bool
    {
        $source = $this->embeddable()['source'];
        $fields = is_array($source) ? $source : [$source];

        foreach ($fields as $field) {
            if ($this->isDirty($field)) {
                return $this->getEmbeddableContent() !== '';
            }
        }

        return false;
    }

    /**
     * Generate embedding using configured defaults.
     */
    public function generateEmbedding(): static
    {
        /** @var EmbeddingResolver $resolver */
        $resolver = app(EmbeddingResolver::class);

        return $this->storeEmbedding($resolver->resolve($this->getEmbeddableContent()));
    }

    /**
     * Generate embedding with explicit provider and model.
     */
    public function generateEmbeddingUsing(?string $provider = null, ?string $model = null): static
    {
        /** @var EmbeddingResolver $resolver */
        $resolver = app(EmbeddingResolver::class);

        return $this->storeEmbedding(
            $resolver->resolveUsing($this->getEmbeddableContent(), $provider, $model)
        );
    }

    /**
     * Validate the vector against the configured dimension, then write it to
     * the embeddable column.
     *
     * The column is sized to atlas.embeddings.dimensions; a mismatched vector
     * would be rejected by pgvector with a cryptic error, so fail early with
     * actionable guidance instead.
     *
     * @param  array<int, float>  $vector
     */
    protected function storeEmbedding(array $vector): static
    {
        $expected = (int) config('atlas.embeddings.dimensions');
        if (count($vector) !== $expected) {
            throw AtlasException::dimensionMismatch($expected, count($vector));
        }

        $this->setAttribute($this->embeddable()['column'], VectorQueryMacros::toVectorLiteral($vector));
        $this->setAttribute('embedding_at', now());

        return $this;
    }

    /**
     * Eloquent scope for similarity search.
     *
     * @param  Builder<static>  $query
     * @param  string|array<int, float>  $embedding
     */
    public function scopeSimilarTo(Builder $query, string|array $embedding, float $minSimilarity = 0.5): void
    {
        $column = $this->embeddable()['column'];

        $query->whereVectorSimilarTo($column, $embedding, $minSimilarity);
    }
}
