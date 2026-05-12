<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Embeddings;

use Atlasphp\Atlas\Embeddings\Chunkers\Chunker;
use Atlasphp\Atlas\Persistence\Models\Chunk;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Contract for any Eloquent model that participates in chunked-embedding indexing.
 *
 * Consumer models satisfy this interface by using the HasChunkedEmbeddings
 * trait (which provides default implementations of every method here) and
 * declaring `implements Chunkable` on the model class itself.
 *
 * Implementing the interface explicitly is required so the chunking services
 * have a precise type to operate against, rather than reaching for trait
 * methods on a bare Eloquent Model.
 */
interface Chunkable
{
    /** @return MorphMany<Chunk, Model> */
    public function chunks(): MorphMany;

    public function getChunkableField(): string;

    public function getChunkableContent(): string;

    public function shouldBeChunked(): bool;

    public function resolveChunker(): Chunker;
}
