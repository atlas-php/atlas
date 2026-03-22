# Middleware

Atlas uses a four-layer middleware system for observability, authentication, rate limiting, and custom logic. Middleware follows the standard Laravel pipeline pattern -- implement a `handle` method that receives a context object and a `$next` closure.

## Four Layers

<div class="full-width-table">

| Layer | Wraps | Context Object | Config Key |
|-------|-------|----------------|------------|
| Agent | Entire agent execution | `AgentContext` | `atlas.middleware.agent` |
| Step | Each round trip in the tool loop | `StepContext` | `atlas.middleware.step` |
| Tool | Each individual tool execution | `ToolContext` | `atlas.middleware.tool` |
| Provider | Every HTTP call to a provider | `ProviderContext` | `atlas.middleware.provider` |

</div>

```
Agent middleware
  └─ Step middleware (per round trip)
       ├─ Tool middleware (per tool call)
       └─ Provider middleware (per HTTP request)
```

## Writing Middleware

Middleware receives a typed context object and a `$next` closure. Call `$next($context)` to continue the pipeline:

```php
use Atlasphp\Atlas\Middleware\AgentContext;
use Closure;

class LogAgentExecution
{
    public function handle(AgentContext $context, Closure $next): mixed
    {
        Log::info('Agent starting', [
            'agent' => $context->agent?->key(),
            'meta' => $context->meta,
        ]);

        $result = $next($context);

        Log::info('Agent completed', [
            'agent' => $context->agent?->key(),
        ]);

        return $result;
    }
}
```

## Context Objects

Each middleware layer receives a dedicated context object with the data relevant to that scope.

### AgentContext

Wraps the entire agent execution from first message to final result.

```php
use Atlasphp\Atlas\Middleware\AgentContext;
```

| Property | Type | Description |
|----------|------|-------------|
| `request` | `TextRequest` | The pending text request (mutable) |
| `agent` | `?Agent` | The agent instance, `null` for direct calls |
| `messages` | `array` | Conversation message history |
| `tools` | `array` | Resolved tool instances |
| `meta` | `array` | Metadata from `withMeta()` |

### StepContext

Wraps each round trip in the executor's tool call loop.

```php
use Atlasphp\Atlas\Middleware\StepContext;
```

| Property | Type | Description |
|----------|------|-------------|
| `stepNumber` | `int` | Current step number (1-based) |
| `request` | `TextRequest` | The request for this step (mutable) |
| `accumulatedUsage` | `Usage` | Token usage from all prior completed steps |
| `previousSteps` | `array` | Array of completed `Step` objects |
| `meta` | `array` | Metadata from the executor |
| `agentKey` | `?string` | Key of the executing agent |

### ToolContext

Wraps each individual tool execution.

```php
use Atlasphp\Atlas\Middleware\ToolContext;
```

| Property | Type | Description |
|----------|------|-------------|
| `toolCall` | `ToolCall` | The tool call with name and arguments |
| `meta` | `array` | Metadata from the execution context |
| `stepNumber` | `?int` | Step in which this tool was called |
| `agentKey` | `?string` | Key of the executing agent |

### ProviderContext

Wraps every HTTP call to an AI provider, across all modalities (text, image, audio, embeddings, etc.).

```php
use Atlasphp\Atlas\Middleware\ProviderContext;
```

| Property | Type | Description |
|----------|------|-------------|
| `provider` | `string` | Provider name (e.g. `'openai'`, `'anthropic'`) |
| `model` | `string` | Model name (e.g. `'gpt-4o'`) |
| `method` | `string` | Modality method (e.g. `'text'`, `'stream'`, `'image'`) |
| `request` | `mixed` | The provider request payload (mutable) |
| `meta` | `array` | Metadata from the request object |

## Registration

### Global via Config

Register middleware globally in `config/atlas.php`. These run on every request at their respective layer:

```php
// config/atlas.php
'middleware' => [
    'agent' => [
        LogAgentExecution::class,
    ],
    'step' => [],
    'tool' => [],
    'provider' => [
        RateLimitProvider::class,
    ],
],
```

### Per-Request

Attach middleware to a single request using `withMiddleware()`:

```php
Atlas::text('openai', 'gpt-4o')
    ->withMiddleware([CacheResponse::class])
    ->message('Hello')
    ->asText();
```

Per-request middleware runs after global middleware for that layer.

## Built-in Middleware (Persistence)

When persistence is enabled, Atlas auto-registers middleware at each layer to track executions, steps, tool calls, and provider calls. You do not need to register these manually.

<div class="full-width-table">

| Middleware | Layer | Description |
|------------|-------|-------------|
| `TrackExecution` | Agent | Creates and tracks execution records through pending, processing, completed, and failed states |
| `TrackStep` | Step | Records each round trip with response text, reasoning, token usage, and finish reason |
| `TrackToolCall` | Tool | Records each tool call with its result or error and wall-clock duration |
| `TrackProviderCall` | Provider | Tracks standalone provider calls and stores file-producing response assets |
| `PersistConversation` | Agent | Loads conversation history before execution and stores user/assistant messages after |
| `WireMemory` | Agent | Wires memory tools, variables, and context onto agents that use the `HasMemory` trait |

</div>

## Examples

### Logging Middleware

```php
use Atlasphp\Atlas\Middleware\AgentContext;
use Closure;
use Illuminate\Support\Facades\Log;

class AuditAgentExecution
{
    public function handle(AgentContext $context, Closure $next): mixed
    {
        $result = $next($context);

        Log::info('Agent executed', [
            'agent' => $context->agent?->key(),
            'user_id' => $context->meta['user_id'] ?? null,
        ]);

        return $result;
    }
}
```

### Rate Limiting

```php
use Atlasphp\Atlas\Middleware\ProviderContext;
use Closure;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitProvider
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
use Atlasphp\Atlas\Middleware\StepContext;
use Closure;

class TrackCost
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
                'prompt_tokens' => $usage->promptTokens,
                'completion_tokens' => $usage->completionTokens,
            ]);
        }

        return $result;
    }
}
```

### Tool Authorization

```php
use Atlasphp\Atlas\Middleware\ToolContext;
use Closure;

class AuthorizeToolCall
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

## API Reference

### Middleware Layers

| Layer | Config Key | Context Object | Wraps |
|-------|-----------|----------------|-------|
| Agent | `atlas.middleware.agent` | `AgentContext` | Entire agent execution |
| Step | `atlas.middleware.step` | `StepContext` | Each round trip in tool loop |
| Tool | `atlas.middleware.tool` | `ToolContext` | Each tool execution |
| Provider | `atlas.middleware.provider` | `ProviderContext` | Every HTTP call to provider |

### Context Properties

| AgentContext | Type | Mutable |
|-------------|------|---------|
| `request` | `TextRequest` | Yes |
| `agent` | `?Agent` | No |
| `messages` | `array` | Yes |
| `tools` | `array` | Yes |
| `meta` | `array` | Yes |

| StepContext | Type | Mutable |
|------------|------|---------|
| `stepNumber` | `int` | No |
| `request` | `TextRequest` | Yes |
| `accumulatedUsage` | `Usage` | No |
| `previousSteps` | `array` | No |
| `meta` | `array` | Yes |
| `agentKey` | `?string` | No |

| ToolContext | Type | Mutable |
|-----------|------|---------|
| `toolCall` | `ToolCall` | No |
| `meta` | `array` | Yes |
| `stepNumber` | `?int` | No |
| `agentKey` | `?string` | No |

| ProviderContext | Type | Mutable |
|----------------|------|---------|
| `provider` | `string` | No |
| `model` | `string` | No |
| `method` | `string` | No |
| `request` | `mixed` | Yes |
| `meta` | `array` | Yes |

## Next Steps

- [Agents](/core-concepts/agents) — Agent configuration
- [Tools](/core-concepts/tools) — Tool definitions and parameters
