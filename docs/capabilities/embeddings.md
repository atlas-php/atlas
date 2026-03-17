# Embeddings

Generate vector embeddings for semantic search, RAG, and similarity matching.

::: tip Prism Reference
Atlas embeddings wraps Prism's embeddings API. For detailed documentation including all configuration options, see [Prism Embeddings](https://prismphp.com/core-concepts/embeddings.html).
:::

## Basic Usage

```php
use Atlasphp\Atlas\Atlas;

$response = Atlas::embeddings()
    ->using('openai', 'text-embedding-3-small')
    ->fromInput('What is the return policy?')
    ->asEmbeddings();

$vector = $response->embeddings[0];  // Array of floats
```

## Batch Embeddings

Process multiple texts in a single request:

```php
$response = Atlas::embeddings()
    ->using('openai', 'text-embedding-3-small')
    ->fromArray([
        'How do I return an item?',
        'What is your shipping policy?',
        'Do you offer refunds?',
    ])
    ->asEmbeddings();

// $response->embeddings contains 3 vectors
foreach ($response->embeddings as $index => $vector) {
    // Process each embedding vector
}
```

## Example: Semantic Search

```php
// Index documents
$documents = Document::all();
foreach ($documents as $doc) {
    $response = Atlas::embeddings()
        ->using('openai', 'text-embedding-3-small')
        ->fromInput($doc->content)
        ->asEmbeddings();

    $doc->embedding = $response->embeddings[0];
    $doc->save();
}

// Search with query
$queryResponse = Atlas::embeddings()
    ->using('openai', 'text-embedding-3-small')
    ->fromInput('How do I reset my password?')
    ->asEmbeddings();

$queryEmbedding = $queryResponse->embeddings[0];

// Find similar documents (using pgvector)
$results = Document::query()
    ->orderByRaw('embedding <-> ?', [json_encode($queryEmbedding)])
    ->limit(5)
    ->get();
```

## Example: RAG Implementation

Combine embeddings with agents for retrieval-augmented generation:

```php
class RagService
{
    public function answer(string $question): string
    {
        // 1. Generate query embedding
        $response = Atlas::embeddings()
            ->using('openai', 'text-embedding-3-small')
            ->fromInput($question)
            ->asEmbeddings();

        $queryEmbedding = $response->embeddings[0];

        // 2. Find relevant documents
        $context = Document::query()
            ->orderByRaw('embedding <-> ?', [json_encode($queryEmbedding)])
            ->limit(3)
            ->pluck('content')
            ->join("\n\n");

        // 3. Generate answer with context
        $response = Atlas::agent('rag-agent')
            ->withVariables(['context' => $context])
            ->chat($question);

        return $response->text();
    }
}
```

## Database Storage

### PostgreSQL with pgvector

```sql
CREATE EXTENSION vector;

CREATE TABLE documents (
    id SERIAL PRIMARY KEY,
    content TEXT,
    embedding vector(1536)
);

CREATE INDEX ON documents USING ivfflat (embedding vector_cosine_ops);
```

### MySQL with JSON

```sql
CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content TEXT,
    embedding JSON
);
```

## Default Provider & Model

Configure default embedding provider and model in `config/atlas.php` so you don't need `->using()` on every call:

```php
// config/atlas.php
'embeddings' => [
    'provider' => 'openai',
    'model' => 'text-embedding-3-small',
],
```

```php
// No ->using() needed when defaults are configured
$response = Atlas::embeddings()
    ->fromInput($text)
    ->asEmbeddings();

// Override for a specific call
$response = Atlas::embeddings()
    ->using('voyageai', 'voyage-3')
    ->fromInput($text)
    ->asEmbeddings();
```

## Caching

Atlas provides built-in embedding caching via the `CacheEmbeddings` pipeline middleware. Enable it in config for zero-code caching:

```php
// config/atlas.php
'embeddings' => [
    'provider' => 'openai',
    'model' => 'text-embedding-3-small',
    'cache' => [
        'enabled' => true,       // Enable caching globally
        'store' => null,         // null = default cache store
        'ttl' => 3600,           // Cache lifetime in seconds
    ],
],
```

Once enabled, identical embedding requests automatically return cached responses:

```php
// First call hits the API and caches the result
$response = Atlas::embeddings()
    ->fromInput('What is the return policy?')
    ->asEmbeddings();

// Second call returns the cached response — no API call
$response = Atlas::embeddings()
    ->fromInput('What is the return policy?')
    ->asEmbeddings();
```

### Per-Request Overrides

Control caching per-request via metadata:

```php
// Override TTL for this call
Atlas::embeddings()
    ->withMetadata(['cache_ttl' => 86400])
    ->fromInput($text)
    ->asEmbeddings();

// Disable cache for this call (even if globally enabled)
Atlas::embeddings()
    ->withMetadata(['cache' => false])
    ->fromInput($text)
    ->asEmbeddings();

// Enable cache for this call (even if globally disabled)
Atlas::embeddings()
    ->withMetadata(['cache' => true])
    ->fromInput($text)
    ->asEmbeddings();

// Use a specific cache store
Atlas::embeddings()
    ->withMetadata(['cache_store' => 'redis'])
    ->fromInput($text)
    ->asEmbeddings();

// Use an explicit cache key
Atlas::embeddings()
    ->withMetadata(['cache_key' => 'product-description-42'])
    ->fromInput($text)
    ->asEmbeddings();
```

### Cache Key Strategy

By default, cache keys are generated by hashing the serialized Prism request object. This means different inputs, models, or providers produce different cache keys automatically.

For explicit control, pass a `cache_key` in metadata. Batch embeddings are cached as a group (matching the full request), not individually.

## Best Practices

### Batch When Possible

```php
// Good - single request for multiple texts
$response = Atlas::embeddings()
    ->using('openai', 'text-embedding-3-small')
    ->fromArray($texts)
    ->asEmbeddings();

// Less efficient - multiple requests
foreach ($texts as $text) {
    $response = Atlas::embeddings()
        ->using('openai', 'text-embedding-3-small')
        ->fromInput($text)
        ->asEmbeddings();
}
```

## Pipeline Hooks

Embeddings support pipeline middleware for observability:

<div class="full-width-table">

| Pipeline | Trigger |
|----------|---------|
| `embeddings.before_embeddings` | Before generating embeddings |
| `embeddings.after_embeddings` | After generating embeddings |

</div>

```php
use Atlasphp\Atlas\Contracts\PipelineContract;

class LogEmbeddings implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        $result = $next($data);

        Log::info('Embeddings generated', [
            'count' => count($result['response']->embeddings),
        ]);

        return $result;
    }
}

$registry->register('embeddings.after_embeddings', LogEmbeddings::class);
```

## API Reference

```php
// Embeddings fluent API
Atlas::embeddings()
    ->using(string $provider, string $model)              // Set provider and model
    ->fromInput(string $input)                            // Single text to embed
    ->fromArray(array $inputs)                            // Batch texts to embed
    ->fromFile(string $path)                              // Embed file contents
    ->fromImage(Image $image)                             // Image embedding (if supported)
    ->fromImages(array $images)                           // Batch image embeddings
    ->withProviderOptions(array $options)                 // Provider-specific options
    ->withMetadata(array $metadata)                       // Pipeline metadata
    ->asEmbeddings(): EmbeddingsResponse;

// Response properties (EmbeddingsResponse)
$response->embeddings;           // array of embedding vectors
$response->embeddings[0];        // First embedding (array of floats)
$response->usage->tokens;        // Tokens used

// Common models
->using('openai', 'text-embedding-3-small')   // 1536 dimensions, cheaper
->using('openai', 'text-embedding-3-large')   // 3072 dimensions, more accurate
->using('openai', 'text-embedding-ada-002')   // 1536 dimensions, legacy

// Single vs batch input
->fromInput('Single text to embed')
->fromArray(['Text one', 'Text two', 'Text three'])  // Batch (more efficient)

// Provider options (via withProviderOptions)
->withProviderOptions([
    'dimensions' => 512,         // Reduce dimensions (text-embedding-3-* only)
    'encoding_format' => 'float' // 'float' or 'base64'
])
```

## Next Steps

- [Prism Embeddings](https://prismphp.com/core-concepts/embeddings.html) — Complete embeddings reference
- [Chat](/capabilities/chat) — Use embeddings in RAG workflows
- [Pipelines](/core-concepts/pipelines) — Add observability to embeddings
