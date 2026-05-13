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
    model: Project::class,
    query: 'when does the contract end',
);
```

Default limit is 5; no minimum similarity floor.

### Options

```php
$results = Atlas::similaritySearch(Project::class, $query, [
    'limit' => 10,                       // top-K, default 5
    'min_similarity' => 0.6,             // optional cosine-similarity floor (0.0–1.0)
    'ids' => [1, 2, 5, 8, 13],           // optional scope to specific owner IDs
    'where' => fn ($q) => $q             // optional scope on the owner table
        ->where('user_id', auth()->id())
        ->where('archived', false),
]);
```

The `where` callback receives an Eloquent Builder for the **owner model** — even in chunk-mode, where the underlying query is on `atlas_chunks`. The service applies your scope as a subquery against the owner table, so a query like `where('user_id', auth()->id())` works the same whether the model uses chunked or whole-record embeddings.

### Scoping to specific records (`ids`)

Pass `ids` to restrict the search to a known set of owner records — a single ID or an array. Combines with `where` (both filters apply). An empty array short-circuits to zero results without touching the embedding API at all.

```php
// Single record
$results = Atlas::similaritySearch(Project::class, $query, ['ids' => 42]);

// "These 5 projects, not the other 3" — the user's effective scope
$results = Atlas::similaritySearch(Project::class, $query, [
    'ids' => [1, 2, 5, 8, 13],
]);

// Combine with a permission scope — both filters apply (intersection)
$results = Atlas::similaritySearch(Project::class, $query, [
    'ids' => $userVisibleProjectIds,
    'where' => fn ($q) => $q->where('archived', false),
]);

// Empty list short-circuits without an embedding API call
// — useful when "the user has no records in scope" is a normal state
$results = Atlas::similaritySearch(Project::class, $query, ['ids' => []]);
// $results is an empty Collection; zero latency, zero API cost.
```

In chunked mode, `ids` translates to `WHERE chunkable_id IN (...)` directly on `atlas_chunks` — faster than the owner-table subquery the `where` callback uses. Reach for `ids` whenever you already have the IDs in hand; reach for `where` when you need a non-key predicate.

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
    ids: [1, 2, 5, 8, 13],        // optional fixed-scope owner IDs
);
```

The `ids` parameter is the agent-facing equivalent of the facade's `ids` option. It's wired at tool-construction time, so it's the right shape for "this agent searches a specific tenant / team / topic." When the consumer doesn't know the IDs ahead of time and needs them per request, build a fresh tool inside the agent's `tools()` method:

```php
class ProjectSearchAgent extends Agent
{
    public function tools(): array
    {
        $teamProjectIds = auth()->user()->team->projects()->pluck('id')->all();

        return [
            SimilaritySearch::usingModel(Project::class, ids: $teamProjectIds, limit: 5)
                ->withName('search_projects')
                ->withDescription('Search this team\'s project briefs.'),
        ];
    }
}
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

## Direct macro usage

For consumers who need custom SQL — hybrid keyword + vector ranking, joins atlas's services don't model, distance thresholds with bespoke ordering — pgvector query-builder methods are available on `Illuminate\Database\Eloquent\Builder` and `Illuminate\Database\Query\Builder`. They wrap pgvector's cosine distance operator (`<=>`) and accept either a pre-computed `array<float>` or a `string` (auto-resolved via `EmbeddingResolver` when invoked through atlas's macros).

::: info Laravel 11+ ships these methods natively
On Laravel 11 and later, `Query\Builder` defines `selectVectorDistance`, `whereVectorSimilarTo`, `whereVectorDistanceLessThan`, `orWhereVectorDistanceLessThan`, and `orderByVectorDistance` as native methods. PHP's `__call` resolves real methods before macros, so on modern Laravel the native implementations execute — atlas's `Builder::macro(...)` registrations of the same names are present for back-compat with older Laravel versions but are shadowed at runtime. Atlas owns one variant Laravel does not ship: `orWhereVectorSimilarTo` is reachable as an atlas macro on every supported Laravel.

The signatures below are Laravel's native ones on Laravel 11+, since that's what actually runs in production. The `String` input shorthand (passing a query string instead of a vector) only works through atlas's macros — Laravel's native methods accept an array vector only. If you need string-input auto-resolution on Laravel 11+, resolve the vector first via `app(EmbeddingResolver::class)->resolve($query)`.
:::

| Method | Signature | SQL it emits |
|---|---|---|
| `whereVectorSimilarTo` | `(string $column, array $vector, float $minSimilarity = 0.6, bool $order = true)` | `WHERE {column} <=> ?::vector <= (1 - minSimilarity)`, plus `ORDER BY {column} <=> ?::vector ASC` when `$order` is true. Combined predicate + ordering — the common case. |
| `whereVectorDistanceLessThan` | `(string $column, array $vector, float $maxDistance, string $boolean = 'and')` | `WHERE {column} <=> ?::vector <= maxDistance`. Predicate only — pair with your own ordering. The `$boolean` parameter accepts `'or'` to compose into an OR group inline. |
| `orWhereVectorDistanceLessThan` | `(string $column, array $vector, float $maxDistance)` | Same as above with `$boolean = 'or'`. |
| `orWhereVectorSimilarTo` _(atlas)_ | `(string $column, string\|array $embedding, float $minSimilarity = 0.5)` | `OR {column} <=> ?::vector <= (1 - minSimilarity)`. No ORDER BY appended — caller decides ordering in an OR group. Reachable via atlas's macro because Laravel doesn't ship this variant. |
| `selectVectorDistance` | `(string $column, array $vector, ?string $as = null)` | `SELECT ..., ({column} <=> ?::vector) AS {as}`. Exposes the raw distance for hybrid ranking. Default alias is `vector_distance`. |
| `orderByVectorDistance` | `(string $column, array $vector)` | `ORDER BY {column} <=> ?::vector ASC`. Distance-ascending ordering without a floor. Laravel's native version has no direction parameter — pass through SQL ordering separately if you need DESC. |

All Laravel-native methods throw `RuntimeException("Vector distance queries are only supported by Postgres.")` if invoked on a non-PostgreSQL connection.

### Example: hybrid keyword + vector ranking

```php
$results = Project::query()
    ->select('projects.*')
    ->selectVectorDistance('embedding', $query, 'distance')
    ->where('archived', false)
    ->where(function ($q) use ($keyword) {
        $q->where('title', 'ilike', "%{$keyword}%")
          ->orWhereVectorDistanceLessThan('embedding', $keyword, 0.4);
    })
    ->orderBy('distance')
    ->limit(20)
    ->get();
```

`distance` is on every row, so you can rank with your own weighting (`similarity = 1 - distance`).

### Example: distance threshold with custom ordering

```php
$candidates = Project::query()
    ->whereVectorDistanceLessThan('embedding', $query, 0.35)
    ->orderByDesc('priority')   // domain ordering, not similarity
    ->limit(10)
    ->get();
```

### Registration

The macros register automatically when atlas boots, but **only on PostgreSQL connections**. `VectorQueryMacros::isPgvectorAvailable()` short-circuits on other drivers, so calling these macros on SQLite/MySQL fails loudly with "method does not exist" rather than producing invalid SQL.

For the few cases where you need them registered before atlas boots (early service-provider work, console commands that bypass the kernel), call `VectorQueryMacros::register()` directly.

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
