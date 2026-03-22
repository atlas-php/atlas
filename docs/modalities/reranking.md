# Reranking

Re-order documents by semantic relevance to a query. Useful for improving RAG retrieval quality.

## Quick Example

```php
use Atlasphp\Atlas\Facades\Atlas;

$response = Atlas::rerank('cohere', 'rerank-v3.5')
    ->query('What is dependency injection?')
    ->documents([
        'Laravel uses a service container for dependency injection.',
        'PHP 8.2 introduced readonly classes.',
        'Dependency injection is a design pattern that removes hard-coded dependencies.',
        'Atlas is an AI execution layer for Laravel.',
    ])
    ->asReranked();

$top = $response->top();
echo $top->document;  // "Dependency injection is a design pattern..."
echo $top->score;     // 0.98
```

## Basic Usage

```php
$response = Atlas::rerank('cohere', 'rerank-v3.5')
    ->query('How do I configure providers?')
    ->documents($searchResults)
    ->asReranked();

// Get results sorted by relevance
foreach ($response->results as $result) {
    echo "{$result->index}: {$result->document} (score: {$result->score})\n";
}
```

## Filtering Results

### Top N

```php
$response = Atlas::rerank('cohere', 'rerank-v3.5')
    ->query('Laravel routing')
    ->documents($docs)
    ->topN(5)
    ->asReranked();

// Only the top 5 results are returned
```

### Minimum Score

```php
$response = Atlas::rerank('cohere', 'rerank-v3.5')
    ->query('Laravel routing')
    ->documents($docs)
    ->minScore(0.5)
    ->asReranked();

// Only results scoring above 0.5 are returned
```

### Max Tokens Per Document

```php
$response = Atlas::rerank('cohere', 'rerank-v3.5')
    ->query('authentication')
    ->documents($longDocuments)
    ->maxTokensPerDoc(512)
    ->asReranked();
```

## Re-ordering Original Items

Use `reorder()` to sort your original array by relevance:

```php
$articles = Article::all()->toArray();
$texts = array_column($articles, 'content');

$response = Atlas::rerank('cohere', 'rerank-v3.5')
    ->query($userQuery)
    ->documents($texts)
    ->asReranked();

// Re-order articles by relevance
$sorted = $response->reorder($articles);
```

## Response Methods

```php
$response->results;          // All RerankResult objects
$response->top();            // Highest scoring result
$response->topN(3);          // Top 3 results
$response->aboveScore(0.7);  // Results above threshold
$response->indexes();        // Original indexes in relevance order
$response->reorder($items);  // Re-sort an array by relevance
```

## Supported Providers

| Provider | Models |
|----------|--------|
| Cohere | rerank-v3.5, rerank-english-v3.0, rerank-multilingual-v3.0 |
| Jina | jina-reranker-v2-base-multilingual |

## RerankResponse

| Property/Method | Type | Description |
|----------------|------|-------------|
| `results` | `array<RerankResult>` | Ranked results |
| `meta` | `array` | Additional metadata |
| `top()` | `?RerankResult` | Highest scoring result |
| `topN(int)` | `array` | Top N results |
| `aboveScore(float)` | `array` | Results above threshold |
| `indexes()` | `array` | Original indexes sorted by relevance |
| `reorder(array)` | `array` | Re-sort items by relevance |

## RerankResult

| Property | Type | Description |
|----------|------|-------------|
| `index` | `int` | Original document index |
| `document` | `string` | The document text |
| `score` | `float` | Relevance score (0-1) |

## Queue Support

```php
Atlas::rerank('cohere', 'rerank-v3.5')
    ->query($searchQuery)
    ->documents($candidates)
    ->queue()
    ->asReranked()
    ->then(function ($response) {
        cache()->put("ranked:{$searchQuery}", $response->results, 3600);
    });
```

## Builder Reference

| Method | Description |
|--------|-------------|
| `query(string)` | The search query |
| `documents(array)` | Documents to rank |
| `topN(int)` | Limit to top N results |
| `maxTokensPerDoc(int)` | Max tokens per document |
| `minScore(float)` | Minimum relevance score threshold |
| `withProviderOptions(array)` | Provider-specific options |
| `withMeta(array)` | Metadata for middleware/events |
| `withMiddleware(array)` | Per-request provider middleware |
| `queue()` | Dispatch to queue |
