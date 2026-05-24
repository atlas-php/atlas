<?php

declare(strict_types=1);

use Atlasphp\Atlas\Embeddings\Chunkable;
use Atlasphp\Atlas\Embeddings\ChunkableRegistry;
use Atlasphp\Atlas\Persistence\Concerns\HasChunkedEmbeddings;
use Atlasphp\Atlas\Persistence\Schema\ChunkedEmbeddingColumns;
use Atlasphp\Atlas\Queue\Jobs\ChunkContentJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

class FakeRechunkDoc extends Model implements Chunkable
{
    use HasChunkedEmbeddings;

    protected $table = 'fake_rechunk_docs';

    protected $guarded = [];

    public $timestamps = true;
}

class FakeRechunkDocB extends Model implements Chunkable
{
    use HasChunkedEmbeddings;

    protected $table = 'fake_rechunk_docs_b';

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

    Schema::dropIfExists('fake_rechunk_docs_b');
    Schema::create('fake_rechunk_docs_b', function (Blueprint $table) {
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

it('marks every registered chunkable class dirty when no class is given', function () {
    app(ChunkableRegistry::class)->register(FakeRechunkDoc::class);
    app(ChunkableRegistry::class)->register(FakeRechunkDocB::class);

    FakeRechunkDoc::create(['body' => 'A1']);
    FakeRechunkDoc::create(['body' => 'A2']);
    FakeRechunkDocB::create(['body' => 'B1']);

    FakeRechunkDoc::query()->update(['indexed_hash' => 'old']);
    FakeRechunkDocB::query()->update(['indexed_hash' => 'old']);

    $this->artisan('atlas:rechunk')
        ->expectsOutputToContain('Marked 2 row(s)')
        ->expectsOutputToContain('Marked 1 row(s)')
        ->expectsOutputToContain('Total: 3 row(s)')
        ->assertExitCode(0);

    expect(FakeRechunkDoc::query()->whereNotNull('indexed_hash')->count())->toBe(0);
    expect(FakeRechunkDocB::query()->whereNotNull('indexed_hash')->count())->toBe(0);
});

it('warns and exits cleanly when no chunkable classes are registered', function () {
    $this->artisan('atlas:rechunk')
        ->expectsOutputToContain('No chunkable models registered')
        ->assertExitCode(0);
});

it('--all is a synonym for omitting the class argument', function () {
    app(ChunkableRegistry::class)->register(FakeRechunkDoc::class);
    app(ChunkableRegistry::class)->register(FakeRechunkDocB::class);

    FakeRechunkDoc::create(['body' => 'A']);
    FakeRechunkDocB::create(['body' => 'B']);
    FakeRechunkDoc::query()->update(['indexed_hash' => 'old']);
    FakeRechunkDocB::query()->update(['indexed_hash' => 'old']);

    $this->artisan('atlas:rechunk', ['--all' => true])
        ->expectsOutputToContain('Total: 2 row(s)')
        ->assertExitCode(0);

    expect(FakeRechunkDoc::query()->whereNotNull('indexed_hash')->count())->toBe(0);
    expect(FakeRechunkDocB::query()->whereNotNull('indexed_hash')->count())->toBe(0);
});

it('errors when class and --all are both passed', function () {
    app(ChunkableRegistry::class)->register(FakeRechunkDoc::class);

    $this->artisan('atlas:rechunk', ['class' => FakeRechunkDoc::class, '--all' => true])
        ->expectsOutputToContain('Pass either a class argument or --all')
        ->assertExitCode(1);
});

it('errors when an id is passed without a class', function () {
    app(ChunkableRegistry::class)->register(FakeRechunkDoc::class);

    $this->artisan('atlas:rechunk', ['id' => 42])
        ->expectsOutputToContain('Cannot pass an id without a single class')
        ->assertExitCode(1);
});

it('reports rows past the failure cap so the operator knows to pass --reset-failures', function () {
    config(['atlas.embeddings.max_failures' => 5]);
    app(ChunkableRegistry::class)->register(FakeRechunkDoc::class);

    $clean = FakeRechunkDoc::create(['body' => 'clean']);
    $poisoned = FakeRechunkDoc::create(['body' => 'poisoned']);
    $poisoned->update(['index_failure_count' => 7]);

    $this->artisan('atlas:rechunk', ['class' => FakeRechunkDoc::class])
        ->expectsOutputToContain('Marked 2 row(s) dirty (1 past failure cap)')
        ->expectsOutputToContain('1 row(s) past the failure cap')
        ->expectsOutputToContain('--reset-failures')
        ->assertExitCode(0);
});

it('does not warn about the failure cap when --reset-failures is passed', function () {
    config(['atlas.embeddings.max_failures' => 5]);
    app(ChunkableRegistry::class)->register(FakeRechunkDoc::class);

    $doc = FakeRechunkDoc::create(['body' => 'poisoned']);
    $doc->update(['index_failure_count' => 7]);

    $this->artisan('atlas:rechunk', [
        'class' => FakeRechunkDoc::class,
        '--reset-failures' => true,
    ])
        ->doesntExpectOutputToContain('past the failure cap')
        ->assertExitCode(0);
});

it('preserves updated_at so freshly-rechunked rows are immediately sweep-eligible', function () {
    app(ChunkableRegistry::class)->register(FakeRechunkDoc::class);

    $doc = FakeRechunkDoc::create(['body' => 'body']);
    $originalUpdatedAt = now()->subHours(3);
    FakeRechunkDoc::query()->update(['updated_at' => $originalUpdatedAt, 'indexed_hash' => 'old']);

    $this->artisan('atlas:rechunk', ['class' => FakeRechunkDoc::class])->assertExitCode(0);

    $doc->refresh();
    // Allow ±2s of clock skew between PHP and the DB but reject a bump to now().
    expect($doc->updated_at->getTimestamp())
        ->toBeGreaterThan($originalUpdatedAt->getTimestamp() - 2)
        ->toBeLessThan($originalUpdatedAt->getTimestamp() + 2);
});

it('dispatches ChunkContentJob for every dirty row when --dispatch is passed', function () {
    Queue::fake();

    app(ChunkableRegistry::class)->register(FakeRechunkDoc::class);

    $a = FakeRechunkDoc::create(['body' => 'A']);
    $b = FakeRechunkDoc::create(['body' => 'B']);

    $this->artisan('atlas:rechunk', [
        'class' => FakeRechunkDoc::class,
        '--dispatch' => true,
    ])
        ->expectsOutputToContain('2 job(s) dispatched')
        ->assertExitCode(0);

    Queue::assertPushed(ChunkContentJob::class, 2);
    Queue::assertPushed(
        ChunkContentJob::class,
        fn (ChunkContentJob $job) => $job->modelId === $a->id,
    );
    Queue::assertPushed(
        ChunkContentJob::class,
        fn (ChunkContentJob $job) => $job->modelId === $b->id,
    );
});

it('--dispatch skips poisoned rows unless --reset-failures is also passed', function () {
    Queue::fake();
    config(['atlas.embeddings.max_failures' => 5]);
    app(ChunkableRegistry::class)->register(FakeRechunkDoc::class);

    $clean = FakeRechunkDoc::create(['body' => 'clean']);
    $poisoned = FakeRechunkDoc::create(['body' => 'poisoned']);
    $poisoned->update(['index_failure_count' => 7]);

    $this->artisan('atlas:rechunk', [
        'class' => FakeRechunkDoc::class,
        '--dispatch' => true,
    ])->assertExitCode(0);

    Queue::assertPushed(ChunkContentJob::class, 1);
    Queue::assertPushed(
        ChunkContentJob::class,
        fn (ChunkContentJob $job) => $job->modelId === $clean->id,
    );
});

it('--dispatch with --reset-failures dispatches every dirty row, including poisoned ones', function () {
    Queue::fake();
    config(['atlas.embeddings.max_failures' => 5]);
    app(ChunkableRegistry::class)->register(FakeRechunkDoc::class);

    FakeRechunkDoc::create(['body' => 'clean']);
    $poisoned = FakeRechunkDoc::create(['body' => 'poisoned']);
    $poisoned->update(['index_failure_count' => 7]);

    $this->artisan('atlas:rechunk', [
        'class' => FakeRechunkDoc::class,
        '--dispatch' => true,
        '--reset-failures' => true,
    ])->assertExitCode(0);

    Queue::assertPushed(ChunkContentJob::class, 2);
});

it('--dispatch with a single id dispatches only that job', function () {
    Queue::fake();
    app(ChunkableRegistry::class)->register(FakeRechunkDoc::class);

    $a = FakeRechunkDoc::create(['body' => 'A']);
    $b = FakeRechunkDoc::create(['body' => 'B']);
    $c = FakeRechunkDoc::create(['body' => 'C']);

    $this->artisan('atlas:rechunk', [
        'class' => FakeRechunkDoc::class,
        'id' => $b->id,
        '--dispatch' => true,
    ])->assertExitCode(0);

    Queue::assertPushed(ChunkContentJob::class, 1);
    Queue::assertPushed(
        ChunkContentJob::class,
        fn (ChunkContentJob $job) => $job->modelId === $b->id,
    );
});

it('--dispatch with no class dispatches for every registered class', function () {
    Queue::fake();
    app(ChunkableRegistry::class)->register(FakeRechunkDoc::class);
    app(ChunkableRegistry::class)->register(FakeRechunkDocB::class);

    FakeRechunkDoc::create(['body' => 'A']);
    FakeRechunkDocB::create(['body' => 'B']);

    $this->artisan('atlas:rechunk', ['--dispatch' => true])
        ->expectsOutputToContain('Total: 2 job(s) dispatched')
        ->assertExitCode(0);

    Queue::assertPushed(ChunkContentJob::class, 2);
    Queue::assertPushed(
        ChunkContentJob::class,
        fn (ChunkContentJob $job) => $job->modelClass === FakeRechunkDoc::class,
    );
    Queue::assertPushed(
        ChunkContentJob::class,
        fn (ChunkContentJob $job) => $job->modelClass === FakeRechunkDocB::class,
    );
});

it('errors when an id is passed together with --all', function () {
    app(ChunkableRegistry::class)->register(FakeRechunkDoc::class);

    $this->artisan('atlas:rechunk', [
        'class' => FakeRechunkDoc::class,
        'id' => 5,
        '--all' => true,
    ])
        ->expectsOutputToContain('Cannot pass an id without a single class')
        ->assertExitCode(1);
});

it('marks zero rows and dispatches zero jobs for a registered class with no rows', function () {
    Queue::fake();
    app(ChunkableRegistry::class)->register(FakeRechunkDoc::class);

    $this->artisan('atlas:rechunk', [
        'class' => FakeRechunkDoc::class,
        '--dispatch' => true,
    ])
        ->expectsOutputToContain('Marked 0 row(s) dirty')
        ->expectsOutputToContain('0 job(s) dispatched')
        ->assertExitCode(0);

    Queue::assertNothingPushed();
});

it('reports the failure cap warning for a single poisoned id', function () {
    config(['atlas.embeddings.max_failures' => 5]);
    app(ChunkableRegistry::class)->register(FakeRechunkDoc::class);

    $doc = FakeRechunkDoc::create(['body' => 'poisoned']);
    $doc->update(['index_failure_count' => 7]);

    $this->artisan('atlas:rechunk', [
        'class' => FakeRechunkDoc::class,
        'id' => $doc->id,
    ])
        ->expectsOutputToContain("Marked id={$doc->id} dirty")
        ->expectsOutputToContain('1 row(s) past the failure cap')
        ->assertExitCode(0);

    // The poisoned row is still marked dirty (indexed_hash cleared); the warning
    // just tells the operator the sweep will skip it without --reset-failures.
    expect($doc->fresh()->indexed_hash)->toBeNull();
    expect($doc->fresh()->index_failure_count)->toBe(7);
});
