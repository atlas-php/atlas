# Error Handling

Strategies for handling errors in Atlas-powered applications.

## Exception Hierarchy

Atlas maps provider HTTP errors and configuration problems to typed exceptions:

```
AtlasException (base — extends RuntimeException, catch all Atlas errors)
├── AuthenticationException        (401 — invalid API key)
├── AuthorizationException         (403 — model access denied)
├── RateLimitException             (429 — rate limit with retry info)
├── ProviderException              (all other HTTP errors)
├── UnsupportedFeatureException    (modality not supported by provider)
├── ProviderNotFoundException      (provider key not registered)
├── AgentNotFoundException         (agent key not found)
├── ToolNotFoundException          (tool name not in registry)
└── MaxStepsExceededException      (executor exceeded step limit)
```

All exceptions live in `Atlasphp\Atlas\Exceptions`.

## Provider Exceptions

Atlas automatically maps HTTP error codes to specific exception types:

```php
use Atlasphp\Atlas\Exceptions\AuthenticationException;
use Atlasphp\Atlas\Exceptions\AuthorizationException;
use Atlasphp\Atlas\Exceptions\RateLimitException;
use Atlasphp\Atlas\Exceptions\ProviderException;

try {
    $response = Atlas::text(Provider::OpenAI, 'gpt-4o-mini')
        ->message('Hello')
        ->asText();
} catch (AuthenticationException $e) {
    // 401 — Invalid or missing API key
    // $e->getMessage() includes provider name
} catch (AuthorizationException $e) {
    // 403 — No access to this model
} catch (RateLimitException $e) {
    // 429 — Too many requests
    $retryAfter = $e->retryAfter; // Seconds to wait (from Retry-After header)
} catch (ProviderException $e) {
    // All other HTTP errors (400, 500, etc.)
    $e->statusCode;        // HTTP status code
    $e->providerMessage;   // Error message from provider
    $e->provider;          // Provider name (e.g., 'openai')
}
```

### Catching All Atlas Errors

```php
use Atlasphp\Atlas\Exceptions\AtlasException;

try {
    $response = Atlas::agent('assistant')
        ->message('Hello')
        ->asText();
} catch (AtlasException $e) {
    // Catches any Atlas exception (provider errors, config errors, etc.)
    Log::error('Atlas error', ['message' => $e->getMessage()]);
}
```

## Agent & Configuration Exceptions

### Agent Not Found

```php
use Atlasphp\Atlas\Exceptions\AgentNotFoundException;

try {
    $response = Atlas::agent('unknown-agent')->message('Hi')->asText();
} catch (AgentNotFoundException $e) {
    // "Agent [unknown-agent] is not registered."
}
```

### Max Steps Exceeded

```php
use Atlasphp\Atlas\Exceptions\MaxStepsExceededException;

try {
    $response = Atlas::agent('assistant')->message('Hi')->asText();
} catch (MaxStepsExceededException $e) {
    // Agent executor exceeded the configured step limit
    // $e->limit — the max steps value
    // $e->steps — the steps completed before exceeding
}
```

### Unsupported Feature

```php
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;

try {
    $response = Atlas::image(Provider::Anthropic, 'claude-sonnet-4-5-20250929')
        ->prompt('Draw a cat')
        ->asImage();
} catch (UnsupportedFeatureException $e) {
    // Provider does not support this modality
}
```

### Tool Not Found

```php
use Atlasphp\Atlas\Exceptions\ToolNotFoundException;

try {
    $response = Atlas::agent('assistant')
        ->tools(['nonexistent_tool'])
        ->message('Hi')
        ->asText();
} catch (ToolNotFoundException $e) {
    // "Tool [nonexistent_tool] is not registered."
}
```

### Provider Not Found

```php
use Atlasphp\Atlas\Exceptions\ProviderNotFoundException;

try {
    $response = Atlas::text('invalid-provider', 'some-model')
        ->message('Hello')
        ->asText();
} catch (ProviderNotFoundException $e) {
    // Provider key not registered in config
}
```

## Tool Error Handling

In tool handlers, return error strings instead of throwing exceptions. This lets the AI retry or adjust its approach:

```php
public function handle(array $args, array $context): mixed
{
    try {
        $order = Order::findOrFail($args['order_id']);
        return $order->toArray();
    } catch (ModelNotFoundException $e) {
        return 'Order not found: '.$args['order_id'];
    }
}
```

When a tool returns an error string, the AI can try a different approach. Throwing exceptions stops the entire agent loop and fires the `AgentToolCallFailed` event.

## Rate Limit Handling

The `RateLimitException` includes retry information from the provider:

```php
use Atlasphp\Atlas\Exceptions\RateLimitException;

try {
    $response = Atlas::text(Provider::OpenAI, 'gpt-4o')
        ->message('Hello')
        ->asText();
} catch (RateLimitException $e) {
    $seconds = $e->retryAfter;

    if ($seconds) {
        // Queue a retry after the specified delay
        dispatch(fn () => $this->retry($input))
            ->delay(now()->addSeconds($seconds));
    }
}
```

## Provider Fallback

Build resilience by falling back across providers:

```php
class ResilientService
{
    public function respond(string $input): TextResponse
    {
        $providers = [
            [Provider::OpenAI, 'gpt-4o-mini'],
            [Provider::Anthropic, 'claude-sonnet-4-5-20250929'],
        ];

        foreach ($providers as [$provider, $model]) {
            try {
                return Atlas::text($provider, $model)
                    ->message($input)
                    ->asText();
            } catch (RateLimitException $e) {
                Log::warning("Rate limited on {$provider->value}, trying next");
                continue;
            } catch (ProviderException $e) {
                Log::warning("Provider error on {$provider->value}: {$e->getMessage()}");
                continue;
            }
        }

        throw new \RuntimeException('All providers failed');
    }
}
```

## Laravel Exception Handler

Integrate Atlas exceptions into your application's exception handler for consistent error responses:

```php
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Exceptions\RateLimitException;
use Atlasphp\Atlas\Exceptions\AuthenticationException;

// In bootstrap/app.php or your exception handler
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (RateLimitException $e) {
        return response()->json([
            'error' => 'Service temporarily unavailable. Please retry.',
            'retry_after' => $e->retryAfter,
        ], 429);
    });

    $exceptions->render(function (AuthenticationException $e) {
        Log::critical('AI provider authentication failed', [
            'message' => $e->getMessage(),
        ]);

        return response()->json([
            'error' => 'AI service configuration error.',
        ], 500);
    });

    $exceptions->render(function (AtlasException $e) {
        return response()->json([
            'error' => 'An error occurred processing your request.',
        ], 500);
    });
})
```

## Next Steps

- [Testing](/advanced/testing) — Test error scenarios with Atlas::fake()
- [Pipelines](/features/middleware) — Add error handling in pipeline hooks
