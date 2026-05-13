<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Embeddings;

use Illuminate\Database\Eloquent\Model;
use JsonSerializable;

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
 *
 * Implements JsonSerializable so the SimilaritySearch agent tool emits a
 * compact LLM-friendly payload (id + type + content + ranking) instead of
 * dumping every column on the hydrated Eloquent record. PHP property access
 * (`$result->record->title`) is unaffected — the trim only applies to JSON.
 */
final readonly class SearchResult implements JsonSerializable
{
    public function __construct(
        public Model $record,
        public string $content,
        public float $similarity,
        public ?string $headingPath = null,
        public ?int $ord = null,
    ) {}

    /**
     * @return array{id: int|string|null, type: string, content: string, similarity: float, heading_path: ?string, ord: ?int}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->record->getKey(),
            'type' => $this->record->getMorphClass(),
            'content' => $this->content,
            'similarity' => $this->similarity,
            'heading_path' => $this->headingPath,
            'ord' => $this->ord,
        ];
    }
}
