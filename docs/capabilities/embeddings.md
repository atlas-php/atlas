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

### Cache Embeddings

```php
$cacheKey = 'embedding:' . md5($text);

$embedding = Cache::remember($cacheKey, 3600, function () use ($text) {
    $response = Atlas::embeddings()
        ->using('openai', 'text-embedding-3-small')
        ->fromInput($text)
        ->asEmbeddings();

    return $response->embeddings[0];
});
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
