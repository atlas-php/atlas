<?php

declare(strict_types=1);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Embeddings\Chunkable;
use Atlasphp\Atlas\Embeddings\SearchResult;
use Atlasphp\Atlas\Embeddings\VectorEmbeddable;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Persistence\Concerns\HasChunkedEmbeddings;
use Atlasphp\Atlas\Persistence\Concerns\HasVectorEmbeddings;
use Atlasphp\Atlas\Persistence\Models\Chunk;
use Atlasphp\Atlas\Persistence\Schema\ChunkedEmbeddingColumns;
use Atlasphp\Atlas\Testing\EmbeddingsResponseFake;
use Atlasphp\Atlas\Tools\SimilaritySearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * A model using HasVectorEmbeddings — one vector per record on the model's
 * own table. The standard whole-record search path.
 */
class FakeRecordDoc extends Model implements VectorEmbeddable
{
    use HasVectorEmbeddings;

    protected $table = 'fake_record_docs';

    protected $guarded = [];

    public $timestamps = true;

    // The trait writes embeddings as pgvector literal strings via
    // toVectorLiteral(). An `array` cast on the column would double-encode
    // that string on save, breaking the pgvector parser. Keep the column
    // raw and let the trait/macros handle the literal format.
    protected function casts(): array
    {
        return ['embedding_at' => 'datetime'];
    }
}

/**
 * A model using HasChunkedEmbeddings — N vectors stored in atlas_chunks.
 */
class FakeChunkDoc extends Model implements Chunkable
{
    use HasChunkedEmbeddings;

    protected $table = 'fake_chunk_docs';

    protected $guarded = [];

    public $timestamps = true;
}

/**
 * A model with neither trait — should error helpfully when searched.
 */
class FakeBareDoc extends Model
{
    protected $table = 'fake_bare_docs';

    protected $guarded = [];
}

beforeEach(function () {
    $isPostgres = Schema::getConnection()->getDriverName() === 'pgsql';
    $dimensions = (int) config('atlas.embeddings.dimensions', 1536);

    Schema::dropIfExists('fake_record_docs');
    Schema::create('fake_record_docs', function (Blueprint $table) use ($isPostgres, $dimensions) {
        $table->id();
        $table->string('title');
        $table->text('content')->nullable();
        if ($isPostgres) {
            $table->vector('embedding', $dimensions)->nullable();
        } else {
            $table->text('embedding')->nullable();
        }
        $table->timestamp('embedding_at')->nullable();
        $table->timestamps();
    });

    Schema::dropIfExists('fake_chunk_docs');
    Schema::create('fake_chunk_docs', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('body')->nullable();
        $table->timestamps();
        ChunkedEmbeddingColumns::add($table);
    });

    Schema::dropIfExists('fake_bare_docs');
    Schema::create('fake_bare_docs', function (Blueprint $table) {
        $table->id();
        $table->timestamps();
    });

    config([
        'atlas.defaults.embed.provider' => 'openai',
        'atlas.defaults.embed.model' => 'text-embedding-3-small',
        // Auto-embed disabled — the seeds pass the embedding literal directly.
        'atlas.persistence.enabled' => false,
    ]);
    AtlasConfig::refresh();

    // pgvector ops aren't available on sqlite — shim the four macros so the
    // service wiring still exercises end-to-end. Shims rank by id ascending.
    $selectDistance = function (string $column, mixed $embedding, string $as = 'distance') {
        $table = $this instanceof Builder ? $this->getModel()->getTable() : $this->from;

        return $this->selectRaw("(1.0 / ({$table}.id + 1)) AS {$as}");
    };
    $orderByDistance = fn (string $column, mixed $embedding, string $direction = 'asc') => $this->orderBy('id', $direction);
    $whereSimilar = fn (string $column, mixed $embedding, float $minSimilarity = 0.5) => $this->orderBy('id');

    Builder::macro('selectVectorDistance', $selectDistance);
    Builder::macro('orderByVectorDistance', $orderByDistance);
    Builder::macro('whereVectorSimilarTo', $whereSimilar);
    QueryBuilder::macro('selectVectorDistance', $selectDistance);
    QueryBuilder::macro('orderByVectorDistance', $orderByDistance);
    QueryBuilder::macro('whereVectorSimilarTo', $whereSimilar);
});

// ─── Auto-dispatch path ─────────────────────────────────────────────────────

it('dispatches to ChunkSearchService when the model implements Chunkable', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    $doc = FakeChunkDoc::create(['title' => 'My doc', 'body' => 'b']);
    Chunk::create(array_merge([
        'chunkable_type' => $doc->getMorphClass(),
        'chunkable_id' => $doc->id,
        'ord' => 3,
        'heading_path' => 'Some > Heading',
        'content' => 'The chunk content the agent should see.',
        'content_hash' => 'abc',
        'token_count' => 1,
        'embedding_model' => 'text-embedding-3-small',
        'embedded_at' => now(),
    ], fakeChunkEmbedding()));

    $results = Atlas::similaritySearch(FakeChunkDoc::class, 'find it', ['limit' => 1]);

    expect($results)->toHaveCount(1);
    $top = $results->first();
    expect($top)->toBeInstanceOf(SearchResult::class);
    // Chunk-mode populates heading_path and ord; record is the parent model.
    expect($top->record)->toBeInstanceOf(FakeChunkDoc::class);
    expect($top->content)->toBe('The chunk content the agent should see.');
    expect($top->headingPath)->toBe('Some > Heading');
    expect($top->ord)->toBe(3);
});

it('dispatches to RecordSearchService when the model uses HasVectorEmbeddings only', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    $doc = FakeRecordDoc::create([
        'title' => 'Whole record',
        'content' => 'The embedded text on the record itself.',
        'embedding' => fakeEmbeddingLiteral(0.1),
        'embedding_at' => now(),
    ]);

    $results = Atlas::similaritySearch(FakeRecordDoc::class, 'find it', ['limit' => 1]);

    expect($results)->toHaveCount(1);
    $top = $results->first();
    expect($top)->toBeInstanceOf(SearchResult::class);
    expect($top->record)->toBeInstanceOf(FakeRecordDoc::class);
    expect($top->record->id)->toBe($doc->id);
    // content is what was embedded — getEmbeddableContent() returns the source field.
    expect($top->content)->toBe('The embedded text on the record itself.');
    // Whole-record mode leaves chunk-only fields null.
    expect($top->headingPath)->toBeNull();
    expect($top->ord)->toBeNull();
});

it('throws AtlasException for models with neither searchable trait', function () {
    expect(fn () => Atlas::similaritySearch(FakeBareDoc::class, 'q'))
        ->toThrow(AtlasException::class, 'not searchable');
});

// ─── Agent tool path ────────────────────────────────────────────────────────

it('the SimilaritySearch tool routes through the unified dispatch for Chunkable models', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    $doc = FakeChunkDoc::create(['title' => 'D', 'body' => 'b']);
    Chunk::create(array_merge([
        'chunkable_type' => $doc->getMorphClass(),
        'chunkable_id' => $doc->id,
        'ord' => 0,
        'heading_path' => null,
        'content' => 'tool-callable chunk',
        'content_hash' => 'h',
        'token_count' => 1,
        'embedding_model' => 'text-embedding-3-small',
        'embedded_at' => now(),
    ], fakeChunkEmbedding()));

    $tool = SimilaritySearch::usingModel(FakeChunkDoc::class, limit: 5);
    $results = $tool->handle(['query' => 'anything'], []);

    expect($results)->toBeInstanceOf(Collection::class);
    expect($results->count())->toBeGreaterThan(0);
    expect($results->first())->toBeInstanceOf(SearchResult::class);
    expect($results->first()->content)->toBe('tool-callable chunk');
});

it('the SimilaritySearch tool still works against HasVectorEmbeddings models', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    FakeRecordDoc::create([
        'title' => 'Whole',
        'content' => 'agent-callable record',
        'embedding' => fakeEmbeddingLiteral(0.1),
        'embedding_at' => now(),
    ]);

    $tool = SimilaritySearch::usingModel(FakeRecordDoc::class, limit: 5);
    $results = $tool->handle(['query' => 'anything'], []);

    expect($results)->toBeInstanceOf(Collection::class);
    expect($results->first())->toBeInstanceOf(SearchResult::class);
    expect($results->first()->content)->toBe('agent-callable record');
});
