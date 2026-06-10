<?php

declare(strict_types=1);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Embeddings\Chunkable;
use Atlasphp\Atlas\Embeddings\ChunkData;
use Atlasphp\Atlas\Embeddings\Chunkers\Chunker;
use Atlasphp\Atlas\Events\ContentChunked;
use Atlasphp\Atlas\Events\ContentChunkingFailed;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Persistence\Concerns\HasChunkedEmbeddings;
use Atlasphp\Atlas\Persistence\Models\Chunk;
use Atlasphp\Atlas\Persistence\Schema\ChunkedEmbeddingColumns;
use Atlasphp\Atlas\Persistence\Services\ChunkContentService;
use Atlasphp\Atlas\Requests\EmbedRequest;
use Atlasphp\Atlas\Testing\EmbeddingsResponseFake;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

class FakeServiceChunker implements Chunker
{
    /** @var array<int, ChunkData> */
    public static array $next = [];

    public function chunk(string $content): array
    {
        return self::$next;
    }
}

class FakeServiceDoc extends Model implements Chunkable
{
    use HasChunkedEmbeddings;

    protected $table = 'fake_service_docs';

    protected $guarded = [];

    public $timestamps = true;

    protected ?string $chunker = FakeServiceChunker::class;
}

function makeChunk(int $ord, string $content, ?string $heading = null): ChunkData
{
    return new ChunkData($ord, $heading, $content, max(1, (int) ceil(strlen($content) / 4)));
}

function freshFakeEmbeddings(int $count): EmbeddingsResponseFake
{
    $vectors = [];
    for ($i = 0; $i < $count; $i++) {
        $vectors[] = fakeEmbeddingVector(0.1 + $i * 0.01);
    }

    return EmbeddingsResponseFake::make()->withEmbeddings($vectors);
}

beforeEach(function () {
    Schema::dropIfExists('fake_service_docs');
    Schema::create('fake_service_docs', function (Blueprint $table) {
        $table->id();
        $table->text('body')->nullable();
        $table->timestamps();
        ChunkedEmbeddingColumns::add($table);
    });
    FakeServiceChunker::$next = [];

    config([
        'atlas.defaults.embed.provider' => 'openai',
        'atlas.defaults.embed.model' => 'text-embedding-3-small',
    ]);
    AtlasConfig::refresh();
});

it('routes chunk embedding through the standard Atlas embed pipeline', function () {
    // Confirms ChunkContentService uses Atlas::embed() — the same entry point
    // text/image/audio modalities use — so it inherits the standard middleware
    // stack, provider events, retry/timeout policy, and HttpClient transport.
    // Drivers internally dispatch this method through MiddlewareResolver->forProvider('embed')
    // (see Driver::dispatch / Driver::embed), so any EmbedMiddleware or
    // ProviderMiddleware a consumer has registered fires for chunked embeddings.
    $fake = Atlas::fake([freshFakeEmbeddings(2)]);

    FakeServiceChunker::$next = [
        makeChunk(0, 'Section A content', 'A'),
        makeChunk(1, 'Section B content', 'B'),
    ];

    $doc = FakeServiceDoc::create(['body' => 'doc']);
    app(ChunkContentService::class)->reconcile($doc);

    $recorded = $fake->recorded();
    expect($recorded)->toHaveCount(1);

    $req = $recorded[0];
    expect($req->method)->toBe('embed');
    expect($req->provider)->toBe('openai');
    expect($req->model)->toBe('text-embedding-3-small');
    expect($req->request)->toBeInstanceOf(EmbedRequest::class);
    // Inputs are the embedText() composites — heading + content per chunk —
    // so any EmbedMiddleware sees the exact text being sent to the provider.
    expect($req->request->input)->toBe([
        "A\n\nSection A content",
        "B\n\nSection B content",
    ]);
});

it('first-time index inserts all chunks and updates indexed_hash', function () {
    Atlas::fake([freshFakeEmbeddings(3)]);

    FakeServiceChunker::$next = [
        makeChunk(0, 'Alpha content', 'A'),
        makeChunk(1, 'Beta content', 'B'),
        makeChunk(2, 'Gamma content', 'C'),
    ];

    $doc = FakeServiceDoc::create(['body' => 'whole markdown body']);

    app(ChunkContentService::class)->reconcile($doc);

    $doc->refresh();

    expect($doc->indexed_hash)->toBe($doc->content_hash);
    expect($doc->indexed_at)->not->toBeNull();
    expect(Chunk::query()->where('chunkable_id', $doc->id)->count())->toBe(3);
});

it('no-change re-run does not call the embedding API', function () {
    // First run.
    Atlas::fake([freshFakeEmbeddings(2)]);
    FakeServiceChunker::$next = [
        makeChunk(0, 'Alpha', 'A'),
        makeChunk(1, 'Beta', 'B'),
    ];

    $doc = FakeServiceDoc::create(['body' => 'first version']);
    app(ChunkContentService::class)->reconcile($doc);
    $doc->refresh();

    // Second run with the same chunker output — capture the new fake so we
    // can inspect recorded requests after reconcile runs.
    $fake = Atlas::fake();
    app(ChunkContentService::class)->reconcile($doc);

    expect(count($fake->recorded()))->toBe(0);
});

it('single-chunk edit re-embeds only that chunk', function () {
    Atlas::fake([freshFakeEmbeddings(3)]);
    FakeServiceChunker::$next = [
        makeChunk(0, 'Alpha', 'A'),
        makeChunk(1, 'Beta', 'B'),
        makeChunk(2, 'Gamma', 'C'),
    ];

    $doc = FakeServiceDoc::create(['body' => 'v1']);
    app(ChunkContentService::class)->reconcile($doc);

    // Change just one chunk's content; the other two should be kept.
    $fake = Atlas::fake([freshFakeEmbeddings(1)]);
    FakeServiceChunker::$next = [
        makeChunk(0, 'Alpha', 'A'),
        makeChunk(1, 'Beta EDITED', 'B'),
        makeChunk(2, 'Gamma', 'C'),
    ];
    $doc->update(['body' => 'v2']);
    $doc->refresh();

    app(ChunkContentService::class)->reconcile($doc);

    expect(Chunk::query()->where('chunkable_id', $doc->id)->count())->toBe(3);

    $recorded = $fake->recorded();
    expect(count($recorded))->toBe(1);
    expect($recorded[0]->method)->toBe('embed');
});

it('updates ord on kept chunks whose position shifted', function () {
    Atlas::fake([freshFakeEmbeddings(2)]);
    FakeServiceChunker::$next = [
        makeChunk(0, 'Alpha', 'A'),
        makeChunk(1, 'Beta', 'B'),
    ];

    $doc = FakeServiceDoc::create(['body' => 'v1']);
    app(ChunkContentService::class)->reconcile($doc);

    // Reorder: same chunks, swapped positions. Zero embed calls expected.
    $fake = Atlas::fake();
    FakeServiceChunker::$next = [
        makeChunk(0, 'Beta', 'B'),
        makeChunk(1, 'Alpha', 'A'),
    ];
    $doc->update(['body' => 'v2']);
    $doc->refresh();
    app(ChunkContentService::class)->reconcile($doc);

    $rows = Chunk::query()->where('chunkable_id', $doc->id)->orderBy('ord')->get();
    expect($rows->pluck('content')->all())->toBe(['Beta', 'Alpha']);
    expect(count($fake->recorded()))->toBe(0);
});

it('purges chunks and clears indexed_hash when content is emptied', function () {
    Atlas::fake([freshFakeEmbeddings(2)]);
    FakeServiceChunker::$next = [
        makeChunk(0, 'Alpha', 'A'),
        makeChunk(1, 'Beta', 'B'),
    ];

    $doc = FakeServiceDoc::create(['body' => 'something']);
    app(ChunkContentService::class)->reconcile($doc);
    expect(Chunk::query()->where('chunkable_id', $doc->id)->count())->toBe(2);

    $fake = Atlas::fake();
    $doc->update(['body' => '']);
    app(ChunkContentService::class)->reconcile($doc->fresh());

    expect(Chunk::query()->where('chunkable_id', $doc->id)->count())->toBe(0);
    expect(count($fake->recorded()))->toBe(0);
});

it('throws when the embedding provider returns a mismatched vector count', function () {
    // Chunker says 2 chunks, but the fake returns only 1 vector.
    Atlas::fake([
        EmbeddingsResponseFake::make()->withEmbeddings([[0.1, 0.2, 0.3]]),
    ]);
    FakeServiceChunker::$next = [
        makeChunk(0, 'Alpha', 'A'),
        makeChunk(1, 'Beta', 'B'),
    ];

    $doc = FakeServiceDoc::create(['body' => 'body']);

    expect(fn () => app(ChunkContentService::class)->reconcile($doc))
        ->toThrow(AtlasException::class, 'returned 1 vectors for 2 chunks');
});

it('throws an actionable error when a vector dimension does not match the configured size', function () {
    // Right count, wrong dimension: the fake returns 3-dim vectors but the
    // column is sized to atlas.embeddings.dimensions. The guard must surface a
    // clear message instead of letting the cryptic pgvector error fire.
    Atlas::fake([
        EmbeddingsResponseFake::make()->withEmbeddings([[0.1, 0.2, 0.3], [0.4, 0.5, 0.6]]),
    ]);
    FakeServiceChunker::$next = [
        makeChunk(0, 'Alpha', 'A'),
        makeChunk(1, 'Beta', 'B'),
    ];

    $doc = FakeServiceDoc::create(['body' => 'body']);
    $expected = (int) config('atlas.embeddings.dimensions');

    expect(fn () => app(ChunkContentService::class)->reconcile($doc))
        ->toThrow(AtlasException::class, "returned a 3-dimension vector but atlas.embeddings.dimensions is {$expected}");
});

it('increments failure counter and dispatches ContentChunkingFailed on exception', function () {
    Event::fake([ContentChunkingFailed::class]);

    Atlas::fake([
        EmbeddingsResponseFake::make()->withEmbeddings([]),
    ]);
    FakeServiceChunker::$next = [makeChunk(0, 'Alpha', 'A')];

    $doc = FakeServiceDoc::create(['body' => 'body']);

    try {
        app(ChunkContentService::class)->reconcile($doc);
    } catch (Throwable $e) {
        // expected
    }

    $doc->refresh();
    expect($doc->index_failure_count)->toBe(1);
    expect($doc->last_index_error)->not->toBeNull();
    expect($doc->indexed_hash)->toBeNull();

    Event::assertDispatched(ContentChunkingFailed::class);
});

it('dispatches ContentChunked event on success', function () {
    Event::fake([ContentChunked::class]);

    Atlas::fake([freshFakeEmbeddings(2)]);
    FakeServiceChunker::$next = [
        makeChunk(0, 'Alpha', 'A'),
        makeChunk(1, 'Beta', 'B'),
    ];

    $doc = FakeServiceDoc::create(['body' => 'body']);
    app(ChunkContentService::class)->reconcile($doc);

    Event::assertDispatched(
        ContentChunked::class,
        fn ($event) => $event->chunkableType === $doc->getMorphClass()
            && $event->chunkableId === $doc->id
            && $event->chunkCount === 2
            && $event->embeddedCount === 2,
    );
});

it('full rewrite — every chunk is new — deletes all old chunks and embeds all new', function () {
    Atlas::fake([freshFakeEmbeddings(4)]);
    FakeServiceChunker::$next = [
        makeChunk(0, 'Alpha v1', 'A'),
        makeChunk(1, 'Beta v1', 'B'),
        makeChunk(2, 'Gamma v1', 'C'),
        makeChunk(3, 'Delta v1', 'D'),
    ];

    $doc = FakeServiceDoc::create(['body' => 'v1']);
    app(ChunkContentService::class)->reconcile($doc);
    $beforeIds = Chunk::query()->where('chunkable_id', $doc->id)->orderBy('ord')->pluck('id')->all();
    $beforeHashes = Chunk::query()->where('chunkable_id', $doc->id)->pluck('content_hash')->all();
    expect($beforeIds)->toHaveCount(4);

    // Replace EVERY chunk with completely different content (different
    // heading_path too — guarantees zero hash overlap).
    $fake = Atlas::fake([freshFakeEmbeddings(4)]);
    FakeServiceChunker::$next = [
        makeChunk(0, 'Brand new topic one', 'Topic 1'),
        makeChunk(1, 'Brand new topic two', 'Topic 2'),
        makeChunk(2, 'Brand new topic three', 'Topic 3'),
        makeChunk(3, 'Brand new topic four', 'Topic 4'),
    ];

    $doc->update(['body' => 'completely rewritten document']);
    app(ChunkContentService::class)->reconcile($doc->fresh());

    $afterRows = Chunk::query()->where('chunkable_id', $doc->id)->orderBy('ord')->get();
    expect($afterRows)->toHaveCount(4);

    // Every chunk is freshly inserted: no id from before survived, no hash either.
    $afterIds = $afterRows->pluck('id')->all();
    $afterHashes = $afterRows->pluck('content_hash')->all();
    expect(array_intersect($beforeIds, $afterIds))->toBe([]);
    expect(array_intersect($beforeHashes, $afterHashes))->toBe([]);

    // Exactly one batched embed call covering all 4 new chunks.
    $recorded = $fake->recorded();
    expect(count($recorded))->toBe(1);

    // indexed_hash matches the new content_hash — invalidation cleared.
    $doc->refresh();
    expect($doc->indexed_hash)->toBe($doc->content_hash);
});

it('document shrinks — surplus chunks are deleted, surviving ones kept verbatim', function () {
    Atlas::fake([freshFakeEmbeddings(5)]);
    FakeServiceChunker::$next = [
        makeChunk(0, 'Alpha', 'A'),
        makeChunk(1, 'Beta', 'B'),
        makeChunk(2, 'Gamma', 'C'),
        makeChunk(3, 'Delta', 'D'),
        makeChunk(4, 'Epsilon', 'E'),
    ];

    $doc = FakeServiceDoc::create(['body' => 'big v1']);
    app(ChunkContentService::class)->reconcile($doc);
    $betaId = Chunk::query()->where('chunkable_id', $doc->id)->where('heading_path', 'B')->value('id');
    $alphaId = Chunk::query()->where('chunkable_id', $doc->id)->where('heading_path', 'A')->value('id');
    expect(Chunk::query()->where('chunkable_id', $doc->id)->count())->toBe(5);

    // Shrink: keep only the first two chunks, drop C/D/E.
    $fake = Atlas::fake();
    FakeServiceChunker::$next = [
        makeChunk(0, 'Alpha', 'A'),
        makeChunk(1, 'Beta', 'B'),
    ];
    $doc->update(['body' => 'shrunk v2']);
    app(ChunkContentService::class)->reconcile($doc->fresh());

    $surviving = Chunk::query()->where('chunkable_id', $doc->id)->orderBy('ord')->get();
    expect($surviving)->toHaveCount(2);
    // The same rows survived — same primary keys, no churn for unchanged content.
    expect($surviving->pluck('id')->all())->toEqualCanonicalizing([$alphaId, $betaId]);
    // Zero embed calls — surviving chunks didn't need re-embedding.
    expect(count($fake->recorded()))->toBe(0);
});

it('document grows — new chunks appended, existing kept verbatim', function () {
    Atlas::fake([freshFakeEmbeddings(2)]);
    FakeServiceChunker::$next = [
        makeChunk(0, 'Alpha', 'A'),
        makeChunk(1, 'Beta', 'B'),
    ];

    $doc = FakeServiceDoc::create(['body' => 'short v1']);
    app(ChunkContentService::class)->reconcile($doc);
    $originalIds = Chunk::query()->where('chunkable_id', $doc->id)->orderBy('ord')->pluck('id')->all();
    expect($originalIds)->toHaveCount(2);

    // Grow: add 3 new chunks after the existing 2 (same heading paths kept).
    $fake = Atlas::fake([freshFakeEmbeddings(3)]);
    FakeServiceChunker::$next = [
        makeChunk(0, 'Alpha', 'A'),
        makeChunk(1, 'Beta', 'B'),
        makeChunk(2, 'Gamma', 'C'),
        makeChunk(3, 'Delta', 'D'),
        makeChunk(4, 'Epsilon', 'E'),
    ];
    $doc->update(['body' => 'longer v2']);
    app(ChunkContentService::class)->reconcile($doc->fresh());

    $afterRows = Chunk::query()->where('chunkable_id', $doc->id)->orderBy('ord')->get();
    expect($afterRows)->toHaveCount(5);

    // Original two rows are still there with their original primary keys.
    foreach ($originalIds as $id) {
        expect($afterRows->pluck('id')->all())->toContain($id);
    }

    // Exactly one batched embed call for the 3 new chunks.
    $recorded = $fake->recorded();
    expect(count($recorded))->toBe(1);
});

it('aborts the write when content_hash changes mid-flight (race-condition guard)', function () {
    Atlas::fake([freshFakeEmbeddings(2)]);
    FakeServiceChunker::$next = [
        makeChunk(0, 'Alpha', 'A'),
        makeChunk(1, 'Beta', 'B'),
    ];

    $doc = FakeServiceDoc::create(['body' => 'original']);

    // Simulate a concurrent edit between hash capture and the transaction by
    // mutating the row's content_hash directly in the DB so the in-tx re-read
    // sees a different value than the captured $workingHash.
    Event::listen(
        TransactionBeginning::class,
        function () use ($doc) {
            DB::table($doc->getTable())
                ->where('id', $doc->id)
                ->update(['content_hash' => 'differenthashmidflight000000000']);
        },
    );

    app(ChunkContentService::class)->reconcile($doc);

    $doc->refresh();
    // Row remains dirty: indexed_hash is unchanged (still null), no chunks inserted.
    expect($doc->indexed_hash)->toBeNull();
    expect(Chunk::query()->where('chunkable_id', $doc->id)->count())->toBe(0);
});
