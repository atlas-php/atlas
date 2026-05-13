<?php

declare(strict_types=1);

use Atlasphp\Atlas\Embeddings\Chunkable;
use Atlasphp\Atlas\Embeddings\ChunkableRegistry;
use Atlasphp\Atlas\Persistence\Concerns\HasChunkedEmbeddings;
use Illuminate\Database\Eloquent\Model;

class FakeChunkableModelForRegistry extends Model implements Chunkable
{
    use HasChunkedEmbeddings;

    protected $guarded = [];
}

it('registers a model class', function () {
    $registry = new ChunkableRegistry;
    $registry->register(FakeChunkableModelForRegistry::class);

    expect($registry->has(FakeChunkableModelForRegistry::class))->toBeTrue()
        ->and($registry->all())->toBe([FakeChunkableModelForRegistry::class]);
});

it('is idempotent — duplicate registers do not duplicate entries', function () {
    $registry = new ChunkableRegistry;
    $registry->register(FakeChunkableModelForRegistry::class);
    $registry->register(FakeChunkableModelForRegistry::class);

    expect($registry->all())->toHaveCount(1);
});

it('rejects non-Eloquent classes', function () {
    $registry = new ChunkableRegistry;

    expect(fn () => $registry->register(stdClass::class))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects models that do not implement Chunkable', function () {
    $registry = new ChunkableRegistry;

    $modelOnlyClass = new class extends Model {};

    expect(fn () => $registry->register($modelOnlyClass::class))
        ->toThrow(InvalidArgumentException::class, 'must implement');
});

it('clears all registrations', function () {
    $registry = new ChunkableRegistry;
    $registry->register(FakeChunkableModelForRegistry::class);
    $registry->clear();

    expect($registry->all())->toBe([]);
});
