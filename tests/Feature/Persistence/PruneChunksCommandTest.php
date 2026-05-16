<?php

declare(strict_types=1);

use Atlasphp\Atlas\Embeddings\Chunkable;
use Atlasphp\Atlas\Embeddings\ChunkableRegistry;
use Atlasphp\Atlas\Persistence\Concerns\HasChunkedEmbeddings;
use Atlasphp\Atlas\Persistence\Models\Chunk;
use Atlasphp\Atlas\Persistence\Schema\ChunkedEmbeddingColumns;
use Atlasphp\Atlas\Queue\Jobs\ChunkContentJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

class FakePruneDoc extends Model implements Chunkable
{
    use HasChunkedEmbeddings;

    protected $table = 'fake_prune_docs';

    protected $guarded = [];

    public $timestamps = true;
}

class FakePruneSoftDeletingDoc extends Model implements Chunkable
{
    use HasChunkedEmbeddings;
    use SoftDeletes;

    protected $table = 'fake_prune_soft_docs';

    protected $guarded = [];

    public $timestamps = true;
}

beforeEach(function () {
    Schema::dropIfExists('fake_prune_docs');
    Schema::create('fake_prune_docs', function (Blueprint $table) {
        $table->id();
        $table->text('body')->nullable();
        $table->timestamps();
        ChunkedEmbeddingColumns::add($table);
    });

    Schema::dropIfExists('fake_prune_soft_docs');
    Schema::create('fake_prune_soft_docs', function (Blueprint $table) {
        $table->id();
        $table->text('body')->nullable();
        $table->timestamps();
        $table->softDeletes();
        ChunkedEmbeddingColumns::add($table);
    });

    app(ChunkableRegistry::class)->clear();
});

it('exits cleanly when no chunkable models are registered', function () {
    $this->artisan('atlas:prune-chunks')->assertExitCode(0);
});

it('deletes chunks whose owner row no longer exists', function () {
    Queue::fake();

    app(ChunkableRegistry::class)->register(FakePruneDoc::class);
    $a = FakePruneDoc::create(['body' => 'A body']);
    $b = FakePruneDoc::create(['body' => 'B body']);

    foreach ([$a, $b] as $doc) {
        Chunk::create(array_merge([
            'chunkable_type' => $doc->getMorphClass(),
            'chunkable_id' => $doc->id,
            'ord' => 0,
            'heading_path' => null,
            'content' => 'chunk for '.$doc->id,
            'content_hash' => hash('xxh128', (string) $doc->id),
            'token_count' => 1,
            'embedding_model' => 'text-embedding-3-small',
            'embedded_at' => now(),
        ], fakeChunkEmbedding()));
    }
    expect(Chunk::query()->count())->toBe(2);

    FakePruneDoc::query()->whereKey($b->id)->delete();
    expect(Chunk::query()->where('chunkable_id', $b->id)->count())->toBe(1);

    $this->artisan('atlas:prune-chunks')
        ->expectsOutputToContain('pruned 1 orphan')
        ->assertExitCode(0);

    expect(Chunk::query()->where('chunkable_id', $b->id)->count())->toBe(0);
    expect(Chunk::query()->where('chunkable_id', $a->id)->count())->toBe(1);
});

it('does not dispatch any ChunkContentJob (orphan cleanup is its sole job)', function () {
    Queue::fake();

    app(ChunkableRegistry::class)->register(FakePruneDoc::class);
    // Dirty row — but prune-chunks should not dispatch reconcile jobs.
    FakePruneDoc::create(['body' => 'dirty content']);

    $this->artisan('atlas:prune-chunks')->assertExitCode(0);

    Queue::assertNotPushed(ChunkContentJob::class);
});

it('does not treat soft-deleted owners as orphans', function () {
    app(ChunkableRegistry::class)->register(FakePruneSoftDeletingDoc::class);

    $doc = FakePruneSoftDeletingDoc::create(['body' => 'content']);
    Chunk::create(array_merge([
        'chunkable_type' => $doc->getMorphClass(),
        'chunkable_id' => $doc->id,
        'ord' => 0,
        'heading_path' => null,
        'content' => 'chunk',
        'content_hash' => 'h',
        'token_count' => 1,
        'embedding_model' => 'text-embedding-3-small',
        'embedded_at' => now(),
    ], fakeChunkEmbedding()));

    // Soft-delete via mass-query: the row stays in the table with deleted_at
    // set. The prune command's withoutGlobalScopes() owner subquery must
    // include this row so its chunks aren't pruned.
    FakePruneSoftDeletingDoc::query()->whereKey($doc->id)->delete();

    $this->artisan('atlas:prune-chunks')->assertExitCode(0);

    expect(Chunk::query()->where('chunkable_id', $doc->id)->count())->toBe(1);
});

it('fails with a descriptive error when --model targets an unregistered class', function () {
    $this->artisan('atlas:prune-chunks', ['--model' => FakePruneDoc::class])
        ->expectsOutputToContain('is not registered as chunkable')
        ->assertExitCode(1);
});

it('only prunes orphans for the requested class when --model is provided', function () {
    Queue::fake();

    Schema::dropIfExists('fake_other_prune_docs');
    Schema::create('fake_other_prune_docs', function (Blueprint $table) {
        $table->id();
        $table->text('body')->nullable();
        $table->timestamps();
        ChunkedEmbeddingColumns::add($table);
    });

    $otherClass = new class extends Model implements Chunkable
    {
        use HasChunkedEmbeddings;

        protected $table = 'fake_other_prune_docs';

        protected $guarded = [];

        public $timestamps = true;
    };
    $otherClassName = get_class($otherClass);

    app(ChunkableRegistry::class)->register(FakePruneDoc::class);
    app(ChunkableRegistry::class)->register($otherClassName);

    $a = FakePruneDoc::create(['body' => 'A']);
    $b = $otherClassName::create(['body' => 'B']);

    // Seed chunks for both.
    foreach ([[$a, FakePruneDoc::class], [$b, $otherClassName]] as [$doc, $class]) {
        Chunk::create(array_merge([
            'chunkable_type' => $doc->getMorphClass(),
            'chunkable_id' => $doc->id,
            'ord' => 0,
            'heading_path' => null,
            'content' => 'chunk',
            'content_hash' => hash('xxh128', $class.$doc->id),
            'token_count' => 1,
            'embedding_model' => 'text-embedding-3-small',
            'embedded_at' => now(),
        ], fakeChunkEmbedding()));
    }

    // Orphan both: mass-delete from both tables.
    FakePruneDoc::query()->whereKey($a->id)->delete();
    $otherClassName::query()->whereKey($b->id)->delete();

    $this->artisan('atlas:prune-chunks', ['--model' => FakePruneDoc::class])->assertExitCode(0);

    // Scope assertions to chunkable_type — autoincrement ids collide
    // across the two tables (both rows are id=1).
    expect(Chunk::query()
        ->where('chunkable_type', $a->getMorphClass())
        ->where('chunkable_id', $a->id)
        ->count())->toBe(0);
    expect(Chunk::query()
        ->where('chunkable_type', $b->getMorphClass())
        ->where('chunkable_id', $b->id)
        ->count())->toBe(1);

    Schema::dropIfExists('fake_other_prune_docs');
});
