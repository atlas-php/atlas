# Error Handling

Strategies for handling errors in Atlas-powered applications.

## Exception Hierarchy

Atlas maps all provider HTTP errors to typed exceptions:

```
AtlasException (base — catch all Atlas errors)
├── AuthenticationException        (401 — invalid API key)
├── AuthorizationException         (403 — model access denied)
├── RateLimitException             (429 — rate limit with retry-after)
├── ProviderException              (all other HTTP errors)
├── UnsupportedFeatureException    (provider doesn't support this modality)
├── ProviderNotFoundException      (provider key not registered)
├── AgentNotFoundException         (agent key not registered)
├── ToolNotFoundException          (tool name not in registry)
└── MaxStepsExceededException      (agent tool loop exceeded limit)
```

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

```php
use Atlasphp\Atlas\Exceptions\AgentNotFoundException;
use Atlasphp\Atlas\Exceptions\MaxStepsExceededException;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;

try {
    $response = Atlas::agent('unknown-agent')->message('Hi')->asText();
} catch (AgentNotFoundException $e) {
    // "Agent [unknown-agent] is not registered."
}

try {
    $response = Atlas::agent('assistant')->message('Hi')->asText();
} catch (MaxStepsExceededException $e) {
    // Agent tool loop exceeded the configured max steps
    // "Agent executor exceeded the maximum of 10 steps..."
}
```

## Credential Validation

Check if provider credentials are valid before making requests:

```php
$isValid = Atlas::provider('openai')->validate();

if (! $isValid) {
    // API key is invalid or provider is unreachable
}
```

`validate()` returns `true` on success and `false` on failure — it never throws.

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

When a tool returns an error string, the AI can try a different approach. Throwing exceptions stops the entire agent loop.

## Provider Fallback

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

## Next Steps

- [Testing](/advanced/testing) — Test error scenarios with Atlas::fake()
- [Middleware](/core-concepts/middleware) — Add error handling middleware
