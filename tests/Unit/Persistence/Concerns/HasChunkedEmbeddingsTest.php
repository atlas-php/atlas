<?php

declare(strict_types=1);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Embeddings\Chunkable;
use Atlasphp\Atlas\Embeddings\ChunkableRegistry;
use Atlasphp\Atlas\Embeddings\Chunkers\Chunker;
use Atlasphp\Atlas\Embeddings\Chunkers\MarkdownChunker;
use Atlasphp\Atlas\Persistence\Concerns\HasChunkedEmbeddings;
use Atlasphp\Atlas\Persistence\Models\Chunk;
use Atlasphp\Atlas\Persistence\Schema\ChunkedEmbeddingColumns;
use Atlasphp\Atlas\Testing\EmbeddingsResponseFake;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FakeChunkableDoc extends Model implements Chunkable
{
    use HasChunkedEmbeddings;

    protected $table = 'fake_chunkable_docs';

    protected $guarded = [];

    public $timestamps = true;
}

class FakeChunkableArticle extends Model implements Chunkable
{
    use HasChunkedEmbeddings;

    protected string $chunkableField = 'description';

    protected $table = 'fake_chunkable_articles';

    protected $guarded = [];

    public $timestamps = true;
}

class FakeSoftDeletingChunkable extends Model implements Chunkable
{
    use HasChunkedEmbeddings;
    use SoftDeletes;

    protected $table = 'fake_soft_deleting_chunkables';

    protected $guarded = [];

    public $timestamps = true;
}

beforeEach(function () {
    Schema::dropIfExists('fake_chunkable_docs');
    Schema::dropIfExists('fake_chunkable_articles');
    Schema::dropIfExists('fake_soft_deleting_chunkables');

    Schema::create('fake_chunkable_docs', function (Blueprint $table) {
        $table->id();
        $table->text('body')->nullable();
        $table->timestamps();
        ChunkedEmbeddingColumns::add($table);
    });

    Schema::create('fake_chunkable_articles', function (Blueprint $table) {
        $table->id();
        $table->text('description')->nullable();
        $table->timestamps();
        ChunkedEmbeddingColumns::add($table);
    });

    Schema::create('fake_soft_deleting_chunkables', function (Blueprint $table) {
        $table->id();
        $table->text('body')->nullable();
        $table->timestamps();
        $table->softDeletes();
        ChunkedEmbeddingColumns::add($table);
    });
});

it('registers the model with ChunkableRegistry on trait boot', function () {
    // Touching the model class boots the trait.
    new FakeChunkableDoc;

    expect(app(ChunkableRegistry::class)->has(FakeChunkableDoc::class))->toBeTrue();
});

it('computes content_hash from the indexable field on save', function () {
    $doc = FakeChunkableDoc::create(['body' => 'Hello world.']);

    expect($doc->content_hash)->toBe(hash('xxh128', 'Hello world.'));
});

it('clears content_hash when the indexable field becomes empty', function () {
    $doc = FakeChunkableDoc::create(['body' => 'Hello world.']);
    expect($doc->content_hash)->not->toBeNull();

    $doc->update(['body' => '']);
    $doc->refresh();

    expect($doc->content_hash)->toBeNull();
});

it('updates content_hash only when the indexable field is dirty', function () {
    $doc = FakeChunkableDoc::create(['body' => 'First content.']);
    $originalHash = $doc->content_hash;

    $doc->update(['body' => 'First content.']);

    expect($doc->fresh()->content_hash)->toBe($originalHash);
});

it('cascades chunk deletion when the owning model is deleted', function () {
    $doc = FakeChunkableDoc::create(['body' => 'Hello.']);

    Chunk::create(array_merge([
        'chunkable_type' => $doc->getMorphClass(),
        'chunkable_id' => $doc->id,
        'ord' => 0,
        'heading_path' => null,
        'content' => 'Hello.',
        'content_hash' => 'abc',
        'token_count' => 2,
        'embedding_model' => 'text-embedding-3-small',
        'embedded_at' => now(),
    ], fakeChunkEmbedding()));

    expect(Chunk::query()->where('chunkable_id', $doc->id)->count())->toBe(1);

    $doc->delete();

    expect(Chunk::query()->where('chunkable_id', $doc->id)->count())->toBe(0);
});

it('respects the $chunkableField override', function () {
    $article = FakeChunkableArticle::create(['description' => 'My article.']);

    expect($article->getChunkableField())->toBe('description');
    expect($article->content_hash)->toBe(hash('xxh128', 'My article.'));
    expect($article->getChunkableContent())->toBe('My article.');
});

it('shouldBeChunked returns false on empty content', function () {
    $doc = new FakeChunkableDoc;
    expect($doc->shouldBeChunked())->toBeFalse();

    $doc->body = '';
    expect($doc->shouldBeChunked())->toBeFalse();

    $doc->body = 'Some content.';
    expect($doc->shouldBeChunked())->toBeTrue();
});

it('resolveChunker returns the MarkdownChunker default', function () {
    $doc = new FakeChunkableDoc;

    expect($doc->resolveChunker())->toBeInstanceOf(MarkdownChunker::class)
        ->toBeInstanceOf(Chunker::class);
});

it('chunks returns the MorphMany relation ordered by ord ascending', function () {
    $doc = FakeChunkableDoc::create(['body' => 'Hello.']);
    $other = FakeChunkableDoc::create(['body' => 'Other.']);

    // Seed three chunks for $doc in non-monotonic ord order, plus one for
    // an unrelated doc to verify the morph filter.
    foreach ([2, 0, 1] as $ord) {
        Chunk::create(array_merge([
            'chunkable_type' => $doc->getMorphClass(),
            'chunkable_id' => $doc->id,
            'ord' => $ord,
            'heading_path' => null,
            'content' => "doc chunk {$ord}",
            'content_hash' => "h{$ord}",
            'token_count' => 1,
            'embedding_model' => 'text-embedding-3-small',
            'embedded_at' => now(),
        ], fakeChunkEmbedding()));
    }
    Chunk::create(array_merge([
        'chunkable_type' => $other->getMorphClass(),
        'chunkable_id' => $other->id,
        'ord' => 0,
        'heading_path' => null,
        'content' => 'other chunk',
        'content_hash' => 'hOther',
        'token_count' => 1,
        'embedding_model' => 'text-embedding-3-small',
        'embedded_at' => now(),
    ], fakeChunkEmbedding()));

    $chunks = $doc->chunks()->get();

    expect($chunks)->toHaveCount(3);
    expect($chunks->pluck('ord')->all())->toBe([0, 1, 2]);
    expect($chunks->pluck('content')->all())->toBe(['doc chunk 0', 'doc chunk 1', 'doc chunk 2']);
});

it('chunkNow runs the reconciler synchronously without dispatching a job', function () {
    config([
        'atlas.defaults.embed.provider' => 'openai',
        'atlas.defaults.embed.model' => 'text-embedding-3-small',
        'atlas.persistence.enabled' => false, // confirm: works without persistence enabled
    ]);
    AtlasConfig::refresh();

    Atlas::fake([
        EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)]),
    ]);

    $doc = FakeChunkableDoc::create([
        'body' => "# Hello\n\nThis is the body of the document with some content to chunk.",
    ]);

    $doc->chunkNow();
    $doc->refresh();

    expect($doc->indexed_hash)->toBe($doc->content_hash);
    expect(Chunk::query()->where('chunkable_id', $doc->id)->count())->toBeGreaterThan(0);
});

// ─── SoftDeletes behaviour ──────────────────────────────────────────────────
//
// Both soft-delete ($model->delete() on a SoftDeletes model) and force-delete
// fire the trait's `deleting` hook, which removes chunks. Restore brings
// back an owner with no chunks — consumers call $model->chunkNow() if they
// want embeddings regenerated. Storage doesn't accumulate cruft from
// never-restored soft-deletes; the cost is paying the embedding API again
// on the rare restore. Locked in here so we don't regress this contract.

function seedChunkFor(Model $owner, string $content = 'chunk content'): Chunk
{
    return Chunk::create(array_merge([
        'chunkable_type' => $owner->getMorphClass(),
        'chunkable_id' => $owner->id,
        'ord' => 0,
        'heading_path' => null,
        'content' => $content,
        'content_hash' => hash('xxh128', $content),
        'token_count' => 1,
        'embedding_model' => 'text-embedding-3-small',
        'embedded_at' => now(),
    ], fakeChunkEmbedding()));
}

it('removes chunks on soft-delete via $model->delete()', function () {
    $doc = FakeSoftDeletingChunkable::create(['body' => 'Hello.']);
    seedChunkFor($doc);
    expect(Chunk::query()->where('chunkable_id', $doc->id)->count())->toBe(1);

    $doc->delete();

    expect($doc->trashed())->toBeTrue();
    expect(Chunk::query()->where('chunkable_id', $doc->id)->count())->toBe(0);
});

it('removes chunks on $model->forceDelete()', function () {
    $doc = FakeSoftDeletingChunkable::create(['body' => 'Hello.']);
    seedChunkFor($doc);
    expect(Chunk::query()->where('chunkable_id', $doc->id)->count())->toBe(1);

    $doc->forceDelete();

    expect(Chunk::query()->where('chunkable_id', $doc->id)->count())->toBe(0);
});

it('restore brings back model with no chunks (consumer calls chunkNow to regenerate)', function () {
    $doc = FakeSoftDeletingChunkable::create(['body' => 'Hello.']);
    seedChunkFor($doc);

    $doc->delete();
    $doc->restore();

    expect($doc->trashed())->toBeFalse();
    expect($doc->fresh()->chunks)->toHaveCount(0);
});
