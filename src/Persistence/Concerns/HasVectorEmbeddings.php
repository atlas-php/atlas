<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Concerns;

use Atlasphp\Atlas\Embeddings\EmbeddingResolver;
use Atlasphp\Atlas\Embeddings\VectorQueryMacros;
use Illuminate\Database\Eloquent\Builder;

/**
 * Provides vector embedding support for Eloquent models.
 *
 * Adds auto-embedding on save when source fields change, manual embedding
 * generation, and a `similarTo` Eloquent scope for similarity queries.
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
     */
    public function shouldGenerateEmbedding(): bool
    {
        if (! config('atlas.persistence.enabled', false)) {
            return false;
        }

        $source = $this->embeddable()['source'];
        $fields = is_array($source) ? $source : [$source];

        $isDirty = false;
        foreach ($fields as $field) {
            if ($this->isDirty($field)) {
                $isDirty = true;
                break;
            }
        }

        if (! $isDirty) {
            return false;
        }

        return $this->getEmbeddableContent() !== '';
    }

    /**
     * Generate embedding using configured defaults.
     */
    public function generateEmbedding(): static
    {
        $config = $this->embeddable();
        $content = $this->getEmbeddableContent();

        /** @var EmbeddingResolver $resolver */
        $resolver = app(EmbeddingResolver::class);
        $vector = $resolver->resolve($content);

        $this->setAttribute($config['column'], VectorQueryMacros::toVectorLiteral($vector));
        $this->setAttribute('embedding_at', now());

        return $this;
    }

    /**
     * Generate embedding with explicit provider and model.
     */
    public function generateEmbeddingUsing(?string $provider = null, ?string $model = null): static
    {
        $config = $this->embeddable();
        $content = $this->getEmbeddableContent();

        /** @var EmbeddingResolver $resolver */
        $resolver = app(EmbeddingResolver::class);
        $vector = $resolver->resolveUsing($content, $provider, $model);

        $this->setAttribute($config['column'], VectorQueryMacros::toVectorLiteral($vector));
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
