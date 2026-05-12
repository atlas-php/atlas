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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Adds chunked embedding support to an Eloquent model.
 *
 * Maintains a content_hash column on save (so the sweep can find dirty rows)
 * and cascade-deletes related atlas_chunks rows on delete (no FK because the
 * relation is polymorphic). The actual chunking + embedding happens out of
 * band in the atlas:chunk artisan command — saving is intentionally
 * lightweight and synchronous.
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
