<?php

declare(strict_types=1);

namespace App\Models;

use Atlasphp\Atlas\Embeddings\Chunkable;
use Atlasphp\Atlas\Persistence\Concerns\HasChunkedEmbeddings;
use Illuminate\Database\Eloquent\Model;

/**
 * Sandbox project model.
 *
 * Used by sandbox/test-chunked-embeddings.php to exercise the chunked-embeddings
 * subsystem against the local PostgreSQL database. The `body` column holds
 * long-form markdown that the HasChunkedEmbeddings trait will chunk and embed.
 *
 * @property int $id
 * @property string $title
 * @property string|null $body
 * @property string|null $content_hash
 * @property string|null $indexed_hash
 */
class Project extends Model implements Chunkable
{
    use HasChunkedEmbeddings;

    protected $table = 'projects';

    protected $guarded = [];

    public $timestamps = true;
}
