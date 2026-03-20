<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing;

use Atlasphp\Atlas\Responses\RerankResponse;
use Atlasphp\Atlas\Responses\RerankResult;

/**
 * Fluent builder for creating fake RerankResponse objects in tests.
 */
class RerankResponseFake
{
    /** @var array<int, RerankResult> */
    protected array $results;

    /** @var array<string, mixed> */
    protected array $meta = [];

    public function __construct()
    {
        $this->results = [
            new RerankResult(0, 0.95, 'Document 1'),
            new RerankResult(2, 0.80, 'Document 3'),
            new RerankResult(1, 0.60, 'Document 2'),
        ];
    }

    public static function make(): self
    {
        return new self;
    }

    /**
     * Create a fake with a specific number of results and optional scores.
     *
     * @param  array<int, float>|null  $scores
     */
    public static function withCount(int $count, ?array $scores = null): self
    {
        $fake = new self;

        $results = [];
        for ($i = 0; $i < $count; $i++) {
            $score = $scores[$i] ?? round(1.0 - ($i * 0.1), 2);
            $results[] = new RerankResult($i, $score, "Document {$i}");
        }

        $fake->results = $results;

        return $fake;
    }

    /**
     * @param  array<int, RerankResult>  $results
     */
    public function withResults(array $results): static
    {
        $this->results = $results;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function withMeta(array $meta): static
    {
        $this->meta = $meta;

        return $this;
    }

    public function toResponse(): RerankResponse
    {
        return new RerankResponse(
            results: $this->results,
            meta: $this->meta,
        );
    }
}
