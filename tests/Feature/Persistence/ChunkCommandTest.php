<?php

declare(strict_types=1);

use Atlasphp\Atlas\Embeddings\Chunkable;
use Atlasphp\Atlas\Embeddings\ChunkableRegistry;
use Atlasphp\Atlas\Embeddings\ChunkData;
use Atlasphp\Atlas\Embeddings\Chunkers\Chunker;
use Atlasphp\Atlas\Persistence\Concerns\HasChunkedEmbeddings;
use Atlasphp\Atlas\Persistence\Models\Chunk;
use Atlasphp\Atlas\Persistence\Schema\ChunkedEmbeddingColumns;
use Atlasphp\Atlas\Queue\Jobs\ChunkContentJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

class FakeCommandChunker implements Chunker
{
    public function chunk(string $content): array
    {
        return [new ChunkData(0, null, $content, 1)];
    }
}

class FakeCommandDoc extends Model implements Chunkable
{
    use HasChunkedEmbeddings;

    protected $table = 'fake_command_docs';

    protected $guarded = [];

    public $timestamps = true;

    protected ?string $chunker = FakeCommandChunker::class;
}

beforeEach(function () {
    Schema::dropIfExists('fake_command_docs');
    Schema::create('fake_command_docs', function (Blueprint $table) {
        $table->id();
        $table->text('body')->nullable();
        $table->timestamps();
        ChunkedEmbeddingColumns::add($table);
    });

    app(ChunkableRegistry::class)->clear();
});

it('exits cleanly when no chunkable models are registered', function () {
    $this->artisan('atlas:chunk')->assertExitCode(0);
});

it('dispatches a ChunkContentJob for each dirty row past the settle period', function () {
    Queue::fake();

    app(ChunkableRegistry::class)->register(FakeCommandDoc::class);

    // settle_seconds defaults to 60, so backdate updated_at by 2 minutes.
    $a = FakeCommandDoc::create(['body' => 'A body']);
    $b = FakeCommandDoc::create(['body' => 'B body']);
    FakeCommandDoc::query()->update(['updated_at' => now()->subMinutes(2)]);

    $this->artisan('atlas:chunk')->assertExitCode(0);

    Queue::assertPushed(ChunkContentJob::class, 2);
    Queue::assertPushed(
        ChunkContentJob::class,
        fn (ChunkContentJob $job) => $job->modelClass === FakeCommandDoc::class && $job->modelId === $a->id,
    );
    Queue::assertPushed(
        ChunkContentJob::class,
        fn (ChunkContentJob $job) => $job->modelClass === FakeCommandDoc::class && $job->modelId === $b->id,
    );
});

it('skips rows still within the settle period', function () {
    Queue::fake();

    app(ChunkableRegistry::class)->register(FakeCommandDoc::class);
    FakeCommandDoc::create(['body' => 'fresh content']);

    $this->artisan('atlas:chunk')->assertExitCode(0);

    Queue::assertNotPushed(ChunkContentJob::class);
});

it('skips rows that have already been indexed', function () {
    Queue::fake();

    app(ChunkableRegistry::class)->register(FakeCommandDoc::class);
    $doc = FakeCommandDoc::create(['body' => 'content']);
    $doc->indexed_hash = $doc->content_hash;
    $doc->save();
    FakeCommandDoc::query()->update(['updated_at' => now()->subMinutes(2)]);

    $this->artisan('atlas:chunk')->assertExitCode(0);

    Queue::assertNotPushed(ChunkContentJob::class);
});

it('prunes orphan chunks left behind by mass-delete', function () {
    Queue::fake();

    app(ChunkableRegistry::class)->register(FakeCommandDoc::class);
    $a = FakeCommandDoc::create(['body' => 'A body']);
    $b = FakeCommandDoc::create(['body' => 'B body']);

    // Seed chunks for both directly.
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

    // Mass-delete B via query builder — this bypasses model events, so the
    // trait's deleting hook does NOT fire and B's chunk would orphan.
    FakeCommandDoc::query()->whereKey($b->id)->delete();
    expect(Chunk::query()->where('chunkable_id', $b->id)->count())->toBe(1);

    $this->artisan('atlas:chunk')
        ->expectsOutputToContain('pruned 1 orphan')
        ->assertExitCode(0);

    expect(Chunk::query()->where('chunkable_id', $b->id)->count())->toBe(0);
    // A's chunk is untouched.
    expect(Chunk::query()->where('chunkable_id', $a->id)->count())->toBe(1);
});

class FakeSoftDeletingDoc extends Model implements Chunkable
{
    use HasChunkedEmbeddings;
    use SoftDeletes;

    protected $table = 'fake_soft_deleting_docs';

    protected $guarded = [];

    public $timestamps = true;
}

it('does not treat soft-deleted owners as orphans (preserves chunks for restore)', function () {
    Queue::fake();

    Schema::dropIfExists('fake_soft_deleting_docs');
    Schema::create('fake_soft_deleting_docs', function (Blueprint $table) {
        $table->id();
        $table->text('body')->nullable();
        $table->timestamps();
        $table->softDeletes();
        ChunkedEmbeddingColumns::add($table);
    });

    app(ChunkableRegistry::class)->register(FakeSoftDeletingDoc::class);

    $doc = FakeSoftDeletingDoc::create(['body' => 'content']);
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

    // Soft-delete via mass-query — this skips model events (like the trait's
    // deleting hook that synchronously removes chunks). The chunks survive
    // until the sweep runs, which used to wrongly prune them since the
    // default query builder filters out soft-deleted rows. With the fix,
    // withoutGlobalScopes() includes them in the owner subquery, so they're
    // not flagged as orphans.
    FakeSoftDeletingDoc::query()->whereKey($doc->id)->delete();
    expect(Chunk::query()->where('chunkable_id', $doc->id)->count())->toBe(1);

    $this->artisan('atlas:chunk')->assertExitCode(0);

    expect(Chunk::query()->where('chunkable_id', $doc->id)->count())->toBe(1);
});

it('leaves chunks alone when soft-deleted rows still live in the owner table', function () {
    // The orphan check uses whereNotIn against the bare table query, so soft
    // deletes (rows still present with deleted_at set) are NOT treated as
    // orphans. That's deliberate: soft-deleted records can be restored, and
    // re-chunking would be wasted work.
    Queue::fake();

    app(ChunkableRegistry::class)->register(FakeCommandDoc::class);
    $doc = FakeCommandDoc::create(['body' => 'A']);
    Chunk::create(array_merge([
        'chunkable_type' => $doc->getMorphClass(),
        'chunkable_id' => $doc->id,
        'ord' => 0,
        'heading_path' => null,
        'content' => 'X',
        'content_hash' => 'abc',
        'token_count' => 1,
        'embedding_model' => 'text-embedding-3-small',
        'embedded_at' => now(),
    ], fakeChunkEmbedding()));

    $this->artisan('atlas:chunk')->assertExitCode(0);
    expect(Chunk::query()->where('chunkable_id', $doc->id)->count())->toBe(1);
});

it('skips rows that have hit max_failures', function () {
    Queue::fake();
    config(['atlas.embeddings.max_failures' => 2]);

    app(ChunkableRegistry::class)->register(FakeCommandDoc::class);
    $doc = FakeCommandDoc::create(['body' => 'content']);
    $doc->index_failure_count = 5;
    $doc->save();
    FakeCommandDoc::query()->update(['updated_at' => now()->subMinutes(2)]);

    $this->artisan('atlas:chunk')->assertExitCode(0);

    Queue::assertNotPushed(ChunkContentJob::class);
});
