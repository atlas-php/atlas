# Middleware

Atlas uses an interface-based middleware system for observability, authentication, rate limiting, and custom logic. Each middleware class declares its execution scope by implementing a marker interface — Atlas routes it to the right pipeline automatically.

## Scope Interfaces

Each interface targets a different execution layer. Implement one per middleware class.

<div class="full-width-table">

| Interface | Description |
|-----------|-------------|
| `AgentMiddleware` | Wraps the entire agent execution. Receives `AgentContext` with the request, agent, messages, tools, and meta. |
| `StepMiddleware` | Wraps each round trip in the executor's tool call loop. Receives `StepContext` with the step number, request, accumulated usage, and previous steps. |
| `ToolMiddleware` | Wraps each individual tool execution. Receives `ToolContext` with the tool call, meta, step number, and agent key. |
| `ProviderMiddleware` | Wraps every HTTP call to a provider across all modalities. Receives `ProviderContext` with the provider, model, method, request, and meta. |
| `VoiceHttpMiddleware` | Standard Laravel HTTP middleware for voice webhook routes (tool execution, transcript storage, session close). Receives a Laravel `Request`. |

</div>

```
AgentMiddleware
  └─ StepMiddleware (per round trip)
       ├─ ToolMiddleware (per tool call)
       └─ ProviderMiddleware (per HTTP request)
```

Each middleware class implements exactly one scope interface and defines a single `handle()` method. If you need middleware at two layers, create two separate classes.

## Modality Filtering

Provider middleware can target specific modalities by implementing a sub-interface instead of `ProviderMiddleware` directly:

<div class="full-width-table">

| Interface | Dispatch Methods |
|-----------|-----------------|
| `TextMiddleware` | `text`, `stream`, `structured` |
| `ImageMiddleware` | `image`, `imageToText` |
| `AudioMiddleware` | `audio`, `audioToText` |
| `VideoMiddleware` | `video`, `videoToText` |
| `VoiceMiddleware` | `voice` |
| `EmbedMiddleware` | `embed`, `moderate`, `rerank` |
| `ProviderMiddleware` (direct) | All of the above |

</div>

All modality interfaces extend `ProviderMiddleware`. Implement multiple modality interfaces to target several modalities with a single class.

## Registration

Register middleware as a flat array in `config/atlas.php`. Atlas inspects each class's interfaces to route it:

```php
// config/atlas.php
'middleware' => [
    App\Atlas\Middleware\LogAgentExecution::class,
    App\Atlas\Middleware\WatermarkImages::class,
    App\Atlas\Middleware\RateLimitProvider::class,
],
```

When persistence is enabled, Atlas auto-registers its tracking middleware. You do not need to add them manually.

### Per-Request

Attach additional provider middleware to a single request using `withMiddleware()`:

```php
Atlas::text('openai', 'gpt-4o')
    ->withMiddleware([CacheResponse::class])
    ->message('Hello')
    ->asText();
```

Per-request middleware runs after global middleware.

### Inspecting Active Middleware

Use the Artisan command to see all registered middleware grouped by layer:

```bash
php artisan atlas:middleware
```

## Writing Middleware

Implement the appropriate interface and define a `handle()` method that receives a typed context object and a `$next` closure:

### Agent Middleware

```php
use Atlasphp\Atlas\Middleware\Contracts\AgentMiddleware;
use Atlasphp\Atlas\Middleware\AgentContext;
use Closure;

class LogAgentExecution implements AgentMiddleware
{
    public function handle(AgentContext $context, Closure $next): mixed
    {
        Log::info('Agent starting', [
            'agent' => $context->agent?->key(),
        ]);

        $result = $next($context);

        Log::info('Agent completed');

        return $result;
    }
}
```

### Modality-Specific Provider Middleware

```php
use Atlasphp\Atlas\Middleware\Contracts\ImageMiddleware;
use Atlasphp\Atlas\Middleware\ProviderContext;
use Closure;

class WatermarkImages implements ImageMiddleware
{
    public function handle(ProviderContext $context, Closure $next): mixed
    {
        $result = $next($context);

        // Only runs on image and imageToText calls
        return $this->addWatermark($result);
    }
}
```

### Multi-Modality Middleware

Implement multiple modality interfaces to target several modalities with one class:

```php
use Atlasphp\Atlas\Middleware\Contracts\ImageMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\AudioMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\VideoMiddleware;
use Atlasphp\Atlas\Middleware\ProviderContext;
use Closure;

class LogMediaGeneration implements ImageMiddleware, AudioMiddleware, VideoMiddleware
{
    public function handle(ProviderContext $context, Closure $next): mixed
    {
        Log::info("Media generation: {$context->method}");

        return $next($context);
    }
}
```

### Stopping Execution (Auth)

Short-circuit the pipeline by throwing an exception or returning early without calling `$next()`:

```php
use Atlasphp\Atlas\Middleware\Contracts\ToolMiddleware;
use Atlasphp\Atlas\Middleware\ToolContext;
use Closure;

class AuthorizeToolCall implements ToolMiddleware
{
    public function handle(ToolContext $context, Closure $next): mixed
    {
        $userId = $context->meta['user_id'] ?? null;
        $toolName = $context->toolCall->name;

        if (! $this->userCanUseTool($userId, $toolName)) {
            throw new \RuntimeException("User {$userId} is not authorized to use tool {$toolName}");
        }

        return $next($context);
    }

    private function userCanUseTool(?int $userId, string $toolName): bool
    {
        // Your authorization logic
        return true;
    }
}
```

### Modifying Context

Context objects have mutable properties that middleware can modify before passing downstream:

```php
use Atlasphp\Atlas\Middleware\Contracts\AgentMiddleware;
use Atlasphp\Atlas\Middleware\AgentContext;
use Closure;

class InjectMeta implements AgentMiddleware
{
    public function handle(AgentContext $context, Closure $next): mixed
    {
        $context->meta['injected_at'] = now()->toIso8601String();

        return $next($context);
    }
}
```

### Rate Limiting

```php
use Atlasphp\Atlas\Middleware\Contracts\ProviderMiddleware;
use Atlasphp\Atlas\Middleware\ProviderContext;
use Closure;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitProvider implements ProviderMiddleware
{
    public function handle(ProviderContext $context, Closure $next): mixed
    {
        $key = "atlas:{$context->provider}:{$context->model}";

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 60)) {
            throw new \RuntimeException('Provider rate limit exceeded');
        }

        RateLimiter::hit($key, decaySeconds: 60);

        return $next($context);
    }
}
```

### Cost Tracking

```php
use Atlasphp\Atlas\Middleware\Contracts\StepMiddleware;
use Atlasphp\Atlas\Middleware\StepContext;
use Closure;

class TrackCost implements StepMiddleware
{
    public function handle(StepContext $context, Closure $next): mixed
    {
        $result = $next($context);

        $usage = $context->accumulatedUsage;
        $userId = $context->meta['user_id'] ?? null;

        if ($userId) {
            UsageLog::create([
                'user_id' => $userId,
                'agent' => $context->agentKey,
                'step' => $context->stepNumber,
                'input_tokens' => $usage->inputTokens,
                'output_tokens' => $usage->outputTokens,
            ]);
        }

        return $result;
    }
}
```

### Voice HTTP Middleware

Standard Laravel HTTP middleware applied to the voice webhook routes (tool execution, transcript storage, session close):

```php
use Atlasphp\Atlas\Middleware\Contracts\VoiceHttpMiddleware;
use Illuminate\Http\Request;
use Closure;

class VerifyVoiceToken implements VoiceHttpMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! $this->isValidToken($request->bearerToken())) {
            abort(401);
        }

        return $next($request);
    }
}
```

## Context Objects

Each middleware layer receives a dedicated context object. Mutable properties can be modified by middleware before reaching the next handler in the pipeline.

### AgentContext

Wraps the entire agent execution from first message to final result.

```php
use Atlasphp\Atlas\Middleware\AgentContext;
```

| Property | Type | Mutable |
|----------|------|---------|
| `request` | `TextRequest` | Yes |
| `agent` | `?Agent` | No |
| `messages` | `array` | Yes |
| `tools` | `array` | Yes |
| `meta` | `array` | Yes |

### StepContext

Wraps each round trip in the executor's tool call loop.

```php
use Atlasphp\Atlas\Middleware\StepContext;
```

| Property | Type | Mutable |
|----------|------|---------|
| `stepNumber` | `int` | No |
| `request` | `TextRequest` | Yes |
| `accumulatedUsage` | `Usage` | No |
| `previousSteps` | `array` | No |
| `meta` | `array` | Yes |
| `agentKey` | `?string` | No |

### ToolContext

Wraps each individual tool execution.

```php
use Atlasphp\Atlas\Middleware\ToolContext;
```

| Property | Type | Mutable |
|----------|------|---------|
| `toolCall` | `ToolCall` | No |
| `meta` | `array` | Yes |
| `stepNumber` | `?int` | No |
| `agentKey` | `?string` | No |

### ProviderContext

Wraps every HTTP call to an AI provider. The `method` property indicates which modality dispatch triggered the call.

```php
use Atlasphp\Atlas\Middleware\ProviderContext;
```

| Property | Type | Mutable |
|----------|------|---------|
| `provider` | `string` | No |
| `model` | `string` | No |
| `method` | `string` | No |
| `request` | `mixed` | Yes |
| `meta` | `array` | Yes |

## Built-in Middleware (Persistence)

When persistence is enabled, Atlas auto-registers tracking middleware at each layer. You do not need to add these manually.

<div class="full-width-table">

| Middleware | Interface | Description |
|------------|-----------|-------------|
| `PersistConversation` | `AgentMiddleware` | Loads conversation history before execution and stores messages after |
| `TrackExecution` | `AgentMiddleware` | Creates and tracks execution records through pending, processing, completed, and failed states |
| `TrackStep` | `StepMiddleware` | Records each round trip with response text, reasoning, token usage, and finish reason |
| `TrackToolCall` | `ToolMiddleware` | Records each tool call with its result or error and wall-clock duration |
| `TrackProviderCall` | `ProviderMiddleware` | Tracks standalone provider calls and stores file-producing response assets |

</div>

## Next Steps

- [Agents](/features/agents) — Agent configuration
- [Tools](/features/tools) — Tool definitions and parameters
