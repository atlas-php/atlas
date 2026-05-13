<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Schema;

use Illuminate\Database\Schema\Blueprint;

/**
 * Schema helper for adding chunked-embedding tracking columns to a consumer model's table.
 *
 * Adds the five columns the HasChunkedEmbeddings trait expects:
 *  - content_hash, indexed_hash: drives the dirty-row detection in the sweep.
 *  - indexed_at: last successful chunk run.
 *  - last_index_error, index_failure_count: backoff for repeated failures.
 *
 * Also adds a composite index on (content_hash, indexed_hash) — useful for
 * point lookups on either column during diagnostics. The sweep's main
 * dirty-row predicate (`IS DISTINCT FROM`) isn't directly served by this
 * index; that path benefits from the partial index the sweep creates at
 * its own query layer.
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
