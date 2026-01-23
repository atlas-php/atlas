# Embeddings

Generate vector embeddings for semantic search, RAG, and similarity matching.

## What are Embeddings?

Embeddings are numerical representations of text that capture semantic meaning. Similar texts have similar embeddings, enabling:

- **Semantic search** — Find relevant documents by meaning, not just keywords
- **RAG (Retrieval Augmented Generation)** — Provide context to AI from your data
- **Similarity matching** — Compare texts for relatedness
- **Clustering** — Group similar content together

## Single Embedding

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

$embedding = Atlas::embedding()->generate('What is the return policy?');
// Returns array of 1536 floats (for text-embedding-3-small)
```

## Batch Embeddings

Process multiple texts efficiently by passing an array:

```php
$texts = [
    'How do I return an item?',
    'What is your shipping policy?',
    'Do you offer refunds?',
];

$embeddings = Atlas::embedding()->generate($texts);
// Returns array of 3 embedding vectors
```

## Get Configured Dimensions

```php
$dimensions = Atlas::embedding()->dimensions();
// 1536 (for text-embedding-3-small)
```

## Configuration

Configure embeddings in `config/atlas.php`:

```php
'embedding' => [
    'provider' => 'openai',
    'model' => 'text-embedding-3-small',
    'dimensions' => 1536,
    'batch_size' => 100,
],
```

Or via environment variables:

```env
ATLAS_EMBEDDING_PROVIDER=openai
ATLAS_EMBEDDING_MODEL=text-embedding-3-small
ATLAS_EMBEDDING_DIMENSIONS=1536
ATLAS_EMBEDDING_BATCH_SIZE=100
```

## Available Models

### OpenAI

| Model | Dimensions | Best For |
|-------|------------|----------|
| `text-embedding-3-small` | 1536 (or 256-1536) | Cost-effective general use |
| `text-embedding-3-large` | 3072 (or 256-3072) | Higher accuracy |
| `text-embedding-ada-002` | 1536 | Legacy support |

## Semantic Search Example

```php
// Index documents
$documents = Document::all();
foreach ($documents as $doc) {
    $doc->embedding = Atlas::embedding()->generate($doc->content);
    $doc->save();
}

// Search with query
$queryEmbedding = Atlas::embedding()->generate('How do I reset my password?');

// Find similar documents (using pgvector)
$results = Document::query()
    ->orderByRaw('embedding <-> ?', [json_encode($queryEmbedding)])
    ->limit(5)
    ->get();
```

## RAG Implementation

```php
class RagService
{
    public function answer(string $question): string
    {
        // 1. Generate query embedding
        $queryEmbedding = Atlas::embedding()->generate($question);

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

        return $response->text;
    }
}
```

With an agent like:

```php
class RagAgent extends AgentDefinition
{
    public function systemPrompt(): string
    {
        return <<<PROMPT
        Answer questions using the provided context.

        Context:
        {context}

        If the context doesn't contain relevant information, say so.
        PROMPT;
    }
}
```

## Chunking Strategies

For long documents, split into chunks before embedding:

```php
class DocumentChunker
{
    public function chunk(string $content, int $maxTokens = 500): array
    {
        // Split by paragraphs
        $paragraphs = preg_split('/\n\n+/', $content);

        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            if ($this->estimateTokens($current . $paragraph) > $maxTokens) {
                if ($current) {
                    $chunks[] = trim($current);
                }
                $current = $paragraph;
            } else {
                $current .= "\n\n" . $paragraph;
            }
        }

        if ($current) {
            $chunks[] = trim($current);
        }

        return $chunks;
    }

    private function estimateTokens(string $text): int
    {
        return (int) ceil(strlen($text) / 4);
    }
}
```

## Best Practices

### 1. Batch When Possible

```php
// Good - single batch request
$embeddings = Atlas::embedding()->generate($texts);

// Less efficient - multiple requests
foreach ($texts as $text) {
    $embeddings[] = Atlas::embedding()->generate($text);
}
```

### 2. Cache Embeddings

```php
$cacheKey = 'embedding:' . md5($text);
$embedding = Cache::remember($cacheKey, 3600, fn() => Atlas::embedding()->generate($text));
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

## Retry & Resilience

Enable automatic retries for embedding requests using the fluent pattern:

```php
// Simple retry: 3 attempts, 1 second delay
$embedding = Atlas::embedding()
    ->withRetry(3, 1000)
    ->generate('Hello world');

// Batch with retry
$embeddings = Atlas::embedding()
    ->withRetry(3, 1000)
    ->generate($texts);

// Exponential backoff
$embedding = Atlas::embedding()
    ->withRetry(3, fn($attempt) => (2 ** $attempt) * 100)
    ->generate('Hello world');

// Only retry on rate limits
$embedding = Atlas::embedding()
    ->withRetry(3, 1000, fn($e) => $e->getCode() === 429)
    ->generate('Hello world');
```

## API Summary

| Method | Description |
|--------|-------------|
| `Atlas::embedding()->generate($text)` | Single text embedding |
| `Atlas::embedding()->generate($texts)` | Batch embeddings (array input) |
| `Atlas::embedding()->dimensions()` | Get configured vector dimensions |
| `Atlas::embedding()->withRetry(...)->generate($text)` | With retry |
| `Atlas::embedding()->withMetadata([...])->generate($text)` | With metadata |

## Next Steps

- [Configuration](/getting-started/configuration) — Configure embedding providers
- [Chat](/capabilities/chat) — Use embeddings in RAG workflows
