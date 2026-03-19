# Middleware

Atlas provides a middleware system that wraps every meaningful touchpoint in the execution lifecycle. A single middleware in global config catches every provider call in the entire application.

## Layers

| Layer | When it runs | Context |
|-------|-------------|---------|
| **Provider** | Every HTTP call to an AI provider | `ProviderContext` |
| **Step** | Each executor round trip (agent loop) | `StepContext` |
| **Tool** | Each tool execution (agent loop) | `ToolContext` |
| **Agent** | Entire agent execution (Phase 7) | `AgentContext` |

## Writing Middleware

Every middleware follows the same `handle($context, Closure $next)` pattern:

```php
class TrackUsage
{
    public function handle(ProviderContext $context, Closure $next): mixed
    {
        $start = microtime(true);

        $response = $next($context);

        Log::info('Provider call', [
            'provider' => $context->provider,
            'model'    => $context->model,
            'method'   => $context->method,
            'time_ms'  => round((microtime(true) - $start) * 1000),
        ]);

        return $response;
    }
}
```

Middleware can be:
- A class with a `handle()` method (most common)
- A class string resolved from the container
- A `Closure`

## Registration

### Global Config (catches everything)

```php
// config/atlas.php
'middleware' => [
    'provider' => [
        App\Atlas\Middleware\TrackUsage::class,
    ],
    'step' => [],
    'tool' => [],
    'agent' => [],
],
```

### Per-Request (fluent API)

```php
Atlas::text(Provider::OpenAI, 'gpt-4o')
    ->withMiddleware([new RetryOnRateLimit])
    ->message('Hello')
    ->asText();
```

`withMiddleware()` is available on all modality builders: text, image, audio, video, embed, moderate.

## Stacking Order

Global config middleware runs outermost, request-level middleware runs innermost:

```
Global middleware → Request middleware → Handler (HTTP call)
```

## Context Objects

### ProviderContext

Available on every provider call (text, stream, image, audio, video, embed, moderate).

| Property | Type | Description |
|----------|------|-------------|
| `provider` | `string` | Driver name (e.g. `'openai'`) |
| `model` | `string` | Model identifier |
| `method` | `string` | Modality method (`'text'`, `'stream'`, `'image'`, etc.) |
| `request` | `mixed` | The request object (mutable) |
| `meta` | `array` | Cross-middleware data passing |

### StepContext

Available on each executor round trip.

| Property | Type | Description |
|----------|------|-------------|
| `stepNumber` | `int` | Current step (1-indexed) |
| `request` | `TextRequest` | The text request (mutable) |
| `accumulatedUsage` | `Usage` | Token usage from prior completed steps |
| `previousSteps` | `array<Step>` | Steps completed before this one |
| `meta` | `array` | Cross-middleware data passing |

### ToolContext

Available on each tool execution.

| Property | Type | Description |
|----------|------|-------------|
| `toolCall` | `ToolCall` | The tool call (name, arguments, id) |
| `meta` | `array` | Cross-middleware data passing |

## Examples

### Cost Guard (Step Middleware)

```php
class CostGuard
{
    public function __construct(protected int $maxTokens = 100000) {}

    public function handle(StepContext $context, Closure $next): mixed
    {
        if ($context->accumulatedUsage->totalTokens() > $this->maxTokens) {
            throw new \RuntimeException('Token budget exceeded.');
        }

        return $next($context);
    }
}
```

### Retry on Rate Limit (Provider Middleware)

```php
class RetryOnRateLimit
{
    public function __construct(protected int $maxRetries = 3) {}

    public function handle(ProviderContext $context, Closure $next): mixed
    {
        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                return $next($context);
            } catch (RateLimitException $e) {
                if ($attempt === $this->maxRetries) {
                    throw $e;
                }
                sleep($e->retryAfter ?? 1);
            }
        }
    }
}
```

### Tool Audit (Tool Middleware)

```php
class AuditToolCalls
{
    public function handle(ToolContext $context, Closure $next): mixed
    {
        Log::info('Tool calling', ['tool' => $context->toolCall->name]);

        $result = $next($context);

        Log::info('Tool returned', ['tool' => $context->toolCall->name]);

        return $result;
    }
}
```

## Custom Providers

Custom drivers must accept `?MiddlewareStack $middlewareStack = null` in their constructor and pass it to the parent to participate in provider middleware:

```php
class MyDriver extends Driver
{
    public function __construct(
        ProviderConfig $config,
        HttpClient $http,
        ?MiddlewareStack $middlewareStack = null,
    ) {
        parent::__construct($config, $http, $middlewareStack);
    }
}
```

When registering a custom driver, pass the MiddlewareStack from the container:

```php
$registry->register('my-provider', function (Application $app, array $config) {
    return new MyDriver(
        config: ProviderConfig::fromArray($config),
        http: $app->make(HttpClient::class),
        middlewareStack: $app->make(MiddlewareStack::class),
    );
});
```
