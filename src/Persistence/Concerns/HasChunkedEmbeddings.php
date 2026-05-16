<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Concerns;

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Embeddings\Chunkable;
use Atlasphp\Atlas\Embeddings\ChunkableRegistry;
use Atlasphp\Atlas\Embeddings\Chunkers\Chunker;
use Atlasphp\Atlas\Embeddings\Chunkers\MarkdownChunker;
use Atlasphp\Atlas\Persistence\Models\Chunk;
use Atlasphp\Atlas\Persistence\Services\ChunkContentService;
use Atlasphp\Atlas\Queue\Jobs\ChunkContentJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Queue\SyncQueue;
use Illuminate\Support\Facades\Queue;

/**
 * Adds chunked embedding support to an Eloquent model.
 *
 * The trait wires three hooks:
 *
 *  1. `saving` — stamps `content_hash` from the chunkable field whenever
 *     the field is dirty. Cheap, synchronous, runs on every save.
 *  2. `saved` — dispatches `ChunkContentJob` with a settle-window delay
 *     when `content_hash` actually changed. This is the on-demand
 *     trigger that replaces polling: chunking happens within ~settle
 *     seconds of an edit instead of waiting for the next sweep tick.
 *     Disable with `atlas.embeddings.dispatch_on_save = false` to fall
 *     back to sweep-only behavior.
 *  3. `deleting` — cascade-deletes chunks for this owner (the relation
 *     is polymorphic, so no FK can carry the delete).
 *
 * The `atlas:chunk` sweep stays as a backstop — it catches rows that
 * bypass the save hook (raw `DB::table()->update()`, factories, etc.)
 * and rows where dispatch was lost (Redis crash, worker outage). With
 * dispatch-on-save enabled, the recommended cadence drops from
 * every-minute to hourly.
 *
 * Multi-tenant: dispatch runs in the request's tenant context, so the
 * queued job inherits the right connection through whichever tenancy
 * package the consumer uses. Atlas itself stores nothing about tenancy.
 *
 * To opt a model in:
 *   1. Add the trait
 *   2. Set $chunkableField if your column isn't `body`
 *   3. Add the chunked-embedding columns via ChunkedEmbeddingColumns::add()
 *   4. Register in AppServiceProvider::boot():
 *        Atlas::registerChunkable(\App\Models\Project::class);
 *
 * The trait also self-registers on first model touch, but explicit
 * registration in a service provider is required for CLI commands to
 * see the model (a fresh artisan process touches no models before the
 * sweep runs, so the trait's boot hook hasn't fired yet).
 *
 * @phpstan-require-extends Model
 *
 * @phpstan-require-implements Chunkable
 */
trait HasChunkedEmbeddings
{
    public static function bootHasChunkedEmbeddings(): void
    {
        app(ChunkableRegistry::class)->register(static::class);

        static::saving(function (Chunkable&Model $model): void {
            $field = $model->getChunkableField();
            if (! $model->isDirty($field)) {
                return;
            }

            $value = (string) ($model->getAttribute($field) ?? '');
            $model->setAttribute(
                'content_hash',
                $value === '' ? null : hash('xxh128', $value),
            );
        });

        static::saved(function (Chunkable&Model $model): void {
            // `wasChanged()` only reports changes on UPDATE — Eloquent
            // does not call `syncChanges()` in `performInsert()`, so a
            // fresh INSERT looks unchanged. Compare the current attribute
            // against `getOriginal()` instead: that returns null for
            // newly-created rows (their original snapshot hasn't been
            // synced yet at the `saved` event) and the prior value for
            // updates.
            if ($model->getAttribute('content_hash') === $model->getOriginal('content_hash')) {
                return;
            }

            $config = app(AtlasConfig::class);
            if (! $config->chunkDispatchOnSave) {
                return;
            }

            // The sync queue runs jobs immediately and treats `release()`
            // as a no-op delete (the job vanishes), while ShouldBeUnique
            // keeps the lock held for `uniqueFor` seconds — so a sync
            // consumer would lose chunked work and block re-dispatch.
            // Skip the dispatch path entirely on sync; the safety-net
            // sweep still catches the dirty row. Resolves the default
            // queue connection (the one the dispatch would actually use).
            if (Queue::connection() instanceof SyncQueue) {
                return;
            }

            // `$model::class` not `static::class` — `static` here resolves
            // to the trait-using class at trait-boot time, but multiple
            // models share the trait and the closure runs late, so we
            // want the actual instance's class. ChunkContentJob uses this
            // to query::find(); morph map aliases would silently break
            // that lookup.
            //
            // The whole dispatch is wrapped in `Connection::afterCommit()`
            // so that everything — including the ShouldBeUnique cache lock
            // acquisition inside `PendingDispatch::__destruct` — runs
            // OUTSIDE any wrapping `DB::transaction()`. The Laravel-shipped
            // `->afterCommit()` modifier on PendingDispatch only defers the
            // queue push, not the lock check; with the database cache
            // driver on Postgres the lock's INSERT-then-fallback-UPDATE
            // pattern aborts the wrapping transaction (SQLSTATE 25P02).
            //
            // `Connection::afterCommit` invokes the closure immediately
            // when no transaction is active and defers it to commit
            // otherwise — works on every SQL driver (the method ships on
            // the base Connection class), every cache driver (lock acquire
            // always happens outside the transaction), every queue driver,
            // with no consumer configuration. Using the model's connection
            // (not the default) keeps tenant-per-connection setups correct.
            $modelClass = $model::class;
            $modelKey = $model->getKey();
            $queue = $config->queue;
            $delaySeconds = $config->chunkSweepSettle;

            $model->getConnection()->afterCommit(static function () use ($modelClass, $modelKey, $queue, $delaySeconds): void {
                ChunkContentJob::dispatch($modelClass, $modelKey)
                    ->onQueue($queue)
                    ->delay(now()->addSeconds($delaySeconds));
            });
        });

        static::deleting(function (Model $model): void {
            /** @var class-string<Chunk> $chunkModel */
            $chunkModel = app(AtlasConfig::class)->model('chunk', Chunk::class);

            $chunkModel::query()
                ->where('chunkable_type', $model->getMorphClass())
                ->where('chunkable_id', $model->getKey())
                ->delete();
        });
    }

    /**
     * Column holding the indexable content. Default `body`; override on the
     * consuming model by declaring `protected string $chunkableField = '…';`.
     *
     * (Trait properties can't be overridden with different defaults in the
     * using class — PHP fatals. So this is the property_exists pattern.)
     */
    public function getChunkableField(): string
    {
        return property_exists($this, 'chunkableField')
            ? $this->chunkableField
            : 'body';
    }

    /**
     * Should this specific record be chunked?
     *
     * Override to scope by domain rules (only published, only owned by
     * paying users, etc.). Default: only when the field is non-empty.
     */
    public function shouldBeChunked(): bool
    {
        $value = $this->getAttribute($this->getChunkableField());

        return $value !== null && (string) $value !== '';
    }

    /**
     * The exact text the chunker operates on. Override to inject synthetic
     * context (document title, chapter number, etc.) ahead of the raw field.
     */
    public function getChunkableContent(): string
    {
        return (string) ($this->getAttribute($this->getChunkableField()) ?? '');
    }

    /** @return MorphMany<Chunk, $this> */
    public function chunks(): MorphMany
    {
        /** @var class-string<Chunk> $chunkModel */
        $chunkModel = app(AtlasConfig::class)->model('chunk', Chunk::class);

        return $this->morphMany($chunkModel, 'chunkable')->orderBy('ord');
    }

    /**
     * Synchronously chunk + embed + write this record now.
     *
     * Use when you want chunking without scheduling the sweep command and
     * without dispatching a queue job — for example in a controller after a
     * save, or in tests. Equivalent to one iteration of what atlas:chunk
     * would do on a dirty row.
     *
     * Does NOT require persistence.enabled. Requires only that:
     *   - the atlas_chunks table exists (run the package migration)
     *   - an embedding provider is configured (atlas.defaults.embed)
     *   - the consuming table has the ChunkedEmbeddingColumns columns
     */
    public function chunkNow(): void
    {
        /** @var Chunkable&Model $self */
        $self = $this;
        app(ChunkContentService::class)->reconcile($self);
    }

    /**
     * Resolve the chunker instance. Order of precedence:
     *   1. Per-model override: declare `protected ?string $chunker = MyChunker::class;` on the model.
     *   2. Global default from config('atlas.embeddings.chunker').
     *   3. MarkdownChunker.
     */
    public function resolveChunker(): Chunker
    {
        $perModel = property_exists($this, 'chunker') ? $this->chunker : null;
        $configured = app(AtlasConfig::class)->chunker;
        $class = $perModel ?? $configured ?? MarkdownChunker::class;

        /** @var Chunker $instance */
        $instance = app($class);

        return $instance;
    }
}
