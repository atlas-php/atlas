# Embeddings

Generate vector embeddings for semantic search, RAG pipelines, and similarity comparisons.

Atlas provides three layers, from low-level to high-level:

1. **Raw API** — `Atlas::embed()->fromInput($text)->asEmbeddings()` for direct embedding calls.
2. **Whole-record embeddings** — `HasVectorEmbeddings` trait stores one vector per row, auto-generated on save.
3. **Chunked embeddings** — `HasChunkedEmbeddings` trait chunks long-form content into N vectors per record, reconciled incrementally on edits.

For retrieval, see the [Similarity Search](/features/similarity-search) feature page — a single facade method drives both modes.

## Quick Example

```php
use Atlasphp\Atlas\Atlas;

$response = Atlas::embed('openai', 'text-embedding-3-small')
    ->fromInput('What is Laravel?')
    ->asEmbeddings();

$vector = $response->embeddings[0];  // Array of floats
echo count($vector);                  // 1536 dimensions
```

## Single Input

```php
$response = Atlas::embed('openai', 'text-embedding-3-small')
    ->fromInput('The quick brown fox')
    ->asEmbeddings();

$response->embeddings;  // [[0.012, -0.034, ...]]
$response->usage;       // Token usage
```

## Batch Input

```php
$response = Atlas::embed('openai', 'text-embedding-3-small')
    ->fromInput([
        'First document about PHP',
        'Second document about Laravel',
        'Third document about Atlas',
    ])
    ->asEmbeddings();

count($response->embeddings);  // 3
```

## Using Defaults

Configure a default embedding provider/model to avoid repeating it:

```env
ATLAS_EMBED_PROVIDER=openai
ATLAS_EMBED_MODEL=text-embedding-3-small
```

```php
// Uses configured defaults
$response = Atlas::embed()
    ->fromInput('Hello world')
    ->asEmbeddings();
```

## Supported Providers

| Provider | Models | Dimensions |
|----------|--------|-----------|
| OpenAI | text-embedding-3-small, text-embedding-3-large, text-embedding-ada-002 | 1536, 3072, 1536 |
| Google | text-embedding-004 | 768 |

## EmbeddingsResponse

| Property | Type | Description |
|----------|------|-------------|
| `embeddings` | `array` | Array of embedding vectors (array of floats) |
| `usage` | `Usage` | Token counts |

## Queue Support

Dispatch embedding generation to a queue for large batches:

```php
Atlas::embed('openai', 'text-embedding-3-small')
    ->fromInput($largeDocumentBatch)
    ->queue()
    ->asEmbeddings()
    ->then(function ($response) {
        foreach ($response->embeddings as $i => $vector) {
            Document::find($ids[$i])->update(['embedding' => $vector]);
        }
    });
```

## Builder Reference

| Method | Description |
|--------|-------------|
| `fromInput(string\|array)` | Text to embed (single string or array for batch) |
| `withProviderOptions(array)` | Provider-specific options |
| `withMeta(array)` | Metadata for middleware/events |
| `withMiddleware(array)` | Per-request provider middleware |
| `queue()` | Dispatch to queue |

---

## Storing Embeddings on Models

Most apps don't want to call `Atlas::embed()` by hand — they want embeddings as a property of their domain models, kept in sync with the source content. Atlas ships two traits for this, both backed by PostgreSQL + `pgvector`.

| Trait | Use when | Storage | Retrieval |
|---|---|---|---|
| `HasVectorEmbeddings` | Short, atomic items: notes, chat messages, prompts, named entities. | One vector per record on the model's own table. | Cosine similarity over the model's embedding column. |
| `HasChunkedEmbeddings` | Long-form, frequently-edited content: project bodies, articles, lore documents, transcripts. | N vectors in the polymorphic `atlas_chunks` table. | Cosine similarity over the chunks; results carry the hydrated parent model. |

Both traits embed through the same configured embedding provider, run through the same middleware pipeline, and produce results consumable through the same [`Atlas::similaritySearch()`](/features/similarity-search) facade. Pick whichever matches your content shape.

A single model can use **both** — typical pattern: `HasVectorEmbeddings` on the title+summary for whole-record discovery, `HasChunkedEmbeddings` on the body for paragraph-level retrieval.

---

## Whole-Record Embeddings (`HasVectorEmbeddings`)

One vector per record, computed from one or more source fields. The trait recomputes the embedding automatically when any source field changes on save.

### Setup

1. Add a `vector(N)` column (and optional `embedding_at` timestamp) to the model's table:

```php
Schema::create('notes', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('body')->nullable();
    $table->timestamps();

    if (Schema::getConnection()->getDriverName() === 'pgsql') {
        $dimensions = config('atlas.embeddings.dimensions', 1536);
        $table->vector('embedding', $dimensions)->nullable();
        $table->timestamp('embedding_at')->nullable();
    }
});

DB::statement('CREATE INDEX notes_embedding_idx ON notes USING hnsw (embedding vector_cosine_ops)');
```

2. Add the trait and the `VectorEmbeddable` interface to the model:

```php
use Atlasphp\Atlas\Embeddings\VectorEmbeddable;
use Atlasphp\Atlas\Persistence\Concerns\HasVectorEmbeddings;
use Illuminate\Database\Eloquent\Model;

class Note extends Model implements VectorEmbeddable
{
    use HasVectorEmbeddings;

    public function embeddable(): array
    {
        return ['column' => 'embedding', 'source' => ['title', 'body']];
    }
}
```

`embeddable()['source']` can be a single field name or an array of field names. Multi-source inputs are concatenated with `\n\n` before embedding.

`implements VectorEmbeddable` is required to use the unified `Atlas::similaritySearch()` facade — the trait itself keeps working without the interface for direct macro usage like `$note->similarTo($vector)`.

### Behavior

- On save, if any source field is dirty, the trait calls the embedding provider once and stores the new vector in `embeddable()['column']`.
- Editing the title regenerates the embedding; editing an unrelated field does not.
- Disable auto-embedding on a specific model by setting `protected bool $autoEmbed = false;`.

---

## Chunked Embeddings (`HasChunkedEmbeddings`)

For long-form content that gets edited continuously, a single whole-record embedding is the wrong shape. The chunked subsystem splits content into pieces, embeds each piece, and reconciles edits so only what changed gets re-embedded.

### How it works

1. The model uses the `HasChunkedEmbeddings` trait and declares which column holds the content.
2. On save, the trait recomputes a `content_hash` of the column. **No embedding work happens on the save path.**
3. A scheduled `atlas:chunk` artisan command sweeps dirty rows (`content_hash != indexed_hash`) past a settle period and dispatches a reconciler job per row.
4. The job runs the configured chunker, diffs the result against existing chunks by content hash, and embeds only the chunks that are new or changed.

A single-paragraph edit on a 20-chunk record re-embeds 1–2 chunks, not 20.

### Setup

#### 1. Add columns to your model's table

```php
use Atlasphp\Atlas\Persistence\Schema\ChunkedEmbeddingColumns;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            ChunkedEmbeddingColumns::add($table);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            ChunkedEmbeddingColumns::drop($table);
        });
    }
};
```

This adds `content_hash`, `indexed_hash`, `indexed_at`, `last_index_error`, and `index_failure_count`, plus a composite index on `(content_hash, indexed_hash)`.

You also need atlas's own `atlas_chunks` table. Publish and run the package migrations:

```bash
php artisan vendor:publish --tag=atlas-migrations
php artisan migrate
```

#### 2. Add the trait to your model

```php
use Atlasphp\Atlas\Embeddings\Chunkable;
use Atlasphp\Atlas\Persistence\Concerns\HasChunkedEmbeddings;
use Illuminate\Database\Eloquent\Model;

class Project extends Model implements Chunkable
{
    use HasChunkedEmbeddings;

    protected string $chunkableField = 'body'; // default; override to point at a different column
}
```

`implements Chunkable` is required — the chunking services type-hint against this interface. The trait provides default implementations of every interface method.

#### 3. Register the model

In `AppServiceProvider::boot()`:

```php
use Atlasphp\Atlas\Atlas;

public function boot(): void
{
    Atlas::registerChunkable(\App\Models\Project::class);
}
```

This registration is what the `atlas:chunk` sweep iterates. The trait also self-registers on first instantiation, but a fresh artisan process touches no models before the sweep runs, so the explicit registration is required.

#### 4. Schedule the sweep

In `routes/console.php` or `App\Console\Kernel`:

```php
Schedule::command('atlas:chunk')->everyMinute()->withoutOverlapping();
```

Saving a model with a non-empty `body` marks it dirty; the next sweep picks it up after the settle period.

### Direct synchronous use (no queue, no command)

To chunk and embed a record immediately — for example inside a controller after a save, or in a test — call `chunkNow()` on the model:

```php
$project->update(['body' => $request->input('body')]);
$project->chunkNow();   // chunk + embed + write, synchronously
```

`chunkNow()` runs the same reconciler the `atlas:chunk` sweep would run, just inline. It works without `atlas.persistence.enabled`, without the scheduled command, and without a queue worker. Use it when:

- You're rendering search results immediately after an edit and can afford the latency of one embedding API call.
- You're writing a test that needs deterministic post-edit chunk state.
- You don't want to run a queue worker for this feature.

The trade-off is that `chunkNow()` makes the user wait for the embedding round-trip on save. The scheduled `atlas:chunk` path is what makes saves feel instant.

Under the hood it delegates to the service, which is also callable directly for models that don't use the trait:

```php
app(\Atlasphp\Atlas\Persistence\Services\ChunkContentService::class)->reconcile($model);
```

### Configuration

All knobs live under `config('atlas.embeddings')`:

```php
'embeddings' => [
    'dimensions' => 1536,           // vector size — must match your embedding model
    'chunker' => MarkdownChunker::class,
    'chunk_size' => 512,            // soft cap per chunk, in tokens (chars/4 heuristic)
    'chunk_overlap' => 50,          // tokens of overlap between adjacent chunks
    'sweep_batch' => 50,            // rows the sweep dispatches per model per run
    'sweep_settle' => 60,           // seconds since updated_at before a dirty row is eligible
    'max_failures' => 5,            // attempts before a row is excluded from sweeps
],
```

Internal hard limits (`HARD_MAX_TOKENS`, `MAX_CHUNKS_PER_RECORD`) are class constants on `MarkdownChunker`. If you need different values, ship a custom chunker.

### Custom chunkers

The default `MarkdownChunker` splits at H1/H2/H3/H4 boundaries with structural awareness for code fences and tables. For non-markdown content (plain text, source code, transcripts, structured data), implement the `Chunker` interface:

```php
use Atlasphp\Atlas\Embeddings\ChunkData;
use Atlasphp\Atlas\Embeddings\Chunkers\BaseTokenAwareChunker;

class TranscriptChunker extends BaseTokenAwareChunker
{
    public function chunk(string $content): array
    {
        $turns = $this->splitByTurns($content);
        $packed = $this->packUnits($turns);

        $result = [];
        foreach ($packed as $i => $piece) {
            $result[] = new ChunkData(
                ord: $i,
                headingPath: null,
                content: $piece,
                tokenCount: \Atlasphp\Atlas\Support\TokenCounter::count($piece),
            );
        }
        return $result;
    }

    private function splitByTurns(string $content): array { /* … */ }
}
```

Extend `BaseTokenAwareChunker` to inherit the `packUnits`, `splitOversizedUnit`, `splitSentences`, and `takeOverlapTail` helpers. Or implement `Chunker` directly for full control over packing.

**Chunkers must be deterministic.** The same input must produce the same chunks with the same content hashes every time, or the diff algorithm will churn on every sweep.

Wire the chunker either globally:

```php
'embeddings' => [
    'chunker' => \App\Chunkers\TranscriptChunker::class,
],
```

Or per-model:

```php
class Transcript extends Model implements Chunkable
{
    use HasChunkedEmbeddings;

    protected ?string $chunker = TranscriptChunker::class;
}
```

### Orphan cleanup

Polymorphic relations can't carry FK cascades, so deleting an owner row via Eloquent's mass-delete (`Project::where(...)->delete()`) bypasses the trait's `deleting` hook and would leave chunks behind. The `atlas:chunk` sweep cleans these up automatically — every run prunes any chunk whose `chunkable_id` is no longer present in its owner table. Soft-deleted rows are not treated as orphans (the row still exists in the table); chunks survive soft delete.

If you delete owner rows one-by-one via `$model->delete()` or `Model::destroy(...)`, the trait fires its `deleting` hook synchronously and chunks are removed immediately — the sweep has nothing to prune.

### Backfill / re-chunk

After deploying a new chunker, changing `chunk_size`, or any other change that should rebuild every record from scratch:

```bash
php artisan atlas:rechunk "App\Models\Project"
```

This clears `indexed_hash` on all rows of the class. The next sweep picks them up.

To re-chunk a single record (e.g. after debugging bad retrieval for it), pass an ID:

```bash
php artisan atlas:rechunk "App\Models\Project" 42
```

Add `--reset-failures` to either form to also clear `index_failure_count` and `last_index_error` so previously-skipped rows are picked up again.

### Events

Both events carry the morph class string and the owner's ID:

| Event | When |
|---|---|
| `Atlasphp\Atlas\Events\ContentChunked` | Reconciliation succeeded — includes `chunkCount` and `embeddedCount` |
| `Atlasphp\Atlas\Events\ContentChunkingFailed` | Reconciliation failed — includes `error` message |

### Testing

To exercise the reconciler in your tests without setting up Horizon, call the service directly:

```php
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Persistence\Services\ChunkContentService;
use Atlasphp\Atlas\Testing\EmbeddingsResponseFake;

it('chunks project bodies on demand', function () {
    Atlas::fake([
        EmbeddingsResponseFake::make()->withEmbeddings([[0.1, 0.2, 0.3]]),
    ]);

    $project = Project::factory()->create(['body' => "# Hello\n\nworld"]);

    app(ChunkContentService::class)->reconcile($project);

    expect($project->chunks)->toHaveCount(1);
});
```

---

For querying these embeddings — both modes through a single facade and as an agent tool — see [Similarity Search](/features/similarity-search).
