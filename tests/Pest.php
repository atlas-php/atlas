<?php

declare(strict_types=1);

use Atlasphp\Atlas\Tests\PersistenceTestCase;
use Atlasphp\Atlas\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

// Persistence tests use PersistenceTestCase which loads migrations and enables persistence config.
// Registered before TestCase bindings to avoid duplicate-folder conflicts.
pest()->extend(PersistenceTestCase::class)->in('Unit/Persistence');
pest()->extend(PersistenceTestCase::class)->in('Feature/Persistence');

// Feature tests — list subdirectories and individual top-level files
// to avoid conflicting with Feature/Persistence binding above.
pest()->extend(TestCase::class)->in(
    'Feature/Console',
    'Feature/Testing',
    'Feature/Variables',
    'Feature/Voice',
    'Feature/AtlasManagerEntryPointTest.php',
    'Feature/AtlasManagerMissingDefaultTest.php',
    'Feature/AtlasServiceProviderTest.php',
    'Feature/ConfigTest.php',
    'Feature/CountTokensTest.php',
    'Feature/FacadeTest.php',
    'Feature/ProviderRegistryTest.php',
);
pest()->extend(TestCase::class)->in(
    'Unit/Agents',
    'Unit/AgentTest.php',
    'Unit/AtlasConfigTest.php',
    'Unit/AtlasServiceProviderTest.php',
    'Unit/Concerns',
    'Unit/Console',
    'Unit/Embeddings',
    'Unit/Enums',
    'Unit/Events',
    'Unit/Http',
    'Unit/Exceptions',
    'Unit/Executor',
    'Unit/Input',
    'Unit/Messages',
    'Unit/Middleware',
    'Unit/Pending',
    'Unit/Providers',
    'Unit/Queue',
    'Unit/RequestConfigTest.php',
    'Unit/Requests',
    'Unit/Responses',
    'Unit/Schema',
    'Unit/Streaming',
    'Unit/Support',
    'Unit/Testing',
    'Unit/Tools',
);

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Driver-aware fixture for the atlas_chunks `embedding` column.
 *
 * On PostgreSQL the column is a NOT NULL `vector(N)`; tests seeding chunks
 * directly must provide a value. On SQLite the column doesn't exist (the
 * migration is gated by isPostgres()), so we return an empty array.
 *
 * Returned as a float array — the Chunk model's `array` cast JSON-encodes
 * on save into `[0,0,...]` which pgvector accepts as a vector literal.
 *
 * @return array{embedding?: array<int, float>}
 */
function fakeChunkEmbedding(): array
{
    if (DB::connection()->getDriverName() !== 'pgsql') {
        return [];
    }

    return ['embedding' => fakeEmbeddingVector()];
}

/**
 * A fake embedding vector sized to match the configured dimensions.
 *
 * Sqlite tests can use any size (the column doesn't exist on that driver),
 * but pgvector enforces the column's declared dimension. This helper
 * returns a vector of the configured size — defaults to 1536 — so the
 * same test code works against both drivers.
 *
 * @return array<int, float>
 */
function fakeEmbeddingVector(float $seed = 0.0): array
{
    $dimensions = (int) config('atlas.embeddings.dimensions', 1536);
    $vector = array_fill(0, $dimensions, 0.0);
    if ($seed !== 0.0) {
        $vector[0] = $seed;
    }

    return $vector;
}

/**
 * A pgvector literal string (`[0.1,0,…]`) for tests seeding record models
 * that don't have an `array` cast on the embedding column.
 *
 * On non-PG drivers returns an empty string (the column doesn't exist or
 * is a TEXT column the test doesn't care about).
 */
function fakeEmbeddingLiteral(float $seed = 0.0): string
{
    return '['.implode(',', fakeEmbeddingVector($seed)).']';
}
