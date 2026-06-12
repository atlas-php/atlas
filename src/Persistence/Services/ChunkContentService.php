<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Services;

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Embeddings\Chunkable;
use Atlasphp\Atlas\Embeddings\ChunkData;
use Atlasphp\Atlas\Embeddings\VectorQueryMacros;
use Atlasphp\Atlas\Events\ContentChunked;
use Atlasphp\Atlas\Events\ContentChunkingFailed;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Persistence\Concerns\ResolvesChunkModel;
use Atlasphp\Atlas\Persistence\Models\Chunk;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Reconciles a single record's chunks against the current state of its
 * indexable content.
 *
 * The job is intentionally simple but unavoidably stateful: chunks live in
 * a side table that must remain consistent with the owner's content. The
 * algorithm — chunk → diff by content_hash → embed only new chunks → write
 * inside a transaction → bump indexed_hash — is the only path that gives
 * "single-paragraph edit re-embeds one chunk, not twenty" while remaining
 * idempotent under worker crashes and concurrent edits.
 */
class ChunkContentService
{
    use ResolvesChunkModel;

    public function __construct(
        protected readonly AtlasConfig $config,
        protected readonly Dispatcher $events,
    ) {}

    public function reconcile(Chunkable&Model $model): void
    {
        $workingHash = $model->getAttribute('content_hash');

        try {
            if (! $model->shouldBeChunked()) {
                $this->purgeChunks($model);
                $this->markIndexed($model, $workingHash);

                return;
            }

            $newChunks = $model->resolveChunker()->chunk($model->getChunkableContent());

            $existing = $this->loadExisting($model);

            ['keep' => $keep, 'ordChanges' => $ordChanges, 'insert' => $insert, 'delete' => $delete]
                = $this->diff($newChunks, $existing);

            $vectors = $this->embedBatch($insert);

            DB::transaction(function () use ($model, $delete, $ordChanges, $insert, $vectors, $workingHash): void {
                // Re-read content_hash inside the transaction. If the owner was
                // edited between when we started chunking and now, $workingHash
                // is stale — bailing out leaves indexed_hash unchanged so the
                // next sweep picks the row up against the current content.
                $fresh = $model->newQuery()
                    ->whereKey($model->getKey())
                    ->value('content_hash');
                if ($fresh !== $workingHash) {
                    return;
                }

                $chunkModel = $this->chunkModel();

                if (! empty($delete)) {
                    $chunkModel::query()->whereIn('id', array_keys($delete))->delete();
                }

                foreach ($ordChanges as $id => $ord) {
                    $chunkModel::query()->whereKey($id)->update(['ord' => $ord]);
                }

                if (! empty($insert)) {
                    $this->insertChunks($model, $insert, $vectors);
                }

                $model->forceFill([
                    'indexed_hash' => $workingHash,
                    'indexed_at' => now(),
                    'last_index_error' => null,
                    'index_failure_count' => 0,
                ])->saveQuietly();
            });

            $this->events->dispatch(new ContentChunked(
                chunkableType: $model->getMorphClass(),
                chunkableId: (int) $model->getKey(),
                chunkCount: count($keep) + count($insert),
                embeddedCount: count($insert),
            ));
        } catch (Throwable $e) {
            $count = (int) ($model->getAttribute('index_failure_count') ?? 0) + 1;
            $model->forceFill([
                'last_index_error' => $e->getMessage(),
                'index_failure_count' => $count,
            ])->saveQuietly();

            $this->events->dispatch(new ContentChunkingFailed(
                chunkableType: $model->getMorphClass(),
                chunkableId: (int) $model->getKey(),
                error: $e->getMessage(),
            ));

            throw $e;
        }
    }

    /**
     * @return array<string, Chunk>
     */
    protected function loadExisting(Model $model): array
    {
        $chunkModel = $this->chunkModel();

        return $chunkModel::query()
            ->where('chunkable_type', $model->getMorphClass())
            ->where('chunkable_id', $model->getKey())
            ->get()
            ->keyBy('content_hash')
            ->all();
    }

    /**
     * @param  array<int, ChunkData>  $newChunks
     * @param  array<string, Chunk>  $existing
     * @return array{keep: array<string, Chunk>, ordChanges: array<int, int>, insert: array<int, ChunkData>, delete: array<int, Chunk>}
     */
    protected function diff(array $newChunks, array $existing): array
    {
        $keep = [];
        $ordChanges = [];
        $insert = [];
        $matchedHashes = [];

        foreach ($newChunks as $chunk) {
            $hash = $chunk->hash();
            if (isset($existing[$hash])) {
                $row = $existing[$hash];
                $keep[$hash] = $row;
                $matchedHashes[$hash] = true;
                if ((int) $row->getAttribute('ord') !== $chunk->ord) {
                    $ordChanges[(int) $row->getKey()] = $chunk->ord;
                }
            } else {
                $insert[] = $chunk;
            }
        }

        $delete = [];
        foreach ($existing as $hash => $row) {
            if (! isset($matchedHashes[$hash])) {
                $delete[(int) $row->getKey()] = $row;
            }
        }

        return [
            'keep' => $keep,
            'ordChanges' => $ordChanges,
            'insert' => $insert,
            'delete' => $delete,
        ];
    }

    /**
     * @param  array<int, ChunkData>  $insert
     * @return array<int, array<int, float>>
     */
    protected function embedBatch(array $insert): array
    {
        if (empty($insert)) {
            return [];
        }

        $texts = array_map(fn (ChunkData $c): string => $c->embedText(), $insert);

        $response = Atlas::embed()->fromInput($texts)->asEmbeddings();
        $vectors = $response->embeddings;

        if (count($vectors) !== count($insert)) {
            throw new AtlasException(
                'Embedding provider returned '.count($vectors).' vectors for '.count($insert).' chunks.'
            );
        }

        // Fail with an actionable message before the cryptic pgvector
        // "expected N dimensions, not M" error at insert time. The chunks
        // column is sized to atlas.embeddings.dimensions, so any other length
        // is guaranteed to be rejected by the database.
        $expected = (int) $this->config->embeddingDimensions;
        foreach ($vectors as $vector) {
            if (count($vector) !== $expected) {
                throw AtlasException::dimensionMismatch($expected, count($vector));
            }
        }

        return $vectors;
    }

    /**
     * @param  array<int, ChunkData>  $insert
     * @param  array<int, array<int, float>>  $vectors
     */
    protected function insertChunks(Model $model, array $insert, array $vectors): void
    {
        $chunkModel = $this->chunkModel();
        $embeddingModelName = $this->config->defaultFor('embed')['model'] ?? '';
        $morphClass = $model->getMorphClass();
        $chunkableId = $model->getKey();
        $now = now();
        // Detect Postgres from the chunk model's OWN connection, not the default
        // one. When persistence is routed to a separate connection
        // (atlas.persistence.connection) whose driver differs from the app
        // default, reading the default connection here drops the vector on a
        // pgvector table (NOT NULL violation) or writes one to a table without
        // the column. The chunks migration detects the driver the same way.
        $isPostgres = (new $chunkModel)->getConnection()->getDriverName() === 'pgsql';

        $rows = [];
        foreach ($insert as $i => $chunk) {
            $row = [
                'chunkable_type' => $morphClass,
                'chunkable_id' => $chunkableId,
                'ord' => $chunk->ord,
                'heading_path' => $chunk->headingPath,
                'content' => $chunk->content,
                'content_hash' => $chunk->hash(),
                'token_count' => $chunk->tokenCount,
                'embedding_model' => $embeddingModelName,
                'embedded_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // The vector column only exists on PostgreSQL (see the chunks migration).
            // On other drivers we still store the chunk so the diff/reconcile path
            // is exercisable in tests, but similarity search will not work.
            if ($isPostgres) {
                $row['embedding'] = VectorQueryMacros::toVectorLiteral($vectors[$i]);
            }

            $rows[] = $row;
        }

        foreach (array_chunk($rows, 100) as $batch) {
            $chunkModel::query()->insert($batch);
        }
    }

    protected function purgeChunks(Model $model): void
    {
        $chunkModel = $this->chunkModel();

        $chunkModel::query()
            ->where('chunkable_type', $model->getMorphClass())
            ->where('chunkable_id', $model->getKey())
            ->delete();
    }

    protected function markIndexed(Model $model, ?string $hash): void
    {
        $model->forceFill([
            'indexed_hash' => $hash,
            'indexed_at' => now(),
            'last_index_error' => null,
            'index_failure_count' => 0,
        ])->saveQuietly();
    }
}
