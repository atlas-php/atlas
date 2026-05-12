<?php

declare(strict_types=1);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Embeddings\Chunkable;
use Atlasphp\Atlas\Embeddings\SearchResult;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Persistence\Concerns\HasChunkedEmbeddings;
use Atlasphp\Atlas\Persistence\Models\Chunk;
use Atlasphp\Atlas\Persistence\Schema\ChunkedEmbeddingColumns;
use Atlasphp\Atlas\Persistence\Services\ChunkSearchService;
use Atlasphp\Atlas\Testing\EmbeddingsResponseFake;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class FakeSearchDoc extends Model implements Chunkable
{
    use HasChunkedEmbeddings;

    protected $table = 'fake_search_docs';

    protected $guarded = [];

    public $timestamps = true;
}

beforeEach(function () {
    Schema::dropIfExists('fake_search_docs');
    Schema::create('fake_search_docs', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->unsignedBigInteger('user_id')->nullable();
        $table->text('body')->nullable();
        $table->timestamps();
        ChunkedEmbeddingColumns::add($table);
    });

    config([
        'atlas.defaults.embed.provider' => 'openai',
        'atlas.defaults.embed.model' => 'text-embedding-3-small',
    ]);
    AtlasConfig::refresh();

    // The vector-cosine operator (<=>) is pgvector-only. Register sqlite-safe
    // shims for the macros our service uses so we can exercise the service's
    // wiring (parent hydration, where-scope subquery, limit) on an in-memory
    // database. Shims rank by id to keep results deterministic.
    $selectDistance = function (string $column, mixed $embedding, string $as = 'distance') {
        $table = $this instanceof Builder ? $this->getModel()->getTable() : $this->from;

        return $this->selectRaw("(1.0 / ({$table}.id + 1)) AS {$as}");
    };
    $orderByDistance = function (string $column, mixed $embedding, string $direction = 'asc') {
        return $this->orderBy('id', $direction);
    };
    $whereSimilar = function (string $column, mixed $embedding, float $minSimilarity = 0.5) {
        return $this->orderBy('id');
    };

    Builder::macro('selectVectorDistance', $selectDistance);
    Builder::macro('orderByVectorDistance', $orderByDistance);
    Builder::macro('whereVectorSimilarTo', $whereSimilar);
    QueryBuilder::macro('selectVectorDistance', $selectDistance);
    QueryBuilder::macro('orderByVectorDistance', $orderByDistance);
    QueryBuilder::macro('whereVectorSimilarTo', $whereSimilar);
});

function seedFakeChunk(FakeSearchDoc $doc, int $ord, string $heading, string $content): Chunk
{
    return Chunk::create(array_merge([
        'chunkable_type' => $doc->getMorphClass(),
        'chunkable_id' => $doc->id,
        'ord' => $ord,
        'heading_path' => $heading,
        'content' => $content,
        'content_hash' => hash('xxh128', $heading."\n\n".$content),
        'token_count' => max(1, (int) ceil(strlen($content) / 4)),
        'embedding_model' => 'text-embedding-3-small',
        'embedded_at' => now(),
    ], fakeChunkEmbedding()));
}

it('returns an empty Collection when no chunks exist', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    $results = Atlas::similaritySearch(FakeSearchDoc::class, 'anything', ['limit' => 5]);

    expect($results)->toBeInstanceOf(Collection::class);
    expect($results)->toHaveCount(0);
});

it('embeds the query string and returns SearchResult objects with hydrated parents', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    $a = FakeSearchDoc::create(['title' => 'Doc A', 'body' => 'body']);
    $b = FakeSearchDoc::create(['title' => 'Doc B', 'body' => 'body']);
    seedFakeChunk($a, 0, 'Section X', 'About X');
    seedFakeChunk($b, 0, 'Section Y', 'About Y');

    $results = Atlas::similaritySearch(FakeSearchDoc::class, 'tell me about X', ['limit' => 5]);

    expect($results)->toHaveCount(2);
    expect($results->first())->toBeInstanceOf(SearchResult::class);
    expect($results->first()->record)->toBeInstanceOf(FakeSearchDoc::class);
    expect($results->pluck('record.title')->all())->toContain('Doc A', 'Doc B');
});

it('respects the limit option', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    $doc = FakeSearchDoc::create(['title' => 'D', 'body' => 'b']);
    for ($i = 0; $i < 6; $i++) {
        seedFakeChunk($doc, $i, "S{$i}", "Chunk {$i}");
    }

    $results = Atlas::similaritySearch(FakeSearchDoc::class, 'query', ['limit' => 3]);

    expect($results)->toHaveCount(3);
});

it('scopes results to the requested chunkable type', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    $doc = FakeSearchDoc::create(['title' => 'D', 'body' => 'b']);
    seedFakeChunk($doc, 0, 'S', 'mine');

    // Insert a chunk under a different morph class — should NOT appear in results.
    Chunk::create(array_merge([
        'chunkable_type' => 'App\\Models\\Other',
        'chunkable_id' => 99,
        'ord' => 0,
        'heading_path' => null,
        'content' => 'theirs',
        'content_hash' => 'unrelated00000000000000000000000',
        'token_count' => 1,
        'embedding_model' => 'text-embedding-3-small',
        'embedded_at' => now(),
    ], fakeChunkEmbedding()));

    $results = Atlas::similaritySearch(FakeSearchDoc::class, 'q', ['limit' => 5]);

    expect($results)->toHaveCount(1);
    expect($results->first()->content)->toBe('mine');
});

it('applies the where callback as a scope on the owner table', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    $alice = FakeSearchDoc::create(['title' => 'Alice doc', 'user_id' => 1, 'body' => 'b']);
    $bob = FakeSearchDoc::create(['title' => 'Bob doc', 'user_id' => 2, 'body' => 'b']);
    seedFakeChunk($alice, 0, 'S', 'Alice content');
    seedFakeChunk($bob, 0, 'S', 'Bob content');

    $results = Atlas::similaritySearch(
        FakeSearchDoc::class,
        'query',
        [
            'limit' => 5,
            'where' => fn ($q) => $q->where('user_id', 1),
        ],
    );

    expect($results)->toHaveCount(1);
    expect($results->first()->record->title)->toBe('Alice doc');
});

it('builds SearchResult similarity from 1 - distance', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    $doc = FakeSearchDoc::create(['title' => 'D', 'body' => 'b']);
    seedFakeChunk($doc, 0, 'S', 'content');

    $results = Atlas::similaritySearch(FakeSearchDoc::class, 'q', ['limit' => 1]);
    $top = $results->first();

    // sqlite shim sets distance = 1 / (id + 1) → similarity = 1 - distance.
    expect($top->similarity)->toBeFloat();
    expect($top->similarity)->toBeLessThan(1.0);
    expect($top->similarity)->toBeGreaterThan(0.0);
});

it('preserves chunkable_type, heading_path, content, and ord on each SearchResult', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    $doc = FakeSearchDoc::create(['title' => 'D', 'body' => 'b']);
    seedFakeChunk($doc, 7, 'My > Heading', 'My content');

    $top = Atlas::similaritySearch(FakeSearchDoc::class, 'q', ['limit' => 1])->first();

    expect($top->headingPath)->toBe('My > Heading');
    expect($top->content)->toBe('My content');
    expect($top->ord)->toBe(7);
    expect($top->record->getMorphClass())->toBe(FakeSearchDoc::class);
});

it('rejects a min_similarity below zero', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    expect(fn () => app(ChunkSearchService::class)->search(
        FakeSearchDoc::class,
        'q',
        ['min_similarity' => -0.01],
    ))->toThrow(InvalidArgumentException::class, 'min_similarity must be between 0.0 and 1.0');
});

it('rejects a min_similarity above one', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    expect(fn () => app(ChunkSearchService::class)->search(
        FakeSearchDoc::class,
        'q',
        ['min_similarity' => 2.0],
    ))->toThrow(InvalidArgumentException::class, 'min_similarity must be between 0.0 and 1.0');
});

it('switches to whereVectorSimilarTo when min_similarity is supplied', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    $doc = FakeSearchDoc::create(['title' => 'D', 'body' => 'b']);
    seedFakeChunk($doc, 0, 'S', 'content');

    $results = app(ChunkSearchService::class)
        ->search(FakeSearchDoc::class, 'q', ['min_similarity' => 0.25, 'limit' => 5]);

    expect($results)->toHaveCount(1);
    expect($results->first())->toBeInstanceOf(SearchResult::class);
});

it('throws AtlasException when chunk rows return without a distance column', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    // Override the macro so distance never lands on the row — simulates
    // VectorQueryMacros not being registered (the only way rawDistance can
    // be null after a successful search).
    $noopSelect = fn (string $column, mixed $embedding, string $as = 'distance') => $this;
    Builder::macro('selectVectorDistance', $noopSelect);
    QueryBuilder::macro('selectVectorDistance', $noopSelect);

    $doc = FakeSearchDoc::create(['title' => 'D', 'body' => 'b']);
    seedFakeChunk($doc, 0, 'S', 'content');

    expect(fn () => app(ChunkSearchService::class)->search(FakeSearchDoc::class, 'q', ['limit' => 1]))
        ->toThrow(AtlasException::class, 'VectorQueryMacros may not be registered');
});
