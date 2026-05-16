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
- Disable auto-embedding on a specific model by setting `protected bool $autoEmbed = false;` (see API reference below).

### API reference

The trait exposes the following methods. Most consumers only override `embeddable()`; the rest are stable extension points for custom workflows.

| Method | Returns | Purpose |
|---|---|---|
| `embeddable()` | `array{column: string, source: string\|array<string>}` | Declares which DB column stores the vector and which source field(s) get embedded. Multi-source values are concatenated with `\n\n`. Default: `['column' => 'embedding', 'source' => 'content']`. |
| `getEmbeddableContent()` | `string` | Returns the exact text that gets sent to the embedding provider. Reads the source field(s), trims empties, joins with `\n\n`. Override to inject synthetic context (titles, breadcrumbs, etc.). |
| `shouldGenerateEmbedding()` | `bool` | Dirty-check on source fields. Returns `true` when any source field is dirty **and** `getEmbeddableContent()` is non-empty. The auto-save hook gates on this. |
| `isAutoEmbedEnabled()` | `bool` | Reports whether the auto-save hook should fire — reads the `$autoEmbed` property on the model. Default `true` unless the model declares `protected bool $autoEmbed = false;`. |
| `generateEmbedding()` | `static` | Generate and assign the embedding **synchronously** using the configured default provider/model. Sets the embedding column and `embedding_at`, but does **not** save the model — the caller is responsible for `->save()`. Called automatically by the saving hook when auto-embed is enabled. |
| `generateEmbeddingUsing(?string $provider = null, ?string $model = null)` | `static` | Same as `generateEmbedding()` but with an explicit per-call provider/model override. Useful for backfills with a non-default embedding model, A/B-ing providers, or generating a one-off embedding outside the configured defaults. Both arguments are nullable and fall back to defaults individually. |
| `scopeSimilarTo($embedding, float $minSimilarity = 0.5)` | _Eloquent scope_ | Applies `whereVectorSimilarTo` to the query on the configured embedding column. Accepts either a pre-computed `array<float>` or a string (auto-resolved via `EmbeddingResolver`). |

#### Opt-out property

```php
class IngestedDoc extends Model implements VectorEmbeddable
{
    use HasVectorEmbeddings;

    // Skip the auto-embed hook. Useful when embeddings come from an
    // upstream batch job and the model is hydrated from that pipeline.
    protected bool $autoEmbed = false;
}
```

When `$autoEmbed = false`, the save hook does not fire, so consumers must call `$model->generateEmbedding()` (or `generateEmbeddingUsing(...)`) explicitly to populate the embedding column.

#### When to use `generateEmbeddingUsing()`

`generateEmbedding()` always goes through `atlas.defaults.embed`. Use `generateEmbeddingUsing()` when you need a different provider or model for a specific call without rebinding the global default:

```php
// Backfill historical notes with a higher-dimension model than the live default
Note::query()
    ->whereNull('embedding')
    ->chunkById(100, function ($batch): void {
        foreach ($batch as $note) {
            $note->generateEmbeddingUsing('openai', 'text-embedding-3-large')->save();
        }
    });
```

`null` falls back to the configured default for that argument, so you can override just the model (`generateEmbeddingUsing(model: 'text-embedding-3-large')`) or just the provider.

::: warning Vector dimensions must match the column
Switching the embedding model mid-flight produces vectors of a different dimension than the column was created with. Postgres `vector(N)` rejects vectors of the wrong size at write time. If you need to backfill with a higher-dimension model, run a migration to widen (or replace) the column first.
:::

#### Using `similarTo` directly

The Eloquent scope works without `Atlas::similaritySearch()` if you want raw query control:

```php
$matches = Note::similarTo($queryEmbedding, minSimilarity: 0.6)
    ->where('user_id', auth()->id())
    ->orderByVectorDistance('embedding', $queryEmbedding)
    ->limit(10)
    ->get();
```

`$queryEmbedding` can be a `string` (auto-resolved via the configured embedding provider) or a pre-computed `array<float>`.

For most retrieval, prefer `Atlas::similaritySearch(Note::class, $query, ...)` — same plumbing, returns `Collection<SearchResult>` with `similarity` already computed.

---

## Chunked Embeddings (`HasChunkedEmbeddings`)

For long-form content that gets edited continuously, a single whole-record embedding is the wrong shape. The chunked subsystem splits content into pieces, embeds each piece, and reconciles edits so only what changed gets re-embedded.

### How it works

1. The model uses the `HasChunkedEmbeddings` trait and declares which column holds the content.
2. On save, the trait recomputes a `content_hash` of the column and dispatches `ChunkContentJob` with a `sweep_settle`-second delay. **No embedding work happens on the save path itself.**
3. The job is `ShouldBeUnique` per row, so rapid edits collapse into a single queued job; it self-releases until `sweep_settle` seconds have passed since the last edit. Chunking runs once per edit burst, against the latest content.
4. The job runs the configured chunker, diffs the result against existing chunks by content hash, and embeds only the chunks that are new or changed.

A single-paragraph edit on a 20-chunk record re-embeds 1–2 chunks, not 20. A 60-save typing burst within a minute triggers one embed at the end, not 60.

Set `atlas.embeddings.dispatch_on_save = false` to disable the save-hook trigger and rely solely on the `atlas:chunk` sweep (legacy mode).

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

::: warning Integer primary keys only
Chunkable models must use integer primary keys. The polymorphic `atlas_chunks.chunkable_id` column is `unsignedBigInteger` — UUID/ULID-keyed models are not supported.
:::

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

##### Override points

`Chunkable` defines five methods; `HasChunkedEmbeddings` ships a working default for every one. Override on the model when you need to customize.

| Method | Default behavior | Override when |
|---|---|---|
| `getChunkableField(): string` | Reads `protected string $chunkableField` if defined, else `'body'`. | The indexable column has a non-default name and you'd rather declare it once than ship the property. |
| `shouldBeChunked(): bool` | Returns `true` when the indexable field is non-empty. | You want domain rules — only chunk published rows, only paid-tier users, only rows past a moderation gate. |
| `getChunkableContent(): string` | Returns the indexable field as-is. | You want to inject synthetic context before chunking — prepend the title as an H1, append a structured-data summary, include parent-document breadcrumbs. |
| `chunks(): MorphMany` | `$this->morphMany(Chunk::class, 'chunkable')->orderBy('ord')`. | Almost never — only if you're using a custom Chunk model and need a different relation configuration. |
| `resolveChunker(): Chunker` | Per-model `protected ?string $chunker` → `config('atlas.embeddings.chunker')` → `MarkdownChunker`. | You want chunker selection driven by row data (e.g. `body_format === 'markdown'` vs `'transcript'`) rather than a static class reference. |

The trait also exposes `chunkNow()` as a public method (see [Direct synchronous use](#direct-synchronous-use-no-queue-no-command)) — not part of the `Chunkable` interface, but always available on models that use the trait.

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

##### Registry API

| Method | Purpose |
|---|---|
| `Atlas::registerChunkable(class-string<Model> $modelClass)` | Add a model class to the sweep registry. Throws `InvalidArgumentException` if the class isn't an Eloquent model or doesn't implement `Chunkable`. Idempotent — calling twice with the same class is safe. |
| `Atlas::chunkables(): array<int, class-string<Model>>` | List every currently-registered class. Useful for diagnostics, dashboards, and writing your own conditional sweep logic. The `atlas:chunk --model=<class>` option validates against this list. |

```php
// In a health check or diagnostic command
foreach (Atlas::chunkables() as $class) {
    $dirty = $class::query()->whereColumn('content_hash', '!=', 'indexed_hash')->count();
    $this->line("{$class}: {$dirty} dirty rows");
}
```

#### 4. Schedule the safety-net commands

Dispatch-on-save handles the hot path. Schedule `atlas:chunk` as a backstop (raw SQL updates, queue outages) and `atlas:prune-chunks` for orphan cleanup:

```php
Schedule::command('atlas:chunk')->hourly()->withoutOverlapping();
Schedule::command('atlas:prune-chunks')->daily()->withoutOverlapping();
```

If you prefer everything in one command, keep the legacy `Schedule::command('atlas:chunk')->everyMinute()` — it still works and still does the orphan scan.

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
    'chunker' => MarkdownChunker::class,    // changing this does NOT re-chunk existing rows
    'chunk_size' => 512,            // soft cap per chunk, in tokens (chars/4 heuristic) — does NOT re-chunk on change
    'chunk_overlap' => 50,          // tokens of overlap between adjacent chunks — does NOT re-chunk on change
    'dispatch_on_save' => true,     // dispatch ChunkContentJob from the saved hook (false = sweep-only)
    'sweep_batch' => 50,            // rows the safety-net sweep dispatches per model per run
    'sweep_settle' => 60,           // seconds to wait after the last edit before chunking (also debounces dispatch-on-save)
    'max_failures' => 5,            // attempts before a row is excluded from sweeps
],
```

::: warning Re-chunk after changing chunker, chunk_size, or chunk_overlap
None of these settings dirty existing rows on their own. Old chunks remain in place until each record is edited. To rebuild every row of a class against the new settings, run `php artisan atlas:rechunk "App\Models\Project"` after deploying the change.
:::

Internal hard limits (`HARD_MAX_TOKENS`, `MAX_CHUNKS_PER_RECORD`) are class constants on `MarkdownChunker`. If you need different values, ship a custom chunker.

All chunked embeddings go through the configured `atlas.defaults.embed` provider — there is currently no per-model override. Apps mixing chunkable models that need different embedding providers will need to wrap the reconciler manually until the interface is extended.

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

Polymorphic relations can't carry FK cascades, so deleting an owner row via Eloquent's mass-delete (`Project::where(...)->delete()`) bypasses the trait's `deleting` hook and would leave chunks behind. Both `atlas:chunk` (when run without `--skip-orphans`) and the dedicated `atlas:prune-chunks` command clean these up — every run prunes any chunk whose `chunkable_id` is no longer present in its owner table. Split the orphan scan onto its own daily schedule (`atlas:prune-chunks`) if you want `atlas:chunk` to run frequently without paying for the full `atlas_chunks` scan each tick. Soft-deleted rows are not treated as orphans — the row still exists in the table.

If you delete owner rows one-by-one via `$model->delete()` or `Model::destroy(...)`, the trait fires its `deleting` hook synchronously and chunks are removed immediately. This applies to both hard deletes and soft deletes — `$model->delete()` on a `SoftDeletes` model fires `deleting`, so its chunks are wiped even though the owner row remains. After `restore()`, the owner's content is intact but its chunks are gone. The sweep won't automatically re-chunk the restored row because the delete didn't touch the hash columns — `content_hash` still equals `indexed_hash` from the last successful sweep, so the dirty-row predicate sees no work to do. To regenerate embeddings, call `$model->chunkNow()` inline, run `php artisan atlas:rechunk "App\Models\Project" {id}` to defer to the queue, or touch the indexed field on the model (which triggers the saving hook and re-hashes `content_hash`). This avoids stale embeddings accumulating forever for soft-deleted records that are never restored.

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
