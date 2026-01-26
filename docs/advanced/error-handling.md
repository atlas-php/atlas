# Error Handling

Strategies for handling errors in Atlas-powered applications.

::: tip Prism Reference
Provider-level exceptions (rate limits, overloaded, context too large) are handled by Prism. See [Prism Error Handling](https://prismphp.com/advanced/error-handling.html) for provider exceptions and retry strategies.
:::

## Atlas Exceptions

Atlas provides exceptions for agent and tool-related errors.

### Exception Hierarchy

```
Exception
├── AtlasException (base for Atlas errors)
├── AgentException (agent-related errors)
│   ├── AgentNotFoundException
│   └── InvalidAgentException
└── ToolException (tool-related errors)
    └── ToolNotFoundException
```

### AtlasException

Base exception for Atlas configuration errors:

```php
use Atlasphp\Atlas\Exceptions\AtlasException;

try {
    $response = Atlas::agent('agent')->chat('Hello');
} catch (AtlasException $e) {
    Log::error('Atlas error', ['message' => $e->getMessage()]);
}
```

### AgentNotFoundException

When an agent cannot be resolved from the registry:

```php
use Atlasphp\Atlas\Agents\Exceptions\AgentNotFoundException;

try {
    $response = Atlas::agent('nonexistent-agent')->chat('Hello');
} catch (AgentNotFoundException $e) {
    // "No agent found with key 'nonexistent-agent'."
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

### ToolNotFoundException

When a tool cannot be resolved from the registry:

```php
use Atlasphp\Atlas\Tools\Exceptions\ToolNotFoundException;

try {
    $tool = $registry->get('nonexistent_tool');
} catch (ToolNotFoundException $e) {
    // "Tool not found: nonexistent_tool"
}
```

## Provider Exceptions

Provider-level exceptions (API errors, rate limits, context limits) come from Prism and pass through Atlas. Handle them in your application:

```php
use Atlasphp\Atlas\Agents\Exceptions\AgentNotFoundException;

try {
    $response = Atlas::agent($agentKey)->chat($input);
} catch (AgentNotFoundException $e) {
    return response()->json(['error' => 'Agent not configured'], 404);
} catch (\Exception $e) {
    // Provider errors pass through from Prism
    Log::error('Request failed', ['error' => $e->getMessage()]);
    return response()->json(['error' => 'Service error'], 500);
}
```

See [Prism Error Handling](https://prismphp.com/advanced/error-handling.html) for catching specific provider exceptions like rate limits and context overflow.

## Tool Error Handling

### Return Errors, Don't Throw

In tool handlers, return errors instead of throwing exceptions:

```php
public function handle(array $params, ToolContext $context): ToolResult
{
    try {
        $order = Order::findOrFail($params['order_id']);
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

### Parameter Validation

```php
public function handle(array $params, ToolContext $context): ToolResult
{
    if (empty($params['order_id'])) {
        return ToolResult::error('Order ID is required');
    }

    if (! preg_match('/^ORD-\d+$/', $params['order_id'])) {
        return ToolResult::error('Invalid order ID format');
    }

    // Continue with processing...
}
```

## Pipeline Error Handling

Add error handling middleware to pipelines:

```php
use Atlasphp\Atlas\Contracts\PipelineContract;

class ErrorLoggingMiddleware implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        try {
            return $next($data);
        } catch (\Exception $e) {
            Log::error('Pipeline error', [
                'agent' => $data['agent']->key(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

$registry->register('agent.before_execute', ErrorLoggingMiddleware::class, priority: 1000);
```

### Error Recovery via Pipeline

The `agent.on_error` pipeline supports returning a recovery response instead of throwing:

```php
$registry->register('agent.on_error', function (mixed $data, Closure $next) {
    if ($data['exception'] instanceof RateLimitException) {
        $data['recovery'] = new PrismResponse(/* fallback response */);
    }

    return $next($data);
});
```

When a `recovery` key is set with a valid response, the exception is suppressed and the recovery response is returned. See [Pipelines](/core-concepts/pipelines#agent-on_error) for full details.

## Graceful Degradation

### Provider Fallback

```php
class ResilientChatService
{
    private array $providers = ['openai', 'anthropic'];

    public function respond(string $input): mixed
    {
        $lastException = null;

        foreach ($this->providers as $provider) {
            try {
                return Atlas::agent('support-agent')
                    ->withProvider($provider)
                    ->chat($input);
            } catch (\Exception $e) {
                Log::warning("Provider {$provider} failed");
                $lastException = $e;
            }
        }

        throw $lastException;
    }
}
```

## Input Validation

Validate input before sending to Atlas:

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
        } catch (\Exception $e) {
            return response()->json(['error' => 'Service error'], 503);
        }
    }
}
```

## API Reference

```php
// Atlas exception hierarchy
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Agents\Exceptions\AgentException;
use Atlasphp\Atlas\Agents\Exceptions\AgentNotFoundException;
use Atlasphp\Atlas\Agents\Exceptions\InvalidAgentException;
use Atlasphp\Atlas\Tools\Exceptions\ToolException;
use Atlasphp\Atlas\Tools\Exceptions\ToolNotFoundException;

// Base exception - catch all Atlas errors
try {
    $response = Atlas::agent('agent')->chat('Hello');
} catch (AtlasException $e) {
    // Any Atlas configuration error
}

// Agent exceptions
try {
    $response = Atlas::agent('unknown')->chat('Hello');
} catch (AgentNotFoundException $e) {
    $e->getMessage();  // "No agent found with key 'unknown'."
} catch (InvalidAgentException $e) {
    $e->getMessage();  // Invalid agent configuration
} catch (AgentException $e) {
    // Any agent-related error
}

// Tool exceptions
try {
    $tool = $registry->get('unknown_tool');
} catch (ToolNotFoundException $e) {
    $e->getMessage();  // "Tool not found: unknown_tool"
} catch (ToolException $e) {
    // Any tool-related error
}

// ToolResult for tool error handling (don't throw, return errors)
use Atlasphp\Atlas\Tools\Support\ToolResult;

ToolResult::text(string $text): ToolResult;   // Success with text
ToolResult::json(array $data): ToolResult;    // Success with JSON
ToolResult::error(string $message): ToolResult; // Error result

$result->succeeded(): bool;  // Check if successful
$result->failed(): bool;     // Check if failed

// Prism exceptions (pass through Atlas)
// See: https://prismphp.com/advanced/error-handling.html
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Exceptions\PrismRateLimitException;
use Prism\Prism\Exceptions\PrismContextLengthExceededException;

// Retry configuration (via Prism passthrough)
Atlas::agent('agent')
    ->withClientRetry(int $times, int $sleepMs)  // Automatic retries
    ->chat('Hello');
```

## Next Steps

- [Pipelines](/core-concepts/pipelines) — Add error handling middleware
- [Testing](/advanced/testing) — Test error scenarios
