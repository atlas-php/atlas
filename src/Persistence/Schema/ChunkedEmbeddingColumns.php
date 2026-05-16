<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Schema;

use Illuminate\Database\Schema\Blueprint;

/**
 * Schema helper for adding chunked-embedding tracking columns to a consumer model's table.
 *
 * Adds the five columns the HasChunkedEmbeddings trait expects:
 *  - content_hash, indexed_hash: drives the dirty-row detection.
 *  - indexed_at: last successful chunk run.
 *  - last_index_error, index_failure_count: backoff for repeated failures.
 *
 * Also adds a composite btree index on (content_hash, indexed_hash) for
 * diagnostic point lookups.
 *
 * Scale note. The safety-net sweep's dirty predicate is
 * `content_hash IS DISTINCT FROM indexed_hash`, which a regular btree
 * cannot serve — Postgres has no equality/range bound to use either
 * column. With dispatch-on-save enabled (default) this rarely matters:
 * the safety net runs hourly and processes a small bounded batch.
 * Consumers expecting >1M rows in a chunkable table — particularly if
 * they also disable dispatch-on-save — should add a partial index in
 * their own migration:
 *
 *   CREATE INDEX {table}_dirty_idx
 *   ON {table} (updated_at)
 *   WHERE content_hash IS DISTINCT FROM indexed_hash;
 *
 * Atlas does not create this automatically — it's Postgres-only DDL and
 * the cost/benefit only kicks in at scale.
 *
 * Usage in a consumer migration:
 *
 *   use Atlasphp\Atlas\Persistence\Schema\ChunkedEmbeddingColumns;
 *
 *   Schema::table('projects', function (Blueprint $table) {
 *       ChunkedEmbeddingColumns::add($table);
 *   });
 */
final class ChunkedEmbeddingColumns
{
    public static function add(Blueprint $table): void
    {
        $table->char('content_hash', 32)->nullable();
        $table->char('indexed_hash', 32)->nullable();
        $table->timestamp('indexed_at')->nullable();
        $table->text('last_index_error')->nullable();
        $table->unsignedSmallInteger('index_failure_count')->default(0);
        $table->index(['content_hash', 'indexed_hash']);
    }

    public static function drop(Blueprint $table): void
    {
        $table->dropIndex(['content_hash', 'indexed_hash']);
        $table->dropColumn([
            'content_hash',
            'indexed_hash',
            'indexed_at',
            'last_index_error',
            'index_failure_count',
        ]);
    }
}
