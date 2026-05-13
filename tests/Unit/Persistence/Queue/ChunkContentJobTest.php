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

it('calls reconcile on the ChunkContentService when the model is Chunkable', function () {
    $doc = FakeJobChunkableDoc::create(['body' => 'some body content']);
    $job = new ChunkContentJob(FakeJobChunkableDoc::class, $doc->id);
    $spy = app(SpyChunkContentService::class);

    $job->handle($spy);

    expect($spy->reconciled)->toHaveCount(1);
    expect($spy->reconciled[0])->toBeInstanceOf(FakeJobChunkableDoc::class);
    expect($spy->reconciled[0]->id)->toBe($doc->id);
});
