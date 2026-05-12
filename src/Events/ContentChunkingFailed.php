<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

/**
 * Dispatched after a chunked-embedding reconciliation fails.
 *
 * The owning record's index_failure_count is incremented before this event
 * fires; once it hits config('atlas.embeddings.max_failures') the record is
 * excluded from future sweeps until a consumer resets it.
 */
class ContentChunkingFailed
{
    public function __construct(
        public readonly string $chunkableType,
        public readonly int $chunkableId,
        public readonly string $error,
    ) {}
}
