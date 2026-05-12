# Similarity Search

`Atlas::similaritySearch()` is a single facade method for semantic search over your Eloquent models. It auto-dispatches between the two embedding modes atlas supports, so consumer code looks the same whether you store one vector per record or many chunks per record.

```php
use Atlasphp\Atlas\Atlas;

$results = Atlas::similaritySearch(Project::class, 'when does the contract end', [
    'limit' => 5,
]);

foreach ($results as $result) {
    echo $result->similarity;            // 0.0 – 1.0
    echo $result->record->title;         // hydrated Eloquent model
    echo $result->content;               // the embedded text
}
```

The facade is also available as an agent tool — see [As an agent tool](#as-an-agent-tool) below.

## The concept

Atlas stores embeddings in two shapes (see [Embeddings](/modalities/embeddings)):

- **Whole-record** — one vector per row, stored on the model's own table via the `HasVectorEmbeddings` trait. Good for short, atomic items (notes, prompts, chat messages, named entities).
- **Chunked** — many vectors per row, stored in the polymorphic `atlas_chunks` table via the `HasChunkedEmbeddings` trait. Good for long-form, frequently-edited content (project bodies, articles, transcripts).

These two shapes need different queries — chunked search joins on `chunkable_type`/`chunkable_id` and returns chunk-level snippets; whole-record search hits the model's `embedding` column and returns whole records. Without a unifying layer, consumer code has to know which mode each model uses and call different APIs.

`Atlas::similaritySearch()` removes that branching. It looks at which interface your model implements and routes the call to the right service:

| Model implements | Searches | Result content |
|---|---|---|
| `Chunkable` (with `HasChunkedEmbeddings`) | `atlas_chunks` filtered by morph class | The matched chunk's text, with `headingPath` and `ord` populated |
| `VectorEmbeddable` (with `HasVectorEmbeddings`) | The model's own embedding column | The model's `getEmbeddableContent()` (what was embedded) |
| Both | `Chunkable` wins (more granular results) | Chunk-level |
| Neither | Throws `AtlasException` with a helpful message | — |

The return type is the same in both modes: `Collection<SearchResult>`. Consumers and agent tools don't need to know which mode produced the results.

## Usage

### Basic call

```php
$results = Atlas::similaritySearch(
    chunkable: Project::class,
    query: 'when does the contract end',
);
```

Default limit is 5; no minimum similarity floor.

### Options

```php
$results = Atlas::similaritySearch(Project::class, $query, [
    'limit' => 10,                       // top-K, default 5
    'min_similarity' => 0.6,             // optional cosine-similarity floor (0.0–1.0)
    'where' => fn ($q) => $q             // optional scope on the owner table
        ->where('user_id', auth()->id())
        ->where('archived', false),
]);
```

The `where` callback receives an Eloquent Builder for the **owner model** — even in chunk-mode, where the underlying query is on `atlas_chunks`. The service applies your scope as a subquery against the owner table, so a query like `where('user_id', auth()->id())` works the same whether the model uses chunked or whole-record embeddings.

### SearchResult

The return type is a `Collection<SearchResult>`. Each result is a readonly value object:

| Property | Type | Notes |
|---|---|---|
| `record` | `Model` | The matched (or parent) Eloquent model, fully hydrated. |
| `content` | `string` | What was embedded — chunk text in chunk-mode, `getEmbeddableContent()` in record-mode. |
| `similarity` | `float` | Cosine similarity (0–1). Computed as `1 - distance`. Higher is better. |
| `headingPath` | `?string` | Chunk-mode only. Joined heading hierarchy like `"Project > Risks > Data quality"`. `null` in record-mode. |
| `ord` | `?int` | Chunk-mode only. Position of the chunk within the parent record. `null` in record-mode. |

The two nullable fields let downstream code branch on shape when it needs to, but the common fields (`record`, `content`, `similarity`) cover most rendering cases without any branching.

## As an agent tool

`SimilaritySearch::usingModel()` produces a tool an agent can call. Same auto-dispatch — the agent doesn't need to know whether the backing model uses chunked or whole-record embeddings.

```php
use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Tools\SimilaritySearch;

class SupportAgent extends Agent
{
    public function tools(): array
    {
        return [
            SimilaritySearch::usingModel(Project::class, limit: 5)
                ->withName('search_projects')
                ->withDescription('Search project briefs by semantic similarity.'),

            SimilaritySearch::usingModel(Note::class, limit: 3)
                ->withName('search_notes')
                ->withDescription('Search short notes — titles and bodies.'),
        ];
    }
}
```

The agent invokes the tool with `{ "query": "…" }` and receives a `Collection<SearchResult>`. Whether the underlying model uses chunked or whole-record embeddings is transparent to the agent.

### Tool factory options

```php
SimilaritySearch::usingModel(
    model: Project::class,
    minSimilarity: 0.5,           // floor; pass null for no floor
    limit: 10,                    // top-K
    query: fn ($q) => $q          // optional Builder scope
        ->where('archived', false),
);
```

For models without either trait, you can still construct a tool with a custom search closure:

```php
SimilaritySearch::usingModel(
    LegacyDocument::class,
    column: 'vector_blob',        // explicit column name
    minSimilarity: 0.5,
    embedProvider: 'cohere',      // explicit provider override
    embedModel: 'embed-v3',
);
```

That legacy path runs the column-based query directly without going through the unified dispatcher. Use it only when you need a non-default embedding provider or your model doesn't fit either standard trait.

## Direct service access

If you need to bypass the facade dispatch — for example you've written a custom chunker and want to operate on its results without a model — the services are bindable:

```php
use Atlasphp\Atlas\Persistence\Services\ChunkSearchService;
use Atlasphp\Atlas\Persistence\Services\RecordSearchService;

$chunkResults  = app(ChunkSearchService::class)->search(Project::class, $query, $options);
$recordResults = app(RecordSearchService::class)->search(Note::class,    $query, $options);
```

Same return shape as the facade — `Collection<SearchResult>`.

## How it routes through atlas

Embedding the query string goes through the standard atlas embed pipeline:

- `Atlas::similaritySearch()` → service → `EmbeddingResolver` → `Atlas::embed()->fromInput($query)->asEmbeddings()`
- Any registered `EmbedMiddleware` or `ProviderMiddleware` fires
- `ModalityStarted` / `ModalityCompleted` events fire
- `ProviderRequestStarted` / `ProviderRequestCompleted` events fire via `HttpClient`
- Retry policy, timeout, provider config — all the standard atlas plumbing applies

The actual vector query is then run via `VectorQueryMacros` (`whereVectorSimilarTo`, `orderByVectorDistance`, `selectVectorDistance`) — pgvector cosine distance over an HNSW index.

## Requirements

- **PostgreSQL with the `pgvector` extension.** Both modes require it for the vector column and similarity query.
- **Model implements** `Chunkable` (with `HasChunkedEmbeddings`) or `VectorEmbeddable` (with `HasVectorEmbeddings`). See [Embeddings](/modalities/embeddings) for setup of each trait.
- **An embedding provider configured** via `atlas.defaults.embed` (or pass one explicitly).

The vector query macros are registered automatically when atlas boots — no extra setup beyond installing pgvector.
