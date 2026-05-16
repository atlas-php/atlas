<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Embeddings\Chunkable;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Persistence\Concerns\HasChunkedEmbeddings;
use Atlasphp\Atlas\Persistence\Schema\ChunkedEmbeddingColumns;
use Atlasphp\Atlas\Persistence\Services\ChunkContentService;
use Atlasphp\Atlas\Queue\Jobs\ChunkContentJob;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FakeJobChunkableDoc extends Model implements Chunkable
{
    use HasChunkedEmbeddings;

    protected $table = 'fake_job_chunkable_docs';

    protected $guarded = [];

    public $timestamps = true;
}

class FakeJobBareDoc extends Model
{
    protected $table = 'fake_job_bare_docs';

    protected $guarded = [];

    public $timestamps = true;
}

/**
 * Spy ChunkContentService that records reconcile() calls without doing work.
 */
class SpyChunkContentService extends ChunkContentService
{
    /** @var array<int, Chunkable&Model> */
    public array $reconciled = [];

    public function __construct(AtlasConfig $config, Dispatcher $events)
    {
        parent::__construct($config, $events);
    }

    public function reconcile(Chunkable&Model $model): void
    {
        $this->reconciled[] = $model;
    }
}

beforeEach(function () {
    Schema::dropIfExists('fake_job_chunkable_docs');
    Schema::create('fake_job_chunkable_docs', function (Blueprint $table) {
        $table->id();
        $table->text('body')->nullable();
        $table->timestamps();
        ChunkedEmbeddingColumns::add($table);
    });

    Schema::dropIfExists('fake_job_bare_docs');
    Schema::create('fake_job_bare_docs', function (Blueprint $table) {
        $table->id();
        $table->timestamps();
    });
});

it('returns silently when the model row no longer exists', function () {
    $job = new ChunkContentJob(FakeJobChunkableDoc::class, 999_999);
    $spy = app(SpyChunkContentService::class);

    $job->handle($spy);

    expect($spy->reconciled)->toBe([]);
});

it('throws AtlasException when the model class does not implement Chunkable', function () {
    $doc = FakeJobBareDoc::create([]);
    $job = new ChunkContentJob(FakeJobBareDoc::class, $doc->id);
    $spy = app(SpyChunkContentService::class);

    expect(fn () => $job->handle($spy))
        ->toThrow(AtlasException::class, 'does not implement '.Chunkable::class);
    expect($spy->reconciled)->toBe([]);
});

it('reconciles a chunkable model whose updated_at is past the settle window', function () {
    $doc = FakeJobChunkableDoc::create(['body' => 'some body content']);
    // Backdate past the default settle (60s) so the debounce guard doesn't
    // release the job back to the queue.
    FakeJobChunkableDoc::query()->whereKey($doc->id)
        ->update(['updated_at' => now()->subMinutes(5)]);

    $job = new ChunkContentJob(FakeJobChunkableDoc::class, $doc->id);
    $spy = app(SpyChunkContentService::class);

    $job->handle($spy);

    expect($spy->reconciled)->toHaveCount(1);
    expect($spy->reconciled[0])->toBeInstanceOf(FakeJobChunkableDoc::class);
    expect($spy->reconciled[0]->id)->toBe($doc->id);
});

it('short-circuits when content_hash matches indexed_hash', function () {
    $doc = FakeJobChunkableDoc::create(['body' => 'already indexed body']);
    $doc->indexed_hash = $doc->content_hash;
    $doc->saveQuietly();

    $job = new ChunkContentJob(FakeJobChunkableDoc::class, $doc->id);
    $spy = app(SpyChunkContentService::class);

    $job->handle($spy);

    expect($spy->reconciled)->toBe([]);
});

it('short-circuits when index_failure_count is at or above max_failures', function () {
    config(['atlas.embeddings.max_failures' => 3]);
    AtlasConfig::refresh();

    $doc = FakeJobChunkableDoc::create(['body' => 'poisoned content']);
    $doc->index_failure_count = 3;
    $doc->saveQuietly();
    // Past the settle window so the debounce guard doesn't engage first.
    FakeJobChunkableDoc::query()->whereKey($doc->id)
        ->update(['updated_at' => now()->subMinutes(5)]);

    $job = new ChunkContentJob(FakeJobChunkableDoc::class, $doc->id);
    $spy = app(SpyChunkContentService::class);

    $job->handle($spy);

    expect($spy->reconciled)->toBe([]);
});

it('releases itself when the model was updated within the settle window', function () {
    // settle = 60s (default). updated_at = now() (within the window).
    $doc = FakeJobChunkableDoc::create(['body' => 'fresh edit']);

    $job = new ChunkContentJob(FakeJobChunkableDoc::class, $doc->id);
    $spy = app(SpyChunkContentService::class);

    // No reconcile occurs. release() is a no-op on an unattached job
    // ($this->job is null), so this just confirms the guard short-circuits.
    // The integration test in HasChunkedEmbeddingsTest covers the full
    // dispatch → release → re-process cycle.
    $job->handle($spy);

    expect($spy->reconciled)->toBe([]);
});

it('reports the correct unique id and retry budget', function () {
    $job = new ChunkContentJob(FakeJobChunkableDoc::class, 42);

    expect($job->uniqueId())->toBe(FakeJobChunkableDoc::class.':42');
    expect($job->retryUntil())->toBeInstanceOf(DateTimeInterface::class);
    // retryUntil should be ~1 hour from now (±a few seconds for test runtime).
    $hourFromNow = now()->addHour()->getTimestamp();
    expect(abs($job->retryUntil()->getTimestamp() - $hourFromNow))->toBeLessThan(5);
});
