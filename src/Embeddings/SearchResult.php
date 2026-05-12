<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Embeddings;

use Illuminate\Database\Eloquent\Model;

/**
 * One result from Atlas::similaritySearch().
 *
 * Works for both search modes:
 *   - chunked embeddings: `record` is the parent model, `content` is the
 *     chunk text, `headingPath` and `ord` are populated.
 *   - whole-record embeddings (HasVectorEmbeddings): `record` is the matched
 *     model, `content` is what was embedded (getEmbeddableContent()), and
 *     `headingPath`/`ord` are null.
 *
 * Returned in a Collection ordered by similarity, most similar first.
 */
final readonly class SearchResult
{
    public function __construct(
        public Model $record,
        public string $content,
        public float $similarity,
        public ?string $headingPath = null,
        public ?int $ord = null,
    ) {}
}
