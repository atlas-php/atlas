<?php

declare(strict_types=1);

use Atlasphp\Atlas\Embeddings\Chunkable;
use Atlasphp\Atlas\Embeddings\ChunkableRegistry;
use Atlasphp\Atlas\Persistence\Concerns\HasChunkedEmbeddings;
use Atlasphp\Atlas\Persistence\Schema\ChunkedEmbeddingColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FakeRechunkDoc extends Model implements Chunkable
{
    use HasChunkedEmbeddings;

    protected $table = 'fake_rechunk_docs';

    protected $guarded = [];

    public $timestamps = true;
}

beforeEach(function () {
    Schema::dropIfExists('fake_rechunk_docs');
    Schema::create('fake_rechunk_docs', function (Blueprint $table) {
        $table->id();
        $table->text('body')->nullable();
        $table->timestamps();
        ChunkedEmbeddingColumns::add($table);
    });

    app(ChunkableRegistry::class)->clear();
});

it('errors when the class does not exist', function () {
    $this->artisan('atlas:rechunk', ['class' => 'App\\NotARealClass'])
        ->expectsOutputToContain('does not exist')
        ->assertExitCode(1);
});

it('errors when the class is not registered as chunkable', function () {
    // Class exists, but no Atlas::registerChunkable() call has been made.
    $this->artisan('atlas:rechunk', ['class' => FakeRechunkDoc::class])
        ->expectsOutputToContain('is not registered as chunkable')
        ->assertExitCode(1);
});

it('clears indexed_hash on every row so the next sweep picks them up', function () {
    app(ChunkableRegistry::class)->register(FakeRechunkDoc::class);

    $a = FakeRechunkDoc::create(['body' => 'body A']);
    $b = FakeRechunkDoc::create(['body' => 'body B']);
    $c = FakeRechunkDoc::create(['body' => 'body C']);

    // Simulate three previously-indexed rows.
    FakeRechunkDoc::query()->update(['indexed_hash' => 'whatever']);
    expect(FakeRechunkDoc::query()->whereNotNull('indexed_hash')->count())->toBe(3);

    $this->artisan('atlas:rechunk', ['class' => FakeRechunkDoc::class])
        ->expectsOutputToContain('Marked 3 row(s)')
        ->assertExitCode(0);

    // All rows are now dirty (indexed_hash cleared, content_hash still set).
    expect(FakeRechunkDoc::query()->whereNotNull('indexed_hash')->count())->toBe(0);
    expect(FakeRechunkDoc::query()->whereNotNull('content_hash')->count())->toBe(3);
});

it('does not touch index_failure_count by default', function () {
    app(ChunkableRegistry::class)->register(FakeRechunkDoc::class);

    $doc = FakeRechunkDoc::create(['body' => 'body']);
    $doc->update([
        'indexed_hash' => 'old',
        'index_failure_count' => 7,
        'last_index_error' => 'stuck',
    ]);

    $this->artisan('atlas:rechunk', ['class' => FakeRechunkDoc::class])
        ->assertExitCode(0);

    $doc->refresh();
    expect($doc->indexed_hash)->toBeNull();
    expect($doc->index_failure_count)->toBe(7);
    expect($doc->last_index_error)->toBe('stuck');
});

it('resets failure counters when --reset-failures is passed', function () {
    app(ChunkableRegistry::class)->register(FakeRechunkDoc::class);

    $doc = FakeRechunkDoc::create(['body' => 'body']);
    $doc->update([
        'indexed_hash' => 'old',
        'index_failure_count' => 7,
        'last_index_error' => 'stuck',
    ]);

    $this->artisan('atlas:rechunk', ['class' => FakeRechunkDoc::class, '--reset-failures' => true])
        ->assertExitCode(0);

    $doc->refresh();
    expect($doc->indexed_hash)->toBeNull();
    expect($doc->index_failure_count)->toBe(0);
    expect($doc->last_index_error)->toBeNull();
});

it('marks only the specified row dirty when an id is given', function () {
    app(ChunkableRegistry::class)->register(FakeRechunkDoc::class);

    $a = FakeRechunkDoc::create(['body' => 'A']);
    $b = FakeRechunkDoc::create(['body' => 'B']);
    $c = FakeRechunkDoc::create(['body' => 'C']);
    // 32 chars to match the char(32) column exactly — PG pads shorter values.
    $marker = str_repeat('s', 32);
    FakeRechunkDoc::query()->update(['indexed_hash' => $marker]);

    $this->artisan('atlas:rechunk', ['class' => FakeRechunkDoc::class, 'id' => $b->id])
        ->expectsOutputToContain("id={$b->id}")
        ->assertExitCode(0);

    expect($a->fresh()->indexed_hash)->toBe($marker);
    expect($b->fresh()->indexed_hash)->toBeNull();
    expect($c->fresh()->indexed_hash)->toBe($marker);
});

it('warns when the given id does not exist', function () {
    app(ChunkableRegistry::class)->register(FakeRechunkDoc::class);

    $this->artisan('atlas:rechunk', ['class' => FakeRechunkDoc::class, 'id' => 999])
        ->expectsOutputToContain('No row found')
        ->assertExitCode(0);
});

it('resets failure counters on a single-id rechunk when --reset-failures is passed', function () {
    app(ChunkableRegistry::class)->register(FakeRechunkDoc::class);

    $doc = FakeRechunkDoc::create(['body' => 'body']);
    $doc->update([
        'indexed_hash' => 'old',
        'index_failure_count' => 9,
        'last_index_error' => 'stuck',
    ]);

    $this->artisan('atlas:rechunk', [
        'class' => FakeRechunkDoc::class,
        'id' => $doc->id,
        '--reset-failures' => true,
    ])->assertExitCode(0);

    $doc->refresh();
    expect($doc->indexed_hash)->toBeNull();
    expect($doc->index_failure_count)->toBe(0);
    expect($doc->last_index_error)->toBeNull();
});
