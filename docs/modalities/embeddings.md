# Embeddings

Generate vector embeddings for semantic search, RAG pipelines, and similarity comparisons.

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
