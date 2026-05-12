<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Models;

use Atlasphp\Atlas\Persistence\Concerns\HasAtlasTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Class Chunk
 *
 * One chunk of a larger piece of content (markdown body, long-form text)
 * with its own embedding vector. Belongs polymorphically to any model
 * using the HasChunkedEmbeddings trait.
 *
 * The (chunkable_type, content_hash) pair is the dedup key the reconciler
 * uses to decide what to re-embed when content is edited.
 *
 * @property int $id
 * @property string $chunkable_type
 * @property int $chunkable_id
 * @property int $ord
 * @property string|null $heading_path
 * @property string $content
 * @property string $content_hash
 * @property int $token_count
 * @property string $embedding_model
 * @property array<int, float>|null $embedding
 * @property Carbon $embedded_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Chunk extends Model
{
    use HasAtlasTable;

    protected $table = 'chunks';

    protected $fillable = [
        'chunkable_type',
        'chunkable_id',
        'ord',
        'heading_path',
        'content',
        'content_hash',
        'token_count',
        'embedding_model',
        'embedding',
        'embedded_at',
    ];

    protected function casts(): array
    {
        return [
            'ord' => 'integer',
            'token_count' => 'integer',
            'embedding' => 'array',
            'embedded_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function chunkable(): MorphTo
    {
        return $this->morphTo();
    }
}
