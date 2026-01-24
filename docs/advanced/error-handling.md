# Error Handling

Strategies for handling errors in Atlas-powered applications.

## Exception Types

Atlas provides a hierarchy of exceptions to handle different error scenarios.

### Exception Hierarchy

```
Exception
├── AtlasException (base for all Atlas errors)
│   └── ProviderException (API provider errors)
│       ├── RateLimitedException (429 rate limits)
│       ├── ProviderOverloadedException (529 overloaded)
│       ├── RequestTooLargeException (413 context too large)
│       └── StructuredDecodingException (JSON parsing failed)
├── AgentException (agent-related errors)
│   ├── AgentNotFoundException
│   └── InvalidAgentException
└── ToolException (tool-related errors)
    └── ToolNotFoundException
```

### AtlasException

Base exception for Atlas errors:

```php
use Atlasphp\Atlas\Foundation\Exceptions\AtlasException;

try {
    $response = Atlas::agent('agent')->chat('Hello');
} catch (AtlasException $e) {
    Log::error('Atlas error', ['message' => $e->getMessage()]);
}
```

### ProviderException

Base exception for AI provider errors. For most use cases, catch the specific subclasses below:

```php
use Atlasphp\Atlas\Providers\Exceptions\ProviderException;

try {
    $response = Atlas::agent('agent')->chat('Hello');
} catch (ProviderException $e) {
    // Handle any provider-specific error
}
```

### RateLimitedException

Thrown when the provider returns a 429 rate limit error:

```php
use Atlasphp\Atlas\Providers\Exceptions\RateLimitedException;

try {
    $response = Atlas::agent('agent')->chat('Hello');
} catch (RateLimitedException $e) {
    $retryAfter = $e->retryAfter();  // Seconds to wait (from Retry-After header)
    $rateLimits = $e->rateLimits();  // Rate limit details from provider

    if ($retryAfter) {
        sleep($retryAfter);
        // Retry the request...
    }
}
```

### ProviderOverloadedException

Thrown when the provider is temporarily overloaded (529 status):

```php
use Atlasphp\Atlas\Providers\Exceptions\ProviderOverloadedException;

try {
    $response = Atlas::agent('agent')->chat('Hello');
} catch (ProviderOverloadedException $e) {
    $provider = $e->provider();  // 'openai', 'anthropic', etc.

    // Switch to backup provider or queue for later
    Log::warning("Provider {$provider} overloaded, using fallback");
    $response = Atlas::agent('agent')
        ->withProvider('anthropic')
        ->chat('Hello');
}
```

### RequestTooLargeException

Thrown when the request exceeds the provider's context limit (413 status):

```php
use Atlasphp\Atlas\Providers\Exceptions\RequestTooLargeException;

try {
    $response = Atlas::agent('agent')
        ->withMessages($longConversation)
        ->chat('Continue');
} catch (RequestTooLargeException $e) {
    $provider = $e->provider();

    // Trim conversation history and retry
    $trimmedMessages = array_slice($longConversation, -10);
    $response = Atlas::agent('agent')
        ->withMessages($trimmedMessages)
        ->chat('Continue');
}
```

### StructuredDecodingException

Thrown when structured output fails to parse as valid JSON:

```php
use Atlasphp\Atlas\Providers\Exceptions\StructuredDecodingException;

try {
    $response = Atlas::agent('agent')
        ->withSchema($schema)
        ->chat('Extract data from: ...');
} catch (StructuredDecodingException $e) {
    $rawResponse = $e->responseText();  // The raw response that failed to parse

    Log::warning('Structured decoding failed', [
        'raw_response' => $rawResponse,
    ]);

    // Retry or handle gracefully
}

### AgentException

Base exception for agent-related errors:

```php
use Atlasphp\Atlas\Agents\Exceptions\AgentException;

try {
    $response = Atlas::agent('agent')->chat('Hello');
} catch (AgentException $e) {
    // Handle any agent-related error
}
```

### AgentNotFoundException

When an agent cannot be resolved from the registry:

```php
use Atlasphp\Atlas\Agents\Exceptions\AgentNotFoundException;

try {
    $response = Atlas::agent('nonexistent-agent')->chat('Hello');
} catch (AgentNotFoundException $e) {
    // "Agent not found: nonexistent-agent"
}
```

### InvalidAgentException

When an agent configuration is invalid:

```php
use Atlasphp\Atlas\Agents\Exceptions\InvalidAgentException;

try {
    $response = Atlas::agent($invalidAgent)->chat('Hello');
} catch (InvalidAgentException $e) {
    // Agent configuration issue
}
```

### ToolException

Base exception for tool-related errors:

```php
use Atlasphp\Atlas\Contracts\Tools\Exceptions\ToolException;

try {
    $tool = $registry->get('my_tool');
} catch (ToolException $e) {
    // Handle tool-related error
}
```

### ToolNotFoundException

When a tool cannot be resolved from the registry:

```php
use Atlasphp\Atlas\Contracts\Tools\Exceptions\ToolNotFoundException;

try {
    $tool = $registry->get('nonexistent_tool');
} catch (ToolNotFoundException $e) {
    // "Tool not found: nonexistent_tool"
}
```

### Comprehensive Error Handling

Handle specific exceptions first, then fall back to broader types:

```php
use Atlasphp\Atlas\Agents\Exceptions\AgentNotFoundException;
use Atlasphp\Atlas\Providers\Exceptions\RateLimitedException;
use Atlasphp\Atlas\Providers\Exceptions\ProviderOverloadedException;
use Atlasphp\Atlas\Providers\Exceptions\RequestTooLargeException;
use Atlasphp\Atlas\Providers\Exceptions\ProviderException;
use Atlasphp\Atlas\Foundation\Exceptions\AtlasException;

try {
    $response = Atlas::agent($agentKey)->chat($input);
} catch (AgentNotFoundException $e) {
    return response()->json(['error' => 'Agent not configured'], 404);
} catch (RateLimitedException $e) {
    $retryAfter = $e->retryAfter() ?? 60;
    return response()->json([
        'error' => 'Rate limited',
        'retry_after' => $retryAfter,
    ], 429);
} catch (ProviderOverloadedException $e) {
    Log::warning("Provider {$e->provider()} overloaded");
    return response()->json(['error' => 'Service busy, try again'], 503);
} catch (RequestTooLargeException $e) {
    return response()->json(['error' => 'Message too long'], 413);
} catch (ProviderException $e) {
    Log::warning('Provider error', ['error' => $e->getMessage()]);
    return response()->json(['error' => 'AI service unavailable'], 503);
} catch (AtlasException $e) {
    Log::error('Atlas error', ['error' => $e->getMessage()]);
    return response()->json(['error' => 'Service error'], 500);
}
```

## Common Error Scenarios

### Rate Limiting

```php
use Atlasphp\Atlas\Providers\Exceptions\RateLimitedException;

try {
    $response = Atlas::agent('agent')->chat($input);
} catch (RateLimitedException $e) {
    $retryAfter = $e->retryAfter() ?? 60;
    sleep($retryAfter);
    return Atlas::agent('agent')->chat($input);  // Retry
}
```

### Provider Overloaded

```php
use Atlasphp\Atlas\Providers\Exceptions\ProviderOverloadedException;

try {
    $response = Atlas::agent('agent')->chat($input);
} catch (ProviderOverloadedException $e) {
    Log::warning("Provider {$e->provider()} overloaded");

    // Use fallback provider
    return Atlas::agent('agent')
        ->withProvider('anthropic')
        ->chat($input);
}
```

### Context Too Large

```php
use Atlasphp\Atlas\Providers\Exceptions\RequestTooLargeException;

try {
    $response = Atlas::agent('agent')
        ->withMessages($messages)
        ->chat($input);
} catch (RequestTooLargeException $e) {
    // Trim older messages and retry
    $trimmedMessages = array_slice($messages, -10);
    return Atlas::agent('agent')
        ->withMessages($trimmedMessages)
        ->chat($input);
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

### Built-in Retry

Atlas provides automatic retry functionality via `withRetry()`:

```php
// Simple: 3 attempts, 1 second delay
Atlas::agent('agent')->withRetry(3, 1000)->chat('Hello');

// Exponential backoff
Atlas::agent('agent')->withRetry(3, fn($attempt) => (2 ** $attempt) * 100)->chat('Hello');

// Custom delays array
Atlas::agent('agent')->withRetry([100, 500, 2000])->chat('Hello');

// Only retry on rate limit errors
Atlas::agent('agent')->withRetry(3, 1000, fn($e) => $e->getCode() === 429)->chat('Hello');

// Suppress exceptions (return last response)
$response = Atlas::agent('agent')->withRetry(3, 1000, throw: false)->chat('Hello');
```

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$times` | `int\|array` | Number of retries OR array of delays `[100, 200, 300]` |
| `$sleepMilliseconds` | `int\|Closure` | Fixed ms OR `fn(int $attempt): int` |
| `$when` | `?callable` | `fn(Throwable $e): bool` to control when to retry |
| `$throw` | `bool` | Throw after all retries fail (default: `true`) |

Works with all Atlas operations:

```php
Atlas::agent('agent')->withRetry(3, 1000)->chat('Hello');
Atlas::embeddings()->withRetry(3, 1000)->generate('text');
Atlas::embeddings()->withRetry(3, 1000)->generate(['text1', 'text2']);
Atlas::image()->withRetry(3, 1000)->generate('A sunset');
Atlas::speech()->withRetry(3, 1000)->generate('Hello');
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
            return Atlas::agent('primary-agent')->chat($input);
        } catch (ProviderException $e) {
            // Fall back to simpler agent
            return Atlas::agent('fallback-agent')->chat($input);
        }
    }
}
```

### Provider Fallback

Use different providers when the primary fails:

```php
class ResilientChatService
{
    private array $providers = ['openai', 'anthropic', 'gemini'];

    public function respond(string $input): AgentResponse
    {
        $lastException = null;

        foreach ($this->providers as $provider) {
            try {
                return Atlas::agent('support-agent')
                    ->withProvider($provider)
                    ->chat($input);
            } catch (ProviderException $e) {
                Log::warning("Provider {$provider} failed", [
                    'error' => $e->getMessage(),
                ]);
                $lastException = $e;
            }
        }

        throw $lastException ?? new AtlasException('All providers failed');
    }
}
```

### Fallback with Retry per Provider

Combine retry logic with provider fallback:

```php
class HighAvailabilityChatService
{
    private array $providerConfig = [
        'openai' => ['model' => 'gpt-4o', 'retries' => 2],
        'anthropic' => ['model' => 'claude-3-sonnet', 'retries' => 2],
    ];

    public function respond(string $input): AgentResponse
    {
        $lastException = null;

        foreach ($this->providerConfig as $provider => $config) {
            try {
                return Atlas::agent('support-agent')
                    ->withProvider($provider)
                    ->withModel($config['model'])
                    ->withRetry($config['retries'], 1000)
                    ->chat($input);
            } catch (ProviderException $e) {
                Log::warning("Provider {$provider} exhausted retries", [
                    'error' => $e->getMessage(),
                ]);
                $lastException = $e;
            }
        }

        throw $lastException ?? new AtlasException('All providers failed');
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
            return Atlas::agent('agent')->chat($input);
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
            $response = Atlas::agent($validated['agent'])->chat($validated['message']);
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
