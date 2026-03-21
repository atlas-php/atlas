<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Database;

use Atlasphp\Atlas\Embeddings\EmbeddingResolver;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;

/**
 * Registers pgvector-aware query macros on the base Query Builder.
 *
 * All macros use the cosine distance operator (`<=>`) which matches
 * existing HNSW indexes. Accepts string (auto-resolved via EmbeddingResolver)
 * or pre-computed float arrays.
 */
class VectorQueryMacros
{
    /**
     * Register all vector macros on the query builder.
     */
    public static function register(): void
    {
        if (! static::isPgvectorAvailable()) {
            return;
        }

        if (Builder::hasMacro('whereVectorSimilarTo')) {
            return;
        }

        static::registerWhereVectorSimilarTo();
        static::registerWhereVectorDistanceLessThan();
        static::registerSelectVectorDistance();
        static::registerOrderByVectorDistance();
    }

    /**
     * Check if the current database connection supports pgvector.
     */
    public static function isPgvectorAvailable(): bool
    {
        return config('database.default') === 'pgsql'
            || config('database.connections.'.config('database.default').'.driver') === 'pgsql';
    }

    /**
     * Resolve an embedding from string or pass through an array.
     *
     * @param  string|array<int, float>  $embedding
     * @return array<int, float>
     */
    public static function resolveEmbedding(string|array $embedding): array
    {
        if (is_array($embedding)) {
            return $embedding;
        }

        return app(EmbeddingResolver::class)->resolve($embedding);
    }

    /**
     * Format a vector array as a pgvector literal string.
     *
     * @param  array<int, float>  $vector
     */
    public static function toVectorLiteral(array $vector): string
    {
        return '['.implode(',', array_map(fn (float|int $v): string => (string) $v, $vector)).']';
    }

    /**
     * Validate a column name to prevent SQL injection on interpolated identifiers.
     * Allows dots for qualified names (e.g., table.column).
     */
    public static function validateColumnName(string $column): void
    {
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $column)) {
            throw new InvalidArgumentException("Invalid column name: {$column}");
        }
    }

    /**
     * Validate an alias name — stricter than column names (no dots allowed).
     */
    public static function validateAliasName(string $alias): void
    {
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $alias)) {
            throw new InvalidArgumentException("Invalid alias name: {$alias}");
        }
    }

    /**
     * Filter + order by cosine similarity.
     */
    protected static function registerWhereVectorSimilarTo(): void
    {
        Builder::macro('whereVectorSimilarTo', function (string $column, string|array $embedding, float $minSimilarity = 0.5): Builder {
            VectorQueryMacros::validateColumnName($column);

            $vector = VectorQueryMacros::resolveEmbedding($embedding);
            $literal = VectorQueryMacros::toVectorLiteral($vector);
            $maxDistance = 1.0 - $minSimilarity;

            /** @var Builder $this */
            if ($maxDistance < 1.0) {
                $this->whereRaw("{$column} <=> ?::vector <= ?", [$literal, $maxDistance]);
            }

            return $this->orderByRaw("{$column} <=> ?::vector ASC", [$literal]);
        });
    }

    /**
     * Filter by maximum cosine distance.
     */
    protected static function registerWhereVectorDistanceLessThan(): void
    {
        Builder::macro('whereVectorDistanceLessThan', function (string $column, string|array $embedding, float $maxDistance): Builder {
            VectorQueryMacros::validateColumnName($column);

            $vector = VectorQueryMacros::resolveEmbedding($embedding);
            $literal = VectorQueryMacros::toVectorLiteral($vector);

            /** @var Builder $this */
            return $this->whereRaw("{$column} <=> ?::vector <= ?", [$literal, $maxDistance]);
        });
    }

    /**
     * Add a computed distance column to the select.
     */
    protected static function registerSelectVectorDistance(): void
    {
        Builder::macro('selectVectorDistance', function (string $column, string|array $embedding, string $as = 'vector_distance'): Builder {
            VectorQueryMacros::validateColumnName($column);
            VectorQueryMacros::validateAliasName($as);

            $vector = VectorQueryMacros::resolveEmbedding($embedding);
            $literal = VectorQueryMacros::toVectorLiteral($vector);

            /** @var Builder $this */
            return $this->selectRaw("({$column} <=> ?::vector) AS {$as}", [$literal]);
        });
    }

    /**
     * Order by cosine distance.
     */
    protected static function registerOrderByVectorDistance(): void
    {
        Builder::macro('orderByVectorDistance', function (string $column, string|array $embedding, string $direction = 'asc'): Builder {
            VectorQueryMacros::validateColumnName($column);

            $vector = VectorQueryMacros::resolveEmbedding($embedding);
            $literal = VectorQueryMacros::toVectorLiteral($vector);

            $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

            /** @var Builder $this */
            return $this->orderByRaw("{$column} <=> ?::vector {$direction}", [$literal]);
        });
    }
}
