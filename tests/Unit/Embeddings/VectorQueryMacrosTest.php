<?php

declare(strict_types=1);

use Atlasphp\Atlas\Embeddings\EmbeddingResolver;
use Atlasphp\Atlas\Embeddings\VectorQueryMacros;

it('formats vector array as pgvector literal', function () {
    $literal = VectorQueryMacros::toVectorLiteral([0.1, 0.2, 0.3]);

    expect($literal)->toBe('[0.1,0.2,0.3]');
});

it('formats integer values in vector literal', function () {
    $literal = VectorQueryMacros::toVectorLiteral([1, 0, -1]);

    expect($literal)->toBe('[1,0,-1]');
});

it('formats empty vector literal', function () {
    $literal = VectorQueryMacros::toVectorLiteral([]);

    expect($literal)->toBe('[]');
});

it('passes arrays through resolveEmbedding unchanged', function () {
    $vector = [0.1, 0.2, 0.3];

    $result = VectorQueryMacros::resolveEmbedding($vector);

    expect($result)->toBe($vector);
});

it('calls EmbeddingResolver for string input', function () {
    $vector = [0.4, 0.5, 0.6];

    $resolver = Mockery::mock(EmbeddingResolver::class);
    $resolver->shouldReceive('resolve')
        ->with('test query')
        ->once()
        ->andReturn($vector);

    app()->instance(EmbeddingResolver::class, $resolver);

    $result = VectorQueryMacros::resolveEmbedding('test query');

    expect($result)->toBe($vector);
});

it('validates acceptable column names', function () {
    // These should not throw
    VectorQueryMacros::validateColumnName('embedding');
    VectorQueryMacros::validateColumnName('content_embedding');
    VectorQueryMacros::validateColumnName('table.column');
    VectorQueryMacros::validateColumnName('_private');

    expect(true)->toBeTrue();
});

it('rejects SQL injection in column names', function () {
    VectorQueryMacros::validateColumnName("'; DROP TABLE users --");
})->throws(InvalidArgumentException::class);

it('rejects column names with spaces', function () {
    VectorQueryMacros::validateColumnName('my column');
})->throws(InvalidArgumentException::class);

it('rejects column names with parentheses', function () {
    VectorQueryMacros::validateColumnName('col()');
})->throws(InvalidArgumentException::class);

it('validates acceptable alias names', function () {
    VectorQueryMacros::validateAliasName('vector_distance');
    VectorQueryMacros::validateAliasName('similarity');
    VectorQueryMacros::validateAliasName('_score');

    expect(true)->toBeTrue();
});

it('rejects dots in alias names', function () {
    VectorQueryMacros::validateAliasName('table.alias');
})->throws(InvalidArgumentException::class);

it('rejects SQL injection in alias names', function () {
    VectorQueryMacros::validateAliasName("'; DROP TABLE --");
})->throws(InvalidArgumentException::class);

it('detects pgsql driver for pgvector availability', function () {
    config(['database.default' => 'pgsql']);

    expect(VectorQueryMacros::isPgvectorAvailable())->toBeTrue();
});

it('detects non-pgsql driver as unavailable', function () {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.driver' => 'sqlite',
    ]);

    expect(VectorQueryMacros::isPgvectorAvailable())->toBeFalse();
});
