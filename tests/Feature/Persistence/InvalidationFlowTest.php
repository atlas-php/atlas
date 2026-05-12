<?php

declare(strict_types=1);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Embeddings\Chunkable;
use Atlasphp\Atlas\Embeddings\ChunkableRegistry;
use Atlasphp\Atlas\Embeddings\ChunkData;
use Atlasphp\Atlas\Embeddings\Chunkers\Chunker;
use Atlasphp\Atlas\Persistence\Concerns\HasChunkedEmbeddings;
use Atlasphp\Atlas\Persistence\Models\Chunk;
use Atlasphp\Atlas\Persistence\Schema\ChunkedEmbeddingColumns;
use Atlasphp\Atlas\Persistence\Services\ChunkContentService;
use Atlasphp\Atlas\Queue\Jobs\ChunkContentJob;
use Atlasphp\Atlas\Testing\EmbeddingsResponseFake;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

/**
 * Programmable chunker for this test: returns whatever has been staged in
 * the static $next array. Lets each test step decide what "the chunker
 * produced" looked like before/after the edit.
 */
class InvalidationFlowChunker implements Chunker
{
    /** @var array<int, ChunkData> */
    public static array $next = [];

    public function chunk(string $content): array
    {
        return self::$next;
    }
}

class InvalidationFlowDoc extends Model implements Chunkable
{
    use HasChunkedEmbeddings;

    protected $table = 'invalidation_flow_docs';

    protected $guarded = [];

    public $timestamps = true;

    protected ?string $chunker = InvalidationFlowChunker::class;
}

beforeEach(function () {
    Schema::dropIfExists('invalidation_flow_docs');
    Schema::create('invalidation_flow_docs', function (Blueprint $table) {
        $table->id();
        $table->text('body')->nullable();
        $table->timestamps();
        ChunkedEmbeddingColumns::add($table);
    });

    app(ChunkableRegistry::class)->clear();
    InvalidationFlowChunker::$next = [];

    config([
        'atlas.defaults.embed.provider' => 'openai',
        'atlas.defaults.embed.model' => 'text-embedding-3-small',
    ]);
    AtlasConfig::refresh();
});

/**
 * End-to-end test for the partial-rechunk guarantee.
 *
 * Walks the full flow:
 *   1. Create + initial reconcile → content_hash == indexed_hash, 4 chunks.
 *   2. Edit content → content_hash diverges (invalidated), indexed_hash unchanged.
 *   3. Sweep command sees a dirty row, dispatches one job.
 *   4. Reconciler runs → only the changed chunk re-embeds; 3/4 kept verbatim.
 *   5. Final state: content_hash == indexed_hash again.
 *
 * The whole point of the design is partial re-chunking after edits — this
 * test asserts every visible invariant of that flow in one place.
 */
it('edit → hash invalidation → sweep detects → only changed chunks re-embed', function () {
    Atlas::fake([
        EmbeddingsResponseFake::make()->withEmbeddings([
            fakeEmbeddingVector(0.1), fakeEmbeddingVector(0.2), fakeEmbeddingVector(0.3), fakeEmbeddingVector(0.4),
        ]),
    ]);

    InvalidationFlowChunker::$next = [
        new ChunkData(0, 'A', 'Alpha content', 4),
        new ChunkData(1, 'B', 'Beta content', 4),
        new ChunkData(2, 'C', 'Gamma content', 4),
        new ChunkData(3, 'D', 'Delta content', 4),
    ];

    // ─── Step 1: initial save + reconcile ───────────────────────────────
    $doc = InvalidationFlowDoc::create(['body' => 'v1 body']);
    $initialContentHash = $doc->content_hash;
    expect($initialContentHash)->not->toBeNull();
    expect($doc->indexed_hash)->toBeNull();

    app(ChunkContentService::class)->reconcile($doc);
    $doc->refresh();

    expect($doc->indexed_hash)->toBe($initialContentHash);
    expect(Chunk::query()->where('chunkable_id', $doc->id)->count())->toBe(4);

    $firstRunHashes = Chunk::query()
        ->where('chunkable_id', $doc->id)
        ->pluck('content_hash')
        ->all();

    // ─── Step 2: edit content → invalidation visible in column state ────
    $fake = Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.2)])]);
    InvalidationFlowChunker::$next = [
        new ChunkData(0, 'A', 'Alpha content', 4),
        new ChunkData(1, 'B', 'Beta CHANGED content', 5),
        new ChunkData(2, 'C', 'Gamma content', 4),
        new ChunkData(3, 'D', 'Delta content', 4),
    ];
    $doc->update(['body' => 'v2 body — one paragraph edited']);
    $doc->refresh();

    // After save, content_hash has been recomputed but indexed_hash is unchanged.
    // This divergence is what the sweep query looks for.
    expect($doc->content_hash)->not->toBe($initialContentHash);
    expect($doc->indexed_hash)->toBe($initialContentHash);
    expect($doc->content_hash)->not->toBe($doc->indexed_hash);

    // ─── Step 3: backdate updated_at + sweep finds + dispatches one job ─
    Queue::fake();
    app(ChunkableRegistry::class)->register(InvalidationFlowDoc::class);
    InvalidationFlowDoc::query()->update(['updated_at' => now()->subMinutes(2)]);

    $this->artisan('atlas:chunk')->assertExitCode(0);

    Queue::assertPushed(ChunkContentJob::class, 1);
    Queue::assertPushed(
        ChunkContentJob::class,
        fn (ChunkContentJob $job) => $job->modelClass === InvalidationFlowDoc::class && $job->modelId === $doc->id,
    );

    // ─── Step 4: actually run the reconciler ────────────────────────────
    app(ChunkContentService::class)->reconcile($doc->fresh());

    // Exactly one embed API call — only the changed chunk was re-embedded.
    $recorded = $fake->recorded();
    expect(count($recorded))->toBe(1);
    expect($recorded[0]->method)->toBe('embed');

    // Chunk diff: 3 of 4 hashes survived; 1 is new.
    $afterHashes = Chunk::query()
        ->where('chunkable_id', $doc->id)
        ->pluck('content_hash')
        ->all();
    $kept = count(array_intersect($firstRunHashes, $afterHashes));
    $new = count(array_diff($afterHashes, $firstRunHashes));
    expect($kept)->toBe(3);
    expect($new)->toBe(1);

    // ─── Step 5: invalidation is fully resolved ─────────────────────────
    $doc->refresh();
    expect($doc->indexed_hash)->toBe($doc->content_hash);
});

it('the sweep does not pick a row up again once indexed_hash matches', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);
    InvalidationFlowChunker::$next = [new ChunkData(0, 'A', 'Alpha', 1)];

    $doc = InvalidationFlowDoc::create(['body' => 'body']);
    app(ChunkContentService::class)->reconcile($doc);
    $doc->refresh();

    // No edit — sweep should treat this row as clean.
    Queue::fake();
    app(ChunkableRegistry::class)->register(InvalidationFlowDoc::class);
    InvalidationFlowDoc::query()->update(['updated_at' => now()->subMinutes(2)]);

    $this->artisan('atlas:chunk')->assertExitCode(0);

    Queue::assertNotPushed(ChunkContentJob::class);
});
