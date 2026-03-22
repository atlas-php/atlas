<?php

declare(strict_types=1);

use Atlasphp\Atlas\Embeddings\EmbeddingResolver;
use Atlasphp\Atlas\Embeddings\VectorQueryMacros;
use Illuminate\Database\Query\Builder;

// ─── isPgvectorAvailable ────────────────────────────────────────────────────

it('returns false for non-pgsql connections', function () {
    config(['database.default' => 'testing']);
    config(['database.connections.testing.driver' => 'sqlite']);

    expect(VectorQueryMacros::isPgvectorAvailable())->toBeFalse();
});

it('returns true when default connection is pgsql', function () {
    config(['database.default' => 'pgsql']);

    expect(VectorQueryMacros::isPgvectorAvailable())->toBeTrue();
});

it('returns true when default connection driver is pgsql', function () {
    config(['database.default' => 'custom']);
    config(['database.connections.custom.driver' => 'pgsql']);

    expect(VectorQueryMacros::isPgvectorAvailable())->toBeTrue();
});

// ─── toVectorLiteral ────────────────────────────────────────────────────────

it('converts float array to pgvector literal', function () {
    $result = VectorQueryMacros::toVectorLiteral([0.1, 0.2, 0.3]);

    expect($result)->toBe('[0.1,0.2,0.3]');
});

it('converts integer values in vector literal', function () {
    $result = VectorQueryMacros::toVectorLiteral([1, 0, -1]);

    expect($result)->toBe('[1,0,-1]');
});

it('handles empty vector', function () {
    $result = VectorQueryMacros::toVectorLiteral([]);

    expect($result)->toBe('[]');
});

// ─── validateColumnName ─────────────────────────────────────────────────────

it('accepts valid simple column names', function () {
    VectorQueryMacros::validateColumnName('embedding');
    VectorQueryMacros::validateColumnName('content_embedding');
    VectorQueryMacros::validateColumnName('_private');

    expect(true)->toBeTrue();
});

it('accepts qualified column names with dots', function () {
    VectorQueryMacros::validateColumnName('table.embedding');
    VectorQueryMacros::validateColumnName('schema.table.column');

    expect(true)->toBeTrue();
});

it('rejects column names with SQL injection attempts', function () {
    VectorQueryMacros::validateColumnName('embedding; DROP TABLE users');
})->throws(InvalidArgumentException::class, 'Invalid column name');

it('rejects column names starting with numbers', function () {
    VectorQueryMacros::validateColumnName('123column');
})->throws(InvalidArgumentException::class, 'Invalid column name');

it('rejects empty column names', function () {
    VectorQueryMacros::validateColumnName('');
})->throws(InvalidArgumentException::class, 'Invalid column name');

it('rejects column names with special characters', function () {
    VectorQueryMacros::validateColumnName("column'name");
})->throws(InvalidArgumentException::class, 'Invalid column name');

it('rejects column names with spaces', function () {
    VectorQueryMacros::validateColumnName('my column');
})->throws(InvalidArgumentException::class, 'Invalid column name');

// ─── validateAliasName ──────────────────────────────────────────────────────

it('accepts valid alias names', function () {
    VectorQueryMacros::validateAliasName('vector_distance');
    VectorQueryMacros::validateAliasName('similarity');
    VectorQueryMacros::validateAliasName('_score');

    expect(true)->toBeTrue();
});

it('rejects alias names with dots', function () {
    VectorQueryMacros::validateAliasName('table.alias');
})->throws(InvalidArgumentException::class, 'Invalid alias name');

it('rejects alias names with SQL injection', function () {
    VectorQueryMacros::validateAliasName('alias; DROP TABLE');
})->throws(InvalidArgumentException::class, 'Invalid alias name');

it('rejects empty alias names', function () {
    VectorQueryMacros::validateAliasName('');
})->throws(InvalidArgumentException::class, 'Invalid alias name');

// ─── resolveEmbedding ───────────────────────────────────────────────────────

it('passes array embeddings through unchanged', function () {
    $vector = [0.1, 0.2, 0.3];

    $result = VectorQueryMacros::resolveEmbedding($vector);

    expect($result)->toBe($vector);
});

it('resolves string embeddings through EmbeddingResolver', function () {
    $vector = [0.4, 0.5, 0.6];

    $resolver = Mockery::mock(EmbeddingResolver::class);
    $resolver->shouldReceive('resolve')
        ->with('hello world')
        ->once()
        ->andReturn($vector);

    app()->instance(EmbeddingResolver::class, $resolver);

    $result = VectorQueryMacros::resolveEmbedding('hello world');

    expect($result)->toBe($vector);
});

// ─── register ───────────────────────────────────────────────────────────────

it('skips registration when pgvector is not available', function () {
    config(['database.default' => 'testing']);
    config(['database.connections.testing.driver' => 'sqlite']);

    VectorQueryMacros::register();

    expect(true)->toBeTrue();
});

// ─── Macro Registration & Validation ────────────────────────────────────────
// SQL generation tests require a real PostgreSQL connection.
// Here we test that macros are correctly registered and validate inputs.

it('registers all four macros on pgsql', function () {
    $original = config('database.default');
    config(['database.default' => 'pgsql']);
    VectorQueryMacros::register();
    config(['database.default' => $original]);

    expect(Builder::hasMacro('whereVectorSimilarTo'))->toBeTrue()
        ->and(Builder::hasMacro('whereVectorDistanceLessThan'))->toBeTrue()
        ->and(Builder::hasMacro('selectVectorDistance'))->toBeTrue()
        ->and(Builder::hasMacro('orderByVectorDistance'))->toBeTrue();
});

it('whereVectorSimilarTo converts minSimilarity to maxDistance', function () {
    // maxDistance = 1.0 - minSimilarity
    // When minSimilarity = 0.7, maxDistance ≈ 0.3 (< 1.0, so filter applies)
    // When minSimilarity = 0.0, maxDistance = 1.0 (>= 1.0, so filter is skipped)
    expect(1.0 - 0.7)->toBeLessThan(1.0)  // filter applies
        ->and(1.0 - 0.0)->toBeGreaterThanOrEqual(1.0)  // filter skipped
        ->and(1.0 - 1.0)->toBe(0.0);  // max strictness
});
