<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses;

/**
 * A single reranked document result with its relevance score.
 */
class RerankResult
{
    public function __construct(
        public readonly int $index,
        public readonly float $score,
        public readonly string $document,
    ) {}
}
