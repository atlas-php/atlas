# Pipelines

Pipelines provide a middleware system for extending Atlas and Prism without modifying core code. Add logging, authentication, metrics, and more through composable handlers.

::: tip Extending Prism
Atlas pipelines are designed to extend Prism's capabilities. Since Atlas wraps Prism, pipelines give you hooks into all Prism operations—text generation, embeddings, images, audio, and moderation—allowing you to add observability, validation, and custom logic around any AI operation.
:::

## How Pipelines Work

Pipelines intercept key operations and allow you to:
- Execute code before/after operations
- Modify data flowing through the system
- Short-circuit execution with custom responses
- Add cross-cutting concerns like logging

## Available Pipelines

### Agent Pipelines

<div class="full-width-table">

| Pipeline | Trigger |
|----------|---------|
| `agent.before_execute` | Before agent execution starts |
| `agent.after_execute` | After agent execution completes |
| `agent.system_prompt.before_build` | Before building system prompt |
| `agent.system_prompt.after_build` | After building system prompt |
| `agent.on_error` | When agent execution fails |

</div>

### Tool Pipelines

<div class="full-width-table">

| Pipeline | Trigger |
|----------|---------|
| `tool.before_execute` | Before tool execution |
| `tool.after_execute` | After tool execution |
| `tool.on_error` | When tool execution fails |

</div>

### Text Pipelines

<div class="full-width-table">

| Pipeline | Trigger |
|----------|---------|
| `text.before_text` | Before text generation |
| `text.after_text` | After text generation |
| `text.before_stream` | Before streaming starts |
| `text.after_stream` | After streaming completes |

</div>

### Structured Pipelines

<div class="full-width-table">

| Pipeline | Trigger |
|----------|---------|
| `structured.before_structured` | Before structured output generation |
| `structured.after_structured` | After structured output generation |

</div>

### Embeddings Pipelines

<div class="full-width-table">

| Pipeline | Trigger |
|----------|---------|
| `embeddings.before_embeddings` | Before generating embeddings |
| `embeddings.after_embeddings` | After generating embeddings |

</div>

### Image Pipelines

<div class="full-width-table">

| Pipeline | Trigger |
|----------|---------|
| `image.before_generate` | Before generating an image |
| `image.after_generate` | After generating an image |

</div>

### Audio Pipelines

<div class="full-width-table">

| Pipeline | Trigger |
|----------|---------|
| `audio.before_audio` | Before text-to-speech conversion |
| `audio.after_audio` | After text-to-speech conversion |
| `audio.before_text` | Before speech-to-text transcription |
| `audio.after_text` | After speech-to-text transcription |

</div>

### Moderation Pipelines

<div class="full-width-table">

| Pipeline | Trigger |
|----------|---------|
| `moderation.before_moderation` | Before content moderation |
| `moderation.after_moderation` | After content moderation |

</div>

## Creating a Handler

Pipeline handlers must implement `PipelineContract`:

```php
use Atlasphp\Atlas\Contracts\PipelineContract;
use Closure;
use Illuminate\Support\Facades\Log;

class LogAgentExecution implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        // Before execution
        Log::info('Agent execution started', [
            'agent' => $data['agent']->key(),
            'input_length' => strlen($data['input']),
        ]);

        // Continue pipeline
        $result = $next($data);

        // After execution (for agent.after_execute, $result contains 'response')
        Log::info('Agent execution completed', [
            'agent' => $data['agent']->key(),
        ]);

        return $result;
    }
}
```

## Registering Handlers

Register handlers in a service provider:

```php
use Atlasphp\Atlas\Pipelines\PipelineRegistry;

public function boot(): void
{
    $registry = app(PipelineRegistry::class);

    $registry->register(
        'agent.after_execute',
        LogAgentExecution::class,
        priority: 100,
    );
}
```

### Using Instances

You can also register handler instances directly:

```php
$registry->register('agent.after_execute', new AuditLogHandler(), priority: 50);
```

### Defining Pipelines

Optionally define pipelines with metadata:

```php
$registry->define('agent.before_execute', 'Runs before agent execution', active: true);
```

### Querying the Registry

```php
// Check if a pipeline has handlers
$registry->has('agent.before_execute');

// Get all registered pipeline names
$registry->pipelines();

// Get all pipeline definitions
$registry->definitions();

// Check if a pipeline is active
$registry->active('agent.before_execute');
```

## Priority

Handlers run in priority order (highest first):

```php
$registry->register('agent.after_execute', HighPriorityHandler::class, priority: 200);
$registry->register('agent.after_execute', LowPriorityHandler::class, priority: 50);
// HighPriorityHandler runs before LowPriorityHandler
```

## Pipeline Data

Each pipeline receives specific data:

### agent.before_execute

```php
[
    'agent' => AgentContract,
    'input' => string,
    'context' => ExecutionContext,
]
```

### agent.after_execute

```php
[
    'agent' => AgentContract,
    'input' => string,
    'context' => ExecutionContext,
    'response' => PrismResponse|StructuredResponse,
    'system_prompt' => ?string,
]
```

The `ExecutionContext` provides access to:
- `messages` — Conversation history (may include attachments per message)
- `variables` — System prompt variables
- `metadata` — Execution metadata (user_id, session_id, etc.)
- `prismMedia` — Prism media objects for current input (images, documents, audio, video)

### agent.system_prompt.before_build

```php
[
    'agent' => AgentContract,
    'context' => ExecutionContext,
    'variables' => array,  // Merged global and context variables
]
```

### agent.system_prompt.after_build

```php
[
    'agent' => AgentContract,
    'context' => ExecutionContext,
    'prompt' => string,  // The built prompt
]
```

### tool.before_execute / tool.after_execute

```php
[
    'tool' => ToolContract,
    'args' => array,
    'context' => ToolContext,
]
```

After execute also includes:
- `result` — The ToolResult object

### agent.on_error

```php
[
    'agent' => AgentContract,
    'input' => string,
    'context' => ExecutionContext,
    'system_prompt' => ?string,
    'exception' => Throwable,
]
```

### tool.on_error

```php
[
    'tool' => ToolContract,
    'args' => array,
    'context' => ToolContext,
    'exception' => Throwable,
]
```

### Prism Proxy Pipelines

All Prism proxy pipelines (text, structured, embeddings, image, audio, moderation) receive:

```php
[
    'pipeline' => string,      // The module name (e.g., 'text', 'image')
    'metadata' => array,       // Custom metadata passed via withMetadata()
    'request' => object,       // The Prism pending request object
]
```

After pipelines also include:
- `response` — The Prism response object

## Example: Audit Logging

```php
use Atlasphp\Atlas\Contracts\PipelineContract;
use Closure;

class AuditMiddleware implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        $result = $next($data);

        AuditLog::create([
            'type' => 'agent_execution',
            'agent' => $data['agent']->key(),
            'user_id' => $data['context']?->getMeta('user_id'),
            'created_at' => now(),
        ]);

        return $result;
    }
}
```

## Example: Dynamic System Prompt

```php
use Atlasphp\Atlas\Contracts\PipelineContract;
use Closure;

class AddTimestampToPrompt implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        // Modify the built prompt
        $timestamp = now()->toDateTimeString();
        $data['prompt'] .= "\n\nCurrent time: {$timestamp}";

        return $next($data);
    }
}

$registry->register('agent.system_prompt.after_build', AddTimestampToPrompt::class);
```

## Example: Tool Rate Limiting

```php
use Atlasphp\Atlas\Contracts\PipelineContract;
use Atlasphp\Atlas\Tools\Support\ToolResult;
use Closure;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitTools implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        $userId = $data['context']->getMeta('user_id');
        $toolName = $data['tool']->name();
        $key = "tool:{$userId}:{$toolName}";

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 10)) {
            $data['result'] = ToolResult::error('Rate limit exceeded. Try again later.');
            return $data;
        }

        RateLimiter::hit($key, decaySeconds: 60);

        return $next($data);
    }
}

$registry->register('tool.before_execute', RateLimitTools::class);
```

## Example: Authentication Check

```php
use Atlasphp\Atlas\Contracts\PipelineContract;
use Closure;

class RequireAuthentication implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        $userId = $data['context']?->getMeta('user_id');

        if (! $userId) {
            throw new UnauthorizedException('User must be authenticated');
        }

        return $next($data);
    }
}

$registry->register('agent.before_execute', RequireAuthentication::class, priority: 1000);
```

## Example: Attachment Auditing

Log multimodal attachments (images, documents, audio, video) for compliance and monitoring:

```php
use Atlasphp\Atlas\Contracts\PipelineContract;
use Closure;

class AuditAttachments implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        $context = $data['context'];

        // Log current input attachments (Prism media objects)
        if ($context?->hasAttachments()) {
            foreach ($context->prismMedia as $media) {
                AuditLog::create([
                    'type' => 'attachment_sent',
                    'media_type' => get_class($media),
                    'user_id' => $context->getMeta('user_id'),
                    'agent' => $data['agent']->key(),
                    'timestamp' => now(),
                ]);
            }
        }

        return $next($data);
    }
}

$registry->register('agent.before_execute', AuditAttachments::class, priority: 500);
```

See [Chat Attachments](/capabilities/chat#attachments) for complete attachment documentation.

## Example: Token Usage Logging

Log token usage for direct Prism text generation:

```php
use Atlasphp\Atlas\Contracts\PipelineContract;
use Closure;
use Illuminate\Support\Facades\Log;

class LogTokenUsage implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        $result = $next($data);

        $response = $result['response'];
        $metadata = $data['metadata'];

        Log::channel('usage')->info('Text generation completed', [
            'user_id' => $metadata['user_id'] ?? null,
            'prompt_tokens' => $response->usage->promptTokens,
            'completion_tokens' => $response->usage->completionTokens,
            'total_tokens' => $response->usage->promptTokens + $response->usage->completionTokens,
        ]);

        return $result;
    }
}

$registry->register('text.after_text', LogTokenUsage::class);
```

Usage with metadata:

```php
$response = Atlas::text()
    ->using('openai', 'gpt-4o')
    ->withMetadata(['user_id' => auth()->id()])
    ->withPrompt('Explain quantum computing')
    ->asText();
```

## Example: Caching Embeddings

Cache embeddings to reduce API calls. Use metadata to pass a cache key:

```php
use Atlasphp\Atlas\Contracts\PipelineContract;
use Closure;
use Illuminate\Support\Facades\Cache;

class CacheEmbeddings implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        $cacheKey = $data['metadata']['cache_key'] ?? null;

        if ($cacheKey && Cache::has($cacheKey)) {
            $data['response'] = Cache::get($cacheKey);
            return $data;
        }

        $result = $next($data);

        if ($cacheKey) {
            Cache::put($cacheKey, $result['response'], now()->addDay());
        }

        return $result;
    }
}

$registry->register('embeddings.before_embeddings', CacheEmbeddings::class);
```

Usage:

```php
$cacheKey = 'embeddings:' . md5($text);

$response = Atlas::embeddings()
    ->using('openai', 'text-embedding-3-small')
    ->withMetadata(['cache_key' => $cacheKey])
    ->fromInput($text)
    ->asEmbeddings();
```

## Disabling Pipelines

Temporarily disable a pipeline:

```php
$registry->setActive('agent.before_execute', false);

// Pipeline won't run
$response = Atlas::agent('agent')->chat('input');

// Re-enable
$registry->setActive('agent.before_execute', true);
```

## Next Steps

- [Error Handling](/advanced/error-handling) — Handle pipeline errors
