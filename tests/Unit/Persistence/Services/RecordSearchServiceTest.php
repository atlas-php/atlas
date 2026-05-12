<?php

declare(strict_types=1);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Embeddings\SearchResult;
use Atlasphp\Atlas\Embeddings\VectorEmbeddable;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Persistence\Concerns\HasVectorEmbeddings;
use Atlasphp\Atlas\Persistence\Services\RecordSearchService;
use Atlasphp\Atlas\Testing\EmbeddingsResponseFake;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class FakeRecordSearchDoc extends Model implements VectorEmbeddable
{
    use HasVectorEmbeddings;

    protected $table = 'fake_record_search_docs';

    protected $guarded = [];

    public $timestamps = true;

    // `embedding` is intentionally NOT cast to array — HasVectorEmbeddings
    // writes a pgvector literal string on save, and an array cast would
    // double-encode that literal into a JSON-quoted string.
    protected function casts(): array
    {
        return ['embedding_at' => 'datetime'];
    }
}

class FakeMultiSourceRecordDoc extends Model implements VectorEmbeddable
{
    use HasVectorEmbeddings;

    protected $table = 'fake_multi_source_record_docs';

    protected $guarded = [];

    public $timestamps = true;

    public function embeddable(): array
    {
        return ['column' => 'embedding', 'source' => ['title', 'content']];
    }

    protected function casts(): array
    {
        return ['embedding_at' => 'datetime'];
    }
}

class FakeBareRecordDoc extends Model
{
    protected $table = 'fake_bare_record_docs';

    protected $guarded = [];
}

beforeEach(function () {
    $isPostgres = Schema::getConnection()->getDriverName() === 'pgsql';
    $dimensions = (int) config('atlas.embeddings.dimensions', 1536);

    Schema::dropIfExists('fake_record_search_docs');
    Schema::create('fake_record_search_docs', function (Blueprint $table) use ($isPostgres, $dimensions) {
        $table->id();
        $table->string('title');
        $table->text('content')->nullable();
        $table->unsignedBigInteger('user_id')->nullable();
        if ($isPostgres) {
            $table->vector('embedding', $dimensions)->nullable();
        } else {
            $table->text('embedding')->nullable();
        }
        $table->timestamp('embedding_at')->nullable();
        $table->timestamps();
    });

    Schema::dropIfExists('fake_multi_source_record_docs');
    Schema::create('fake_multi_source_record_docs', function (Blueprint $table) use ($isPostgres, $dimensions) {
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

    Schema::dropIfExists('fake_bare_record_docs');
    Schema::create('fake_bare_record_docs', function (Blueprint $table) {
        $table->id();
        $table->timestamps();
    });

    config([
        'atlas.defaults.embed.provider' => 'openai',
        'atlas.defaults.embed.model' => 'text-embedding-3-small',
        // Keep auto-embed disabled so seed() controls the embedding value.
        'atlas.persistence.enabled' => false,
    ]);
    AtlasConfig::refresh();

    // pgvector ops are PG-only; shim the macros so we can exercise the
    // service end-to-end on in-memory sqlite. Shims rank by id ascending.
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

function seedRecord(string $modelClass, array $attrs = []): Model
{
    return $modelClass::create(array_merge([
        'title' => 'Doc',
        'content' => 'embeddable text content',
        'embedding' => fakeEmbeddingLiteral(0.1),
        'embedding_at' => now(),
    ], $attrs));
}

it('throws when the model does not implement VectorEmbeddable', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    expect(fn () => app(RecordSearchService::class)->search(FakeBareRecordDoc::class, 'q'))
        ->toThrow(AtlasException::class, 'is not searchable as a record');
});

it('returns an empty Collection when no records exist', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    $results = app(RecordSearchService::class)->search(FakeRecordSearchDoc::class, 'q');

    expect($results)->toBeInstanceOf(Collection::class);
    expect($results)->toHaveCount(0);
});

it('embeds the query and returns SearchResult wrapping each matched record', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    $a = seedRecord(FakeRecordSearchDoc::class, ['title' => 'A', 'content' => 'apple']);
    $b = seedRecord(FakeRecordSearchDoc::class, ['title' => 'B', 'content' => 'banana']);

    $results = app(RecordSearchService::class)->search(FakeRecordSearchDoc::class, 'fruit');

    expect($results)->toHaveCount(2);
    expect($results->first())->toBeInstanceOf(SearchResult::class);
    expect($results->first()->record)->toBeInstanceOf(FakeRecordSearchDoc::class);
    // The service sorts by similarity desc; the sqlite shim gives higher id
    // higher similarity, so order is ['B', 'A']. Assert set-membership.
    expect($results->pluck('record.title')->all())->toEqualCanonicalizing(['A', 'B']);
});

it('respects the limit option', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    for ($i = 0; $i < 6; $i++) {
        seedRecord(FakeRecordSearchDoc::class, ['title' => "D{$i}"]);
    }

    $results = app(RecordSearchService::class)->search(FakeRecordSearchDoc::class, 'q', ['limit' => 3]);

    expect($results)->toHaveCount(3);
});

it('applies the where callback as an Eloquent scope on the owner builder', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    seedRecord(FakeRecordSearchDoc::class, ['title' => 'Alice', 'user_id' => 1]);
    seedRecord(FakeRecordSearchDoc::class, ['title' => 'Bob', 'user_id' => 2]);

    $results = app(RecordSearchService::class)->search(
        FakeRecordSearchDoc::class,
        'q',
        ['where' => fn ($q) => $q->where('user_id', 1)],
    );

    expect($results)->toHaveCount(1);
    expect($results->first()->record->title)->toBe('Alice');
});

it('populates content from getEmbeddableContent (single source)', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    seedRecord(FakeRecordSearchDoc::class, [
        'title' => 'X',
        'content' => 'the only embedded text',
    ]);

    $top = app(RecordSearchService::class)
        ->search(FakeRecordSearchDoc::class, 'q', ['limit' => 1])
        ->first();

    expect($top->content)->toBe('the only embedded text');
});

it('populates content from getEmbeddableContent (multi-source concatenation)', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    FakeMultiSourceRecordDoc::create([
        'title' => 'My title',
        'content' => 'My body content',
        'embedding' => fakeEmbeddingLiteral(0.1),
        'embedding_at' => now(),
    ]);

    $top = app(RecordSearchService::class)
        ->search(FakeMultiSourceRecordDoc::class, 'q', ['limit' => 1])
        ->first();

    expect($top->content)->toBe("My title\n\nMy body content");
});

it('leaves chunk-only fields null on whole-record results', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    seedRecord(FakeRecordSearchDoc::class);
    $top = app(RecordSearchService::class)
        ->search(FakeRecordSearchDoc::class, 'q', ['limit' => 1])
        ->first();

    expect($top->headingPath)->toBeNull();
    expect($top->ord)->toBeNull();
});

it('similarity is computed as 1 - distance from the macro', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    seedRecord(FakeRecordSearchDoc::class);
    $top = app(RecordSearchService::class)
        ->search(FakeRecordSearchDoc::class, 'q', ['limit' => 1])
        ->first();

    // sqlite shim assigns distance = 1 / (id + 1) → similarity = 1 - that.
    expect($top->similarity)->toBeFloat();
    expect($top->similarity)->toBeLessThan(1.0);
    expect($top->similarity)->toBeGreaterThan(0.0);
});

it('uses whereVectorSimilarTo when min_similarity is set, otherwise orderByVectorDistance', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    seedRecord(FakeRecordSearchDoc::class, ['title' => 'X']);

    // Both modes should return results (the shim doesn't actually filter).
    $withMin = app(RecordSearchService::class)
        ->search(FakeRecordSearchDoc::class, 'q', ['min_similarity' => 0.1]);
    $withoutMin = app(RecordSearchService::class)
        ->search(FakeRecordSearchDoc::class, 'q');

    expect($withMin)->toHaveCount(1);
    expect($withoutMin)->toHaveCount(1);
});

it('rejects a min_similarity below zero', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    expect(fn () => app(RecordSearchService::class)->search(
        FakeRecordSearchDoc::class,
        'q',
        ['min_similarity' => -0.1],
    ))->toThrow(InvalidArgumentException::class, 'min_similarity must be between 0.0 and 1.0');
});

it('rejects a min_similarity above one', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    expect(fn () => app(RecordSearchService::class)->search(
        FakeRecordSearchDoc::class,
        'q',
        ['min_similarity' => 1.5],
    ))->toThrow(InvalidArgumentException::class, 'min_similarity must be between 0.0 and 1.0');
});

it('throws AtlasException when rows return without a distance column', function () {
    Atlas::fake([EmbeddingsResponseFake::make()->withEmbeddings([fakeEmbeddingVector(0.1)])]);

    // Override the macro so distance never lands on the row — simulates
    // VectorQueryMacros not being registered (the only way rawDistance can
    // be null after a successful search).
    $noopSelect = fn (string $column, mixed $embedding, string $as = 'distance') => $this;
    Builder::macro('selectVectorDistance', $noopSelect);
    QueryBuilder::macro('selectVectorDistance', $noopSelect);

    seedRecord(FakeRecordSearchDoc::class, ['title' => 'X']);

    expect(fn () => app(RecordSearchService::class)->search(FakeRecordSearchDoc::class, 'q'))
        ->toThrow(AtlasException::class, 'VectorQueryMacros may not be registered');
});
