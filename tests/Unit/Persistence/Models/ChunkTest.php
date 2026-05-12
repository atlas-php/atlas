<?php

declare(strict_types=1);

use Atlasphp\Atlas\Embeddings\Chunkable;
use Atlasphp\Atlas\Persistence\Concerns\HasChunkedEmbeddings;
use Atlasphp\Atlas\Persistence\Models\Chunk;
use Atlasphp\Atlas\Persistence\Schema\ChunkedEmbeddingColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FakeChunkOwnerDoc extends Model implements Chunkable
{
    use HasChunkedEmbeddings;

    protected $table = 'fake_chunk_owner_docs';

    protected $guarded = [];

    public $timestamps = true;
}

beforeEach(function () {
    Schema::dropIfExists('fake_chunk_owner_docs');
    Schema::create('fake_chunk_owner_docs', function (Blueprint $table) {
        $table->id();
        $table->text('body')->nullable();
        $table->timestamps();
        ChunkedEmbeddingColumns::add($table);
    });
});

it('chunkable returns a MorphTo relation', function () {
    $chunk = new Chunk;

    expect($chunk->chunkable())->toBeInstanceOf(MorphTo::class);
});

it('chunkable resolves to the polymorphic owner model', function () {
    $doc = FakeChunkOwnerDoc::create(['body' => 'Hello.']);
    $chunk = Chunk::create(array_merge([
        'chunkable_type' => $doc->getMorphClass(),
        'chunkable_id' => $doc->id,
        'ord' => 0,
        'heading_path' => null,
        'content' => 'chunk body',
        'content_hash' => hash('xxh128', 'chunk body'),
        'token_count' => 2,
        'embedding_model' => 'text-embedding-3-small',
        'embedded_at' => now(),
    ], fakeChunkEmbedding()));

    $owner = $chunk->fresh()->chunkable;

    expect($owner)->toBeInstanceOf(FakeChunkOwnerDoc::class);
    expect($owner->id)->toBe($doc->id);
    expect($owner->body)->toBe('Hello.');
});
