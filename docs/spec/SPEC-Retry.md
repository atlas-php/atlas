# Retry & Resilience Specification

## Overview

Atlas provides automatic retry functionality for API requests by leveraging Prism's `withClientRetry()` method. This passthrough approach gives you the full power of Laravel's HTTP client retry mechanism while maintaining a clean, user-friendly API.

## Quick Start

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

// Simple: 3 attempts, 1 second delay
Atlas::agent('agent')->withRetry(3, 1000)->chat('Hello');

// Exponential backoff
Atlas::agent('agent')->withRetry(3, fn($attempt) => (2 ** $attempt) * 100)->chat('Hello');

// Custom delays array
Atlas::agent('agent')->withRetry([100, 500, 2000])->chat('Hello');
```

## API Reference

### `withRetry()` Method

Available on `PendingAgentRequest`, `PendingEmbeddingRequest`, `ImageService`, and `SpeechService`.

```php
public function withRetry(
    array|int $times,
    Closure|int $sleepMilliseconds = 0,
    ?callable $when = null,
    bool $throw = true,
): static
```

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$times` | `int\|array` | Number of retry attempts OR array of specific delays in ms `[100, 200, 300]` |
| `$sleepMilliseconds` | `int\|Closure` | Fixed delay in ms OR `fn(int $attempt, Throwable $e): int` for dynamic delay |
| `$when` | `?callable` | `fn(Throwable $e, PendingRequest $req): bool` to control when to retry |
| `$throw` | `bool` | Whether to throw after all retries fail (default: `true`) |

**Returns:** Cloned instance with retry configuration applied

## Backoff Strategies

### Fixed Delay

Wait the same amount of time between each retry:

```php
// Wait 1 second between each of 3 attempts
Atlas::agent('agent')->withRetry(3, 1000)->chat('Hello');
```

### Exponential Backoff

Increase wait time exponentially between retries:

```php
// 100ms, 200ms, 400ms delays
Atlas::agent('agent')->withRetry(3, fn($attempt) => (2 ** $attempt) * 100)->chat('Hello');

// Or use the RetryConfig helper
use Atlasphp\Atlas\Providers\Support\RetryConfig;

$config = RetryConfig::exponential(3, 100); // 3 attempts, 100ms base
```

### Custom Delay Schedule

Specify exact delays for each retry:

```php
// First retry after 100ms, second after 500ms, third after 2000ms
Atlas::agent('agent')->withRetry([100, 500, 2000])->chat('Hello');
```

### Jitter (Randomized Delay)

Add randomness to prevent thundering herd:

```php
Atlas::agent('agent')
    ->withRetry(3, fn($attempt) => random_int(100, 500) * $attempt)
    ->chat('Hello');
```

## Retry Conditions

Control when retries should occur using the `$when` callback:

```php
// Only retry on rate limit errors
Atlas::agent('agent')
    ->withRetry(3, 1000, fn($e) => $e->getCode() === 429)
    ->chat('Hello');

// Only retry on specific exception types
Atlas::agent('agent')
    ->withRetry(3, 1000, fn($e) => $e instanceof ConnectionException)
    ->chat('Hello');

// Retry on any server error
Atlas::agent('agent')
    ->withRetry(3, 1000, fn($e) => $e->getCode() >= 500)
    ->chat('Hello');
```

## Error Handling

### Throw After Failure (Default)

By default, if all retries fail, the exception is thrown:

```php
try {
    Atlas::agent('agent')->withRetry(3, 1000)->chat('Hello');
} catch (Exception $e) {
    // Handle the failure
}
```

### Suppress Exceptions

Set `throw: false` to suppress exceptions and return the last response:

```php
$response = Atlas::agent('agent')
    ->withRetry(3, 1000, throw: false)
    ->chat('Hello');

// Check if the response was successful
if ($response->hasText()) {
    // Success
}
```

## Supported Operations

Retry configuration works with all Atlas operations:

### Chat/Agent

```php
Atlas::agent('agent')->withRetry(3, 1000)->chat('Hello');
```

### Embeddings

```php
Atlas::embeddings()->withRetry(3, 1000)->generate('text to embed');
Atlas::embeddings()->withRetry(3, 1000)->generateBatch(['text 1', 'text 2']);
```

### Multi-turn Conversations

```php
Atlas::agent('agent')
    ->withMessages($messages)
    ->withVariables(['name' => 'John'])
    ->withRetry(3, 1000)
    ->chat('Continue the conversation');
```

### Images

```php
Atlas::image()
    ->withRetry(3, 1000)
    ->size('1024x1024')
    ->generate('A sunset over mountains');
```

### Speech

```php
Atlas::speech()
    ->withRetry(3, 1000)
    ->voice('alloy')
    ->generate('Hello, world!');
```

## RetryConfig Helper

The `RetryConfig` class provides factory methods for creating retry configurations. This is useful when you want to reuse the same configuration or use the built-in exponential backoff without writing the closure yourself:

```php
use Atlasphp\Atlas\Providers\Support\RetryConfig;

// Create an exponential backoff config
$config = RetryConfig::exponential(3, 100);  // 3 attempts, 100ms base delay

// Apply to Atlas using spread operator
Atlas::agent('agent')->withRetry(...$config->toArray())->chat('Hello');

// Available factory methods
$config = RetryConfig::none();           // Disable retry
$config = RetryConfig::fixed(3, 1000);   // 3 attempts, 1000ms fixed delay
$config = RetryConfig::exponential(3);   // 3 attempts, exponential backoff (100ms base)

// Check if retry is enabled
if ($config->isEnabled()) {
    // ...
}
```

Note: For simple cases, calling `->withRetry()` directly is more straightforward.

## Architecture

The retry configuration flows through the system as follows:

```
Atlas::agent('agent')->withRetry(3, 1000)->chat('Hello')
    ↓
PendingAgentRequest (captures retry config)
    ↓
AgentExecutor (passes retry to PrismBuilder)
    ↓
PrismBuilder.forPrompt() (applies withClientRetry() to request)
    ↓
Prism PendingRequest (executes with Laravel HTTP retry)
```

This passthrough design means:
- No retry logic is implemented in Atlas - we delegate to Prism/Laravel
- Full compatibility with Prism's retry behavior
- Minimal code, maximum flexibility

## Best Practices

1. **Start with reasonable defaults**: 3 retries with 1 second delay works for most cases.

2. **Use exponential backoff for rate limits**: Gives the API time to recover.

3. **Add retry conditions**: Don't retry on 4xx client errors (except 429).

4. **Consider circuit breakers**: For high-traffic applications, consider additional resilience patterns.

5. **Monitor retry behavior**: Track retry counts in your observability pipeline.

## Related

- [Prism Documentation](https://prism.echolabs.dev/) - Underlying retry mechanism
- [Laravel HTTP Client](https://laravel.com/docs/http-client#retries) - Retry implementation
