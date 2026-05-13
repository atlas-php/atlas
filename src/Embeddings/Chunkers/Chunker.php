<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Embeddings\Chunkers;

use Atlasphp\Atlas\Embeddings\ChunkData;

/**
 * Contract for splitting long content into embeddable chunks.
 *
 * Implementations must be pure: same input → same output → same hashes,
 * every time. Any non-determinism breaks the diff algorithm and causes
 * chunks to churn on every sweep.
 *
 * Custom chunkers (plain text, source code, transcripts, structured data)
 * implement this interface directly or extend BaseTokenAwareChunker to
 * reuse the split-and-pack helpers.
 */
interface Chunker
{
    /**
     * @return array<int, ChunkData>
     */
    public function chunk(string $content): array;
}
