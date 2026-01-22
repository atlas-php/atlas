# Error Handling

Strategies for handling errors in Atlas-powered applications.

## Exception Types

### AtlasException

Base exception for Atlas errors:

```php
use Atlasphp\Atlas\Foundation\Exceptions\AtlasException;

try {
    $response = Atlas::chat('agent', 'Hello');
} catch (AtlasException $e) {
    Log::error('Atlas error', ['message' => $e->getMessage()]);
}
```

### ProviderException

Errors from AI providers:

```php
use Atlasphp\Atlas\Providers\Exceptions\ProviderException;

try {
    $response = Atlas::chat('agent', 'Hello');
} catch (ProviderException $e) {
    // Handle provider-specific errors
    // Rate limits, invalid API keys, model errors, etc.
}
```

### AgentNotFoundException

When an agent cannot be resolved:

```php
try {
    $response = Atlas::chat('nonexistent-agent', 'Hello');
} catch (AtlasException $e) {
    // "Agent not found: nonexistent-agent"
}
```

## Common Error Scenarios

### Rate Limiting

```php
try {
    $response = Atlas::chat('agent', $input);
} catch (ProviderException $e) {
    if (str_contains($e->getMessage(), 'rate limit')) {
        // Implement retry with backoff
        return $this->retryWithBackoff($input);
    }
    throw $e;
}
```

### API Key Issues

```php
try {
    $response = Atlas::chat('agent', $input);
} catch (ProviderException $e) {
    if (str_contains($e->getMessage(), 'authentication')) {
        Log::critical('API key invalid or expired');
        throw new ServiceUnavailableException('AI service unavailable');
    }
    throw $e;
}
```

### Model Errors

```php
try {
    $response = Atlas::chat('agent', $input);
} catch (ProviderException $e) {
    if (str_contains($e->getMessage(), 'context length')) {
        // Message too long, try trimming
        return $this->retryWithTrimmedContext($input);
    }
    throw $e;
}
```

## Tool Error Handling

### In Tool Handlers

Return errors instead of throwing exceptions:

```php
public function handle(array $arguments, ToolContext $context): ToolResult
{
    try {
        $order = Order::findOrFail($arguments['order_id']);
        return ToolResult::json($order);
    } catch (ModelNotFoundException $e) {
        return ToolResult::error('Order not found');
    } catch (\Exception $e) {
        Log::error('Tool error', ['error' => $e->getMessage()]);
        return ToolResult::error('Unable to process request');
    }
}
```

### Why Return Errors?

When a tool returns an error, the AI can:
- Try a different approach
- Ask the user for more information
- Gracefully handle the situation

Throwing exceptions stops execution entirely.

## Pipeline Error Handling

### In Pipeline Middleware

```php
class ErrorHandlingMiddleware
{
    public function __invoke(array $data, Closure $next): mixed
    {
        try {
            return $next($data);
        } catch (ProviderException $e) {
            Log::error('Provider error', [
                'agent' => $data['agent']->key(),
                'error' => $e->getMessage(),
            ]);

            // Return fallback response
            return AgentResponse::text(
                'I apologize, but I encountered an issue. Please try again.'
            );
        }
    }
}

// Register with high priority
$registry->register('agent.before_execute', ErrorHandlingMiddleware::class, priority: 1000);
```

## Retry Strategies

### Simple Retry

```php
class RetryService
{
    public function chat(string $agent, string $input, int $maxRetries = 3): AgentResponse
    {
        $lastException = null;

        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                return Atlas::chat($agent, $input);
            } catch (ProviderException $e) {
                $lastException = $e;
                sleep(pow(2, $i)); // Exponential backoff
            }
        }

        throw $lastException;
    }
}
```

### With Circuit Breaker

```php
class CircuitBreaker
{
    private int $failures = 0;
    private int $threshold = 5;
    private ?Carbon $openUntil = null;

    public function execute(callable $operation): mixed
    {
        if ($this->isOpen()) {
            throw new CircuitOpenException('Service temporarily unavailable');
        }

        try {
            $result = $operation();
            $this->recordSuccess();
            return $result;
        } catch (\Exception $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    private function isOpen(): bool
    {
        return $this->openUntil && $this->openUntil->isFuture();
    }

    private function recordSuccess(): void
    {
        $this->failures = 0;
    }

    private function recordFailure(): void
    {
        $this->failures++;
        if ($this->failures >= $this->threshold) {
            $this->openUntil = now()->addMinutes(5);
        }
    }
}
```

## Graceful Degradation

### Fallback Agents

```php
class ChatService
{
    public function respond(string $input): AgentResponse
    {
        try {
            // Try primary agent
            return Atlas::chat('primary-agent', $input);
        } catch (ProviderException $e) {
            // Fall back to simpler agent
            return Atlas::chat('fallback-agent', $input);
        }
    }
}
```

### Fallback Responses

```php
class ChatService
{
    public function respond(string $input): AgentResponse
    {
        try {
            return Atlas::chat('agent', $input);
        } catch (\Exception $e) {
            Log::error('Chat failed', ['error' => $e->getMessage()]);

            return AgentResponse::text(
                'I apologize, but I\'m having trouble processing your request. ' .
                'Please try again in a moment.'
            );
        }
    }
}
```

## Validation Errors

### Input Validation

```php
class ChatController extends Controller
{
    public function respond(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:10000',
            'agent' => 'required|string|in:support,sales,help',
        ]);

        try {
            $response = Atlas::chat($validated['agent'], $validated['message']);
            return response()->json(['message' => $response->text]);
        } catch (AtlasException $e) {
            return response()->json(['error' => 'Service error'], 503);
        }
    }
}
```

### Tool Parameter Validation

```php
public function handle(array $arguments, ToolContext $context): ToolResult
{
    // Validate required parameters
    if (empty($arguments['order_id'])) {
        return ToolResult::error('Order ID is required');
    }

    // Validate format
    if (! preg_match('/^ORD-\d+$/', $arguments['order_id'])) {
        return ToolResult::error('Invalid order ID format');
    }

    // Continue with processing...
}
```

## Logging Best Practices

### Structured Logging

```php
class LoggingMiddleware
{
    public function __invoke(array $data, Closure $next): mixed
    {
        $startTime = microtime(true);

        try {
            $result = $next($data);

            Log::info('Atlas request completed', [
                'agent' => $data['agent']->key(),
                'duration_ms' => (microtime(true) - $startTime) * 1000,
                'tokens' => $result->totalTokens(),
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Atlas request failed', [
                'agent' => $data['agent']->key(),
                'duration_ms' => (microtime(true) - $startTime) * 1000,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
```

## Next Steps

- [Performance](/advanced/performance) — Optimize for reliability
- [Pipelines](/core-concepts/pipelines) — Add error handling middleware
- [Testing](/guides/testing) — Test error scenarios
