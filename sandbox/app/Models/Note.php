<?php

declare(strict_types=1);

namespace App\Models;

use Atlasphp\Atlas\Embeddings\VectorEmbeddable;
use Atlasphp\Atlas\Persistence\Concerns\HasVectorEmbeddings;
use Illuminate\Database\Eloquent\Model;

/**
 * Sandbox note model — whole-record embeddings via HasVectorEmbeddings.
 *
 * Mirrors the consumer-side flow: declare a `body` column, opt into the
 * trait + interface, and the trait's saving hook generates one embedding
 * per record on save. Atlas::similaritySearch(Note::class, $query) then
 * runs cosine-similarity search over those vectors.
 *
 * @property int $id
 * @property string $title
 * @property string|null $body
 * @property array<int, float>|null $embedding
 */
class Note extends Model implements VectorEmbeddable
{
    use HasVectorEmbeddings;

    protected $table = 'notes';

    protected $guarded = [];

    public $timestamps = true;

    protected function casts(): array
    {
        // `embedding` is intentionally NOT cast to array — the trait writes
        // a pgvector literal string via VectorQueryMacros::toVectorLiteral(),
        // and pgvector accepts that format directly. Adding an `array` cast
        // would JSON-encode the already-formatted literal on save and break
        // Postgres's vector parser. Read it back via DB queries or the
        // similarity-search macros, not as a PHP array on this model.
        return [
            'embedding_at' => 'datetime',
        ];
    }

    /**
     * Configure which column holds the embedding and which field is its source.
     *
     * @return array{column: string, source: string|array<int, string>}
     */
    public function embeddable(): array
    {
        return ['column' => 'embedding', 'source' => ['title', 'body']];
    }
}
