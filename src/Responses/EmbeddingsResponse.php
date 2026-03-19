<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses;

/**
 * Response from an embeddings generation request.
 */
class EmbeddingsResponse
{
    /**
     * @param  array<int, array<int, float>>  $embeddings
     */
    public function __construct(
        public readonly array $embeddings,
        public readonly Usage $usage,
    ) {}
}
