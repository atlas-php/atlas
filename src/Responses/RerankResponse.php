<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses;

/**
 * Response from a reranking request containing scored results in relevance order.
 */
class RerankResponse
{
    /**
     * @param  array<int, RerankResult>  $results
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly array $results,
        public readonly array $meta = [],
    ) {}

    /**
     * Get the original document indexes in relevance order.
     *
     * @return array<int, int>
     */
    public function indexes(): array
    {
        return array_map(fn (RerankResult $r): int => $r->index, $this->results);
    }

    /**
     * Get the top result, or null if empty.
     */
    public function top(): ?RerankResult
    {
        return $this->results[0] ?? null;
    }

    /**
     * Get the top N results.
     *
     * @return array<int, RerankResult>
     */
    public function topN(int $n): array
    {
        return array_slice($this->results, 0, $n);
    }

    /**
     * Filter results above a minimum score threshold.
     *
     * @return array<int, RerankResult>
     */
    public function aboveScore(float $threshold): array
    {
        return array_values(array_filter(
            $this->results,
            fn (RerankResult $r): bool => $r->score >= $threshold,
        ));
    }

    /**
     * Reorder an array of items by relevance using the reranked indexes.
     *
     * @template T
     *
     * @param  array<int, T>  $items
     * @return array<int, T>
     */
    public function reorder(array $items): array
    {
        return array_map(fn (int $index): mixed => $items[$index], $this->indexes());
    }
}
