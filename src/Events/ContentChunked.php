<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

/**
 * Dispatched after a chunked-embedding reconciliation completes successfully.
 *
 * embeddedCount is the number of chunks that actually hit the embedding
 * provider this run (i.e. new or changed chunks). chunkCount is the total
 * chunks now present for the record.
 */
class ContentChunked
{
    public function __construct(
        public readonly string $chunkableType,
        public readonly int $chunkableId,
        public readonly int $chunkCount,
        public readonly int $embeddedCount,
    ) {}
}
