<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Embeddings;

/**
 * Contract for Eloquent models that store a whole-record embedding.
 *
 * Models satisfy this interface by using the HasVectorEmbeddings trait
 * (which provides default implementations of every method here) and
 * declaring `implements VectorEmbeddable` on the model class.
 *
 * Implementing the interface explicitly is required for Atlas::similaritySearch()
 * to dispatch a model to the whole-record search path. The HasVectorEmbeddings
 * trait continues to work without the interface for callers using the trait's
 * own methods (auto-embedding on save, the `similarTo` scope) directly.
 */
interface VectorEmbeddable
{
    /**
     * @return array{column: string, source: string|array<int, string>}
     */
    public function embeddable(): array;

    /**
     * The text that gets fed to the embedding provider for this record.
     */
    public function getEmbeddableContent(): string;
}
