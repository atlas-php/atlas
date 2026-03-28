<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Requests;

/**
 * Request object for reranking documents against a query.
 */
final class RerankRequest
{
    /**
     * @param  array<int, string|array<string, string>>  $documents
     * @param  array<string, mixed>  $providerOptions
     * @param  array<int, mixed>  $middleware
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $model,
        public readonly string $query,
        public readonly array $documents,
        public readonly ?int $topN = null,
        public readonly ?int $maxTokensPerDoc = null,
        public readonly array $providerOptions = [],
        public readonly array $middleware = [],
        public readonly array $meta = [],
    ) {}
}
