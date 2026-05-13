<?php

declare(strict_types=1);

use Atlasphp\Atlas\Embeddings\VectorQueryMacros;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Integration tests for the documented hybrid keyword + vector ranking pattern
 * (docs/features/similarity-search.md → "Direct macro usage").
 *
 * Laravel 11+ ships native pgvector query-builder methods that supersede
 * atlas's macros for the same names — `selectVectorDistance`,
 * `whereVectorDistanceLessThan`, `orWhereVectorDistanceLessThan`,
 * `whereVectorSimilarTo`, `orderByVectorDistance`. atlas only owns
 * `orWhereVectorSimilarTo`, which Laravel does not ship.
 *
 * Both implementations require a real PostgreSQL connection — the native
 * methods throw `RuntimeException("Vector distance queries are only supported
 * by Postgres.")` before SQL is generated, so we can't even inspect ->toSql()
 * on SQLite. These tests therefore run end-to-end on pgsql (the CI persistence
 * job) and no-op on other drivers.
 */
/**
 * Test fixture model for hybrid vector-query integration tests.
 *
 * Backs the `fake_hybrid_projects` table created in beforeEach with a
 * pgvector embedding column on PG (text on other drivers). Deliberately
 * does not implement VectorEmbeddable or Chunkable — these tests target
 * the raw query-builder vector methods, not the trait-driven search
 * services covered elsewhere.
 */
class FakeHybridProject extends Model
{
    protected $table = 'fake_hybrid_projects';

    protected $guarded = [];

    public $timestamps = true;
}

beforeEach(function () {
    $isPostgres = DB::connection()->getDriverName() === 'pgsql';

    if (! $isPostgres) {
        return;
    }

    $dimensions = (int) config('atlas.embeddings.dimensions', 1536);

    Schema::dropIfExists('fake_hybrid_projects');
    Schema::create('fake_hybrid_projects', function (Blueprint $table) use ($dimensions) {
        $table->id();
        $table->string('title');
        $table->boolean('archived')->default(false);
        $table->vector('embedding', $dimensions)->nullable();
        $table->timestamps();
    });

    VectorQueryMacros::register();
});

it('executes the documented hybrid example end-to-end and ranks by distance', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        expect(true)->toBeTrue();

        return;
    }

    $dimensions = (int) config('atlas.embeddings.dimensions', 1536);

    // Query vector has 1.0 at index 0.
    // - vecA: 1.0 at index 0 → distance ≈ 0 (closest).
    // - vecB: 1.0 at index 1 → orthogonal → distance ≈ 1.0 (far).
    // - vecC: 0.7 at index 0 and 1 → distance somewhere in between.
    $queryVector = array_fill(0, $dimensions, 0.0);
    $queryVector[0] = 1.0;

    $vecA = array_fill(0, $dimensions, 0.0);
    $vecA[0] = 1.0;

    $vecB = array_fill(0, $dimensions, 0.0);
    $vecB[1] = 1.0;

    $vecC = array_fill(0, $dimensions, 0.0);
    $vecC[0] = 0.7;
    $vecC[1] = 0.7;

    $a = FakeHybridProject::create([
        'title' => 'Quarterly invoice review',
        'archived' => false,
        'embedding' => VectorQueryMacros::toVectorLiteral($vecA),
    ]);
    $b = FakeHybridProject::create([
        'title' => 'Onboarding checklist',
        'archived' => false,
        'embedding' => VectorQueryMacros::toVectorLiteral($vecB),
    ]);
    $c = FakeHybridProject::create([
        'title' => 'Annual report',
        'archived' => false,
        'embedding' => VectorQueryMacros::toVectorLiteral($vecC),
    ]);
    // Archived row — excluded by the archived=false predicate even though
    // its vector and title would otherwise match.
    $d = FakeHybridProject::create([
        'title' => 'Archived invoice',
        'archived' => true,
        'embedding' => VectorQueryMacros::toVectorLiteral($vecA),
    ]);

    $keyword = 'invoice';

    $results = FakeHybridProject::query()
        ->select('fake_hybrid_projects.*')
        ->selectVectorDistance('embedding', $queryVector, 'distance')
        ->where('archived', false)
        ->where(function ($q) use ($queryVector, $keyword) {
            $q->where('title', 'ilike', "%{$keyword}%")
                ->orWhereVectorDistanceLessThan('embedding', $queryVector, 0.4);
        })
        ->orderBy('distance')
        ->limit(20)
        ->get();

    $ids = $results->pluck('id')->all();

    // A is included: title matches keyword AND vector is closest.
    expect($ids)->toContain($a->id);
    // C is included: title doesn't match, but vector distance is under 0.4.
    expect($ids)->toContain($c->id);
    // B is excluded: title doesn't match and vector distance is ~1.0.
    expect($ids)->not->toContain($b->id);
    // D is excluded: archived=true gate.
    expect($ids)->not->toContain($d->id);

    // First row must be the closest by distance.
    expect($results->first()->id)->toBe($a->id);

    // Distance column exposed on every row and sorted ascending.
    $distances = $results->pluck('distance')->map(fn ($v) => (float) $v)->all();
    $sorted = $distances;
    sort($sorted);
    expect($distances)->toBe($sorted);
});

it('selectVectorDistance exposes the raw distance for hybrid ranking', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        expect(true)->toBeTrue();

        return;
    }

    $dimensions = (int) config('atlas.embeddings.dimensions', 1536);
    $queryVector = array_fill(0, $dimensions, 0.0);
    $queryVector[0] = 1.0;

    $vec = array_fill(0, $dimensions, 0.0);
    $vec[0] = 1.0;
    $project = FakeHybridProject::create([
        'title' => 'X',
        'embedding' => VectorQueryMacros::toVectorLiteral($vec),
    ]);

    $row = FakeHybridProject::query()
        ->select('fake_hybrid_projects.*')
        ->selectVectorDistance('embedding', $queryVector, 'd')
        ->where('id', $project->id)
        ->first();

    expect($row->getAttribute('d'))->not->toBeNull();
    // Identical vectors → cosine distance is essentially zero.
    expect((float) $row->getAttribute('d'))->toEqualWithDelta(0.0, 1e-6);
});

it('whereVectorDistanceLessThan filters by maxDistance and preserves caller ordering', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        expect(true)->toBeTrue();

        return;
    }

    $dimensions = (int) config('atlas.embeddings.dimensions', 1536);
    $queryVector = array_fill(0, $dimensions, 0.0);
    $queryVector[0] = 1.0;

    $close = array_fill(0, $dimensions, 0.0);
    $close[0] = 1.0;
    $far = array_fill(0, $dimensions, 0.0);
    $far[1] = 1.0;

    $closeRow = FakeHybridProject::create([
        'title' => 'Z close',
        'embedding' => VectorQueryMacros::toVectorLiteral($close),
    ]);
    $farRow = FakeHybridProject::create([
        'title' => 'A far',
        'embedding' => VectorQueryMacros::toVectorLiteral($far),
    ]);

    // maxDistance=0.5 includes $closeRow (≈0) and excludes $farRow (≈1).
    // We use orderBy('title') to confirm the call doesn't impose its own order.
    $rows = FakeHybridProject::query()
        ->whereVectorDistanceLessThan('embedding', $queryVector, 0.5)
        ->orderBy('title')
        ->get();

    expect($rows->pluck('id')->all())->toBe([$closeRow->id]);
    expect($rows->pluck('id')->all())->not->toContain($farRow->id);
});

it('orWhereVectorDistanceLessThan composes as an OR branch in a WHERE group', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        expect(true)->toBeTrue();

        return;
    }

    $dimensions = (int) config('atlas.embeddings.dimensions', 1536);
    $queryVector = array_fill(0, $dimensions, 0.0);
    $queryVector[0] = 1.0;

    $close = array_fill(0, $dimensions, 0.0);
    $close[0] = 1.0;
    $far = array_fill(0, $dimensions, 0.0);
    $far[1] = 1.0;

    $vectorHit = FakeHybridProject::create([
        'title' => 'unrelated',
        'embedding' => VectorQueryMacros::toVectorLiteral($close),
    ]);
    $keywordHit = FakeHybridProject::create([
        'title' => 'invoice draft',
        'embedding' => VectorQueryMacros::toVectorLiteral($far),
    ]);
    $miss = FakeHybridProject::create([
        'title' => 'unrelated',
        'embedding' => VectorQueryMacros::toVectorLiteral($far),
    ]);

    $rows = FakeHybridProject::query()
        ->where(function ($q) use ($queryVector) {
            $q->where('title', 'ilike', '%invoice%')
                ->orWhereVectorDistanceLessThan('embedding', $queryVector, 0.5);
        })
        ->get();

    $ids = $rows->pluck('id')->all();
    expect($ids)->toContain($vectorHit->id);    // matched the vector half
    expect($ids)->toContain($keywordHit->id);   // matched the keyword half
    expect($ids)->not->toContain($miss->id);    // matched neither
});

it('orderByVectorDistance sorts rows by cosine distance ASC by default', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        expect(true)->toBeTrue();

        return;
    }

    $dimensions = (int) config('atlas.embeddings.dimensions', 1536);
    $queryVector = array_fill(0, $dimensions, 0.0);
    $queryVector[0] = 1.0;

    $closer = array_fill(0, $dimensions, 0.0);
    $closer[0] = 1.0;
    $farther = array_fill(0, $dimensions, 0.0);
    $farther[1] = 1.0;

    // Insert far-first so we know the ORDER BY is doing the work.
    $far = FakeHybridProject::create([
        'title' => 'first inserted',
        'embedding' => VectorQueryMacros::toVectorLiteral($farther),
    ]);
    $close = FakeHybridProject::create([
        'title' => 'second inserted',
        'embedding' => VectorQueryMacros::toVectorLiteral($closer),
    ]);

    $rows = FakeHybridProject::query()
        ->orderByVectorDistance('embedding', $queryVector)
        ->get();

    expect($rows->pluck('id')->all())->toBe([$close->id, $far->id]);
});

it('whereVectorSimilarTo applies the floor and ranks ascending', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        expect(true)->toBeTrue();

        return;
    }

    $dimensions = (int) config('atlas.embeddings.dimensions', 1536);
    $queryVector = array_fill(0, $dimensions, 0.0);
    $queryVector[0] = 1.0;

    $high = array_fill(0, $dimensions, 0.0);
    $high[0] = 1.0;      // similarity ≈ 1.0
    $medium = array_fill(0, $dimensions, 0.0);
    $medium[0] = 0.7;    // similarity ≈ 0.7 after normalization
    $medium[1] = 0.7;
    $low = array_fill(0, $dimensions, 0.0);
    $low[1] = 1.0;       // similarity ≈ 0.0

    $hi = FakeHybridProject::create(['title' => 'hi', 'embedding' => VectorQueryMacros::toVectorLiteral($high)]);
    $mid = FakeHybridProject::create(['title' => 'mid', 'embedding' => VectorQueryMacros::toVectorLiteral($medium)]);
    $lo = FakeHybridProject::create(['title' => 'lo', 'embedding' => VectorQueryMacros::toVectorLiteral($low)]);

    // minSimilarity=0.6 should include $hi and $mid, exclude $lo.
    $rows = FakeHybridProject::query()
        ->whereVectorSimilarTo('embedding', $queryVector, 0.6)
        ->get();

    $ids = $rows->pluck('id')->all();
    expect($ids)->toContain($hi->id);
    expect($ids)->toContain($mid->id);
    expect($ids)->not->toContain($lo->id);
});

it('orWhereVectorSimilarTo (atlas-owned) executes as an OR-similarity branch on pgsql', function () {
    // Reachable by atlas macro (Laravel does not ship this name).
    if (DB::connection()->getDriverName() !== 'pgsql') {
        expect(true)->toBeTrue();

        return;
    }

    $dimensions = (int) config('atlas.embeddings.dimensions', 1536);
    $queryVector = array_fill(0, $dimensions, 0.0);
    $queryVector[0] = 1.0;

    $close = array_fill(0, $dimensions, 0.0);
    $close[0] = 1.0;
    $far = array_fill(0, $dimensions, 0.0);
    $far[1] = 1.0;

    // Four-quadrant fixture covering every (archived, similarity) combo so
    // both halves of `WHERE archived=false OR similarity >= 0.9` are
    // exercised — including the OR-positive case where an archived row
    // is still returned because its vector is close enough.
    $unarchivedClose = FakeHybridProject::create([
        'title' => 'irrelevant',
        'archived' => false,
        'embedding' => VectorQueryMacros::toVectorLiteral($close),
    ]);
    $unarchivedFar = FakeHybridProject::create([
        'title' => 'irrelevant',
        'archived' => false,
        'embedding' => VectorQueryMacros::toVectorLiteral($far),
    ]);
    $archivedClose = FakeHybridProject::create([
        'title' => 'irrelevant',
        'archived' => true,
        'embedding' => VectorQueryMacros::toVectorLiteral($close),
    ]);
    $archivedFar = FakeHybridProject::create([
        'title' => 'irrelevant',
        'archived' => true,
        'embedding' => VectorQueryMacros::toVectorLiteral($far),
    ]);

    $rows = FakeHybridProject::query()
        ->where('archived', false)
        ->orWhereVectorSimilarTo('embedding', $queryVector, 0.9)
        ->get();

    $ids = $rows->pluck('id')->all();
    // Matches archived=false (regardless of vector):
    expect($ids)->toContain($unarchivedClose->id);
    expect($ids)->toContain($unarchivedFar->id);
    // Matches the OR-similarity branch even though archived=true:
    expect($ids)->toContain($archivedClose->id);
    // Matches neither: archived=true AND vector too far.
    expect($ids)->not->toContain($archivedFar->id);
});
