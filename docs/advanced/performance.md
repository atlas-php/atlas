# Performance

Optimization strategies for Atlas-powered applications.

## Token Optimization

### Trim Conversation History

Long conversations consume tokens. Implement trimming:

```php
class ConversationTrimmer
{
    public function trim(array $messages, int $maxMessages = 20): array
    {
        if (count($messages) <= $maxMessages) {
            return $messages;
        }

        return array_slice($messages, -$maxMessages);
    }
}
```

### Summarize Old Messages

Replace old messages with summaries:

```php
class ConversationSummarizer
{
    public function summarize(array $messages): array
    {
        if (count($messages) < 30) {
            return $messages;
        }

        $toSummarize = array_slice($messages, 0, -10);
        $recent = array_slice($messages, -10);

        $summary = Atlas::agent('summarizer')->chat(json_encode($toSummarize));

        return [
            ['role' => 'system', 'content' => "Previous: {$summary->text}"],
            ...$recent,
        ];
    }
}
```

### Concise System Prompts

Keep system prompts focused:

```php
// Good - concise and focused
public function systemPrompt(): string
{
    return 'You are a support agent. Help with orders. Be brief.';
}

// Avoid - verbose and repetitive
public function systemPrompt(): string
{
    return 'You are a customer support agent for our company.
    Your job is to help customers with their orders.
    Always be helpful and friendly.
    Provide accurate information.
    ...hundreds more lines...';
}
```

## Caching Strategies

### Cache Embeddings

Embeddings for the same text are always identical:

```php
class CachedEmbeddingService
{
    public function embed(string $text): array
    {
        $key = 'embedding:' . md5($text);

        return Cache::remember($key, 86400, fn() => Atlas::embed($text));
    }
}
```

### Cache Frequent Queries

Cache responses for common questions:

```php
class CachedChatService
{
    public function respond(string $input): AgentResponse
    {
        // Only cache deterministic, context-free queries
        if ($this->isCacheable($input)) {
            $key = 'chat:' . md5($input);
            $cached = Cache::get($key);

            if ($cached) {
                return AgentResponse::text($cached);
            }

            $response = Atlas::agent('agent')->chat($input);
            Cache::put($key, $response->text, 3600);
            return $response;
        }

        return Atlas::agent('agent')->chat($input);
    }

    private function isCacheable(string $input): bool
    {
        // Cache FAQ-style queries
        return str_starts_with($input, 'What is') ||
               str_starts_with($input, 'How do I');
    }
}
```

## Batch Operations

### Batch Embeddings

Use batch API for multiple texts:

```php
// Good - single batch request
$embeddings = Atlas::embedBatch($texts);

// Avoid - multiple requests
foreach ($texts as $text) {
    $embeddings[] = Atlas::embed($text);
}
```

### Parallel Processing

For independent operations, use queues:

```php
class EmbeddingJob implements ShouldQueue
{
    public function __construct(
        public Document $document,
    ) {}

    public function handle(): void
    {
        $embedding = Atlas::embed($this->document->content);
        $this->document->embedding = $embedding;
        $this->document->save();
    }
}

// Dispatch multiple jobs
foreach ($documents as $document) {
    EmbeddingJob::dispatch($document);
}
```

## Model Selection

### Choose Appropriate Models

| Use Case | Recommended | Why |
|----------|-------------|-----|
| Simple chat | `gpt-3.5-turbo` | Faster, cheaper |
| Complex reasoning | `gpt-4o` | Better accuracy |
| Quick embeddings | `text-embedding-3-small` | 1536 dims, fast |
| High-quality embeddings | `text-embedding-3-large` | 3072 dims, better |

### Dynamic Model Selection

```php
class AdaptiveAgent extends AgentDefinition
{
    public function model(): string
    {
        // Use cheaper model for simple queries
        if ($this->isSimpleQuery()) {
            return 'gpt-3.5-turbo';
        }

        return 'gpt-4o';
    }
}
```

## Pipeline Optimization

### Minimize Pipeline Overhead

Keep pipeline handlers lightweight:

```php
// Good - async logging
class AsyncLoggingMiddleware
{
    public function __invoke(array $data, Closure $next): mixed
    {
        $result = $next($data);

        // Log asynchronously
        dispatch(new LogAgentExecution($data, $result))->afterResponse();

        return $result;
    }
}

// Avoid - synchronous heavy operations
class HeavyMiddleware
{
    public function __invoke(array $data, Closure $next): mixed
    {
        // Don't do heavy operations synchronously
        $this->heavyAnalysis($data);
        return $next($data);
    }
}
```

### Conditional Pipelines

Only run when needed:

```php
class ConditionalAuditMiddleware
{
    public function __invoke(array $data, Closure $next): mixed
    {
        $result = $next($data);

        // Only audit certain agents
        if ($this->shouldAudit($data['agent'])) {
            AuditLog::create([...]);
        }

        return $result;
    }

    private function shouldAudit(AgentContract $agent): bool
    {
        return in_array($agent->key(), ['financial-agent', 'admin-agent']);
    }
}
```

## Database Optimization

### Index Embeddings

For vector search, use appropriate indexes:

```sql
-- PostgreSQL with pgvector
CREATE INDEX ON documents USING ivfflat (embedding vector_cosine_ops);

-- Or HNSW for better accuracy
CREATE INDEX ON documents USING hnsw (embedding vector_cosine_ops);
```

### Efficient Conversation Storage

```php
// Good - store as JSON column
$table->json('messages');

// For very long conversations, consider separate table
$table->id();
$table->foreignId('conversation_id');
$table->string('role');
$table->text('content');
$table->timestamps();
```

## Connection Pooling

### HTTP Client Configuration

Configure HTTP client for better performance:

```php
// config/atlas.php
'http' => [
    'timeout' => 30,
    'connect_timeout' => 5,
    'pool' => [
        'max_connections' => 50,
        'idle_timeout' => 60,
    ],
],
```

## Monitoring

### Track Key Metrics

```php
class MetricsMiddleware
{
    public function __invoke(array $data, Closure $next): mixed
    {
        $start = microtime(true);

        $result = $next($data);

        $duration = microtime(true) - $start;

        // Record metrics
        Metrics::timing('atlas.request.duration', $duration);
        Metrics::increment('atlas.request.count');
        Metrics::gauge('atlas.tokens.total', $result->totalTokens());

        return $result;
    }
}
```

### Set Up Alerts

```php
class AlertingMiddleware
{
    public function __invoke(array $data, Closure $next): mixed
    {
        $start = microtime(true);
        $result = $next($data);
        $duration = microtime(true) - $start;

        // Alert on slow requests
        if ($duration > 10) {
            Alert::send("Slow Atlas request: {$duration}s");
        }

        // Alert on high token usage
        if ($result->totalTokens() > 10000) {
            Alert::send("High token usage: {$result->totalTokens()}");
        }

        return $result;
    }
}
```

## Quick Reference

| Optimization | Impact | Effort |
|--------------|--------|--------|
| Trim conversations | High | Low |
| Cache embeddings | High | Low |
| Batch operations | High | Low |
| Choose right model | Medium | Low |
| Async logging | Medium | Medium |
| Vector indexes | High | Medium |
| Connection pooling | Medium | Medium |
| Monitoring | Ongoing | Medium |

## Next Steps

- [Error Handling](/advanced/error-handling) — Handle failures gracefully
- [Pipelines](/core-concepts/pipelines) — Optimize middleware
- [Embeddings](/capabilities/embeddings) — Embedding best practices
