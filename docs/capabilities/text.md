# Text

Generate text directly using Prism's text generation API without agents.

::: tip Prism Reference
Atlas text generation wraps Prism's text API. For detailed documentation including all options, see [Prism Text Generation](https://prismphp.com/core-concepts/text-generation.html).
:::

## Basic Usage

```php
use Atlasphp\Atlas\Atlas;

$response = Atlas::text()
    ->using('openai', 'gpt-4o')
    ->withSystemPrompt('You are a helpful assistant.')
    ->withPrompt('What is Laravel?')
    ->asText();

echo $response->text;
```

## With Options

```php
$response = Atlas::text()
    ->using('anthropic', 'claude-sonnet-4-20250514')
    ->withSystemPrompt('You are a technical writer.')
    ->withPrompt('Explain dependency injection in PHP.')
    ->withMaxTokens(1000)
    ->withTemperature(0.7)
    ->asText();
```

## With Messages

```php
$response = Atlas::text()
    ->using('openai', 'gpt-4o')
    ->withMessages([
        ['role' => 'user', 'content' => 'Hello!'],
        ['role' => 'assistant', 'content' => 'Hi there! How can I help?'],
        ['role' => 'user', 'content' => 'What is PHP?'],
    ])
    ->asText();
```

## Pipeline Hooks

Text generation supports pipeline middleware for observability:

<div class="full-width-table">

| Pipeline | Trigger |
|----------|---------|
| `text.before_text` | Before text generation |
| `text.after_text` | After text generation |
| `text.before_stream` | Before streaming text generation |
| `text.after_stream` | After streaming text generation |

</div>

```php
use Atlasphp\Atlas\Contracts\PipelineContract;

class LogTextGeneration implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        $result = $next($data);

        Log::info('Text generated', [
            'user_id' => $data['metadata']['user_id'] ?? null,
        ]);

        return $result;
    }
}

$registry->register('text.after_text', LogTextGeneration::class);
```

## API Reference

```php
// Text generation fluent API
Atlas::text()
    ->using(string $provider, string $model)              // Set provider and model
    ->withSystemPrompt(string $prompt)                    // System instructions
    ->withPrompt(string $prompt)                          // User prompt
    ->withMessages(array $messages)                       // Message history
    ->withMaxTokens(int $tokens)                          // Max response tokens
    ->withTemperature(float $temp)                        // Sampling temperature (0-2)
    ->usingTopP(float $topP)                              // Top-p sampling
    ->usingTopK(int $topK)                                // Top-k sampling
    ->withClientRetry(int $times, int $sleepMs)           // Retry with backoff
    ->withClientOptions(array $options)                   // HTTP client options
    ->withProviderOptions(array $options)                 // Provider-specific options
    ->withMetadata(array $metadata)                       // Pipeline metadata
    ->asText(): PrismResponse;                            // Execute and return response
    ->asStream(): Generator<StreamEvent>;                 // Execute and stream

// Response properties (PrismResponse)
$response->text;                    // Generated text
$response->finishReason;            // FinishReason enum
$response->usage->promptTokens;     // Input tokens
$response->usage->completionTokens; // Output tokens
$response->meta;                    // Request metadata

// Message format for withMessages()
[
    ['role' => 'system', 'content' => 'You are helpful.'],
    ['role' => 'user', 'content' => 'Hello'],
    ['role' => 'assistant', 'content' => 'Hi there!'],
]
```

## Next Steps

- [Chat](/capabilities/chat) — Agent-based conversations
- [Streaming](/capabilities/streaming) — Real-time streaming responses
- [Prism Text Generation](https://prismphp.com/core-concepts/text-generation.html) — Complete text generation reference
