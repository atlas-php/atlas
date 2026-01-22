# Pipelines

Pipelines provide a middleware system for extending Atlas without modifying core code. Add logging, authentication, metrics, and more through composable handlers.

## How Pipelines Work

Pipelines intercept key operations and allow you to:
- Execute code before/after operations
- Modify data flowing through the system
- Short-circuit execution with custom responses
- Add cross-cutting concerns like logging

## Available Pipelines

### Agent Pipelines

| Pipeline | Trigger |
|----------|---------|
| `agent.before_execute` | Before agent execution starts |
| `agent.after_execute` | After agent execution completes |
| `agent.system_prompt.before_build` | Before building system prompt |
| `agent.system_prompt.after_build` | After building system prompt |
| `agent.on_error` | When agent execution fails |

### Streaming Pipelines

| Pipeline | Trigger |
|----------|---------|
| `stream.on_event` | For each streaming event received |
| `stream.after_complete` | After streaming completes |

### Tool Pipelines

| Pipeline | Trigger |
|----------|---------|
| `tool.before_execute` | Before tool execution |
| `tool.after_execute` | After tool execution |
| `tool.on_error` | When tool execution fails |

### Embedding Pipelines

| Pipeline | Trigger |
|----------|---------|
| `embedding.before_generate` | Before generating a single embedding |
| `embedding.after_generate` | After generating a single embedding |
| `embedding.before_generate_batch` | Before generating batch embeddings |
| `embedding.after_generate_batch` | After generating batch embeddings |
| `embedding.on_error` | When embedding generation fails |

### Image Pipelines

| Pipeline | Trigger |
|----------|---------|
| `image.before_generate` | Before generating an image |
| `image.after_generate` | After generating an image |
| `image.on_error` | When image generation fails |

### Speech Pipelines

| Pipeline | Trigger |
|----------|---------|
| `speech.before_speak` | Before text-to-speech conversion |
| `speech.after_speak` | After text-to-speech conversion |
| `speech.before_transcribe` | Before speech-to-text transcription |
| `speech.after_transcribe` | After speech-to-text transcription |
| `speech.on_error` | When speech operation fails |

## Creating a Handler

Pipeline handlers are invocable classes or closures:

```php
use Closure;

class LogAgentExecution
{
    public function __invoke(array $data, Closure $next): mixed
    {
        // Before execution
        Log::info('Agent execution started', [
            'agent' => $data['agent']->key(),
            'input_length' => strlen($data['input']),
        ]);

        // Continue pipeline
        $result = $next($data);

        // After execution
        Log::info('Agent execution completed', [
            'agent' => $data['agent']->key(),
            'tokens' => $result->totalTokens(),
        ]);

        return $result;
    }
}
```

## Registering Handlers

Register handlers in a service provider:

```php
use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;

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

### Using Closures

```php
$registry->register('agent.after_execute', function (array $data, $next) {
    $result = $next($data);

    AuditLog::create([
        'agent' => $data['agent']->key(),
        'tokens' => $result->totalTokens(),
    ]);

    return $result;
}, priority: 50);
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

### agent.before_execute / agent.after_execute

```php
[
    'agent' => AgentContract,
    'input' => string,
    'context' => ?ExecutionContext,
]
```

### agent.system_prompt.before_build

```php
[
    'agent' => AgentContract,
    'context' => ?ExecutionContext,
]
```

### agent.system_prompt.after_build

```php
[
    'agent' => AgentContract,
    'context' => ?ExecutionContext,
    'prompt' => string,  // The built prompt
]
```

### stream.on_event

```php
[
    'event' => StreamEvent,
    'agent' => AgentContract,
    'context' => ?ExecutionContext,
]
```

### stream.after_complete

```php
[
    'agent' => AgentContract,
    'input' => string,
    'context' => ?ExecutionContext,
    'system_prompt' => string,
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

### agent.on_error / tool.on_error

```php
[
    'agent' => AgentContract,  // or 'tool' => ToolContract
    'error' => Throwable,
    'context' => ?ExecutionContext,  // or ToolContext for tools
]
```

### embedding.before_generate / embedding.after_generate

```php
[
    'text' => string,
    'provider' => string,
    'model' => string,
]
```

### embedding.before_generate_batch / embedding.after_generate_batch

```php
[
    'texts' => array<string>,
    'provider' => string,
    'model' => string,
]
```

### image.before_generate / image.after_generate

```php
[
    'prompt' => string,
    'provider' => string,
    'model' => string,
    'options' => array,
]
```

### speech.before_speak / speech.after_speak

```php
[
    'text' => string,
    'provider' => string,
    'voice' => string,
    'options' => array,
]
```

### speech.before_transcribe / speech.after_transcribe

```php
[
    'audio' => string,  // file path or content
    'provider' => string,
    'options' => array,
]
```

## Examples

### Audit Logging

```php
class AuditMiddleware
{
    public function __invoke(array $data, Closure $next): mixed
    {
        $result = $next($data);

        AuditLog::create([
            'type' => 'agent_execution',
            'agent' => $data['agent']->key(),
            'user_id' => $data['context']?->getMeta('user_id'),
            'tokens' => $result->totalTokens(),
            'created_at' => now(),
        ]);

        return $result;
    }
}
```

### Dynamic System Prompt

```php
class AddTimestampToPrompt
{
    public function __invoke(array $data, Closure $next): mixed
    {
        // Modify the built prompt
        $timestamp = now()->toDateTimeString();
        $data['prompt'] .= "\n\nCurrent time: {$timestamp}";

        return $next($data);
    }
}

$registry->register('agent.system_prompt.after_build', AddTimestampToPrompt::class);
```

### Tool Rate Limiting

```php
class RateLimitTools
{
    public function __invoke(array $data, Closure $next): mixed
    {
        $tool = $data['tool'];
        $context = $data['context'];

        $userId = $context->getMeta('user_id');
        $key = "tool_calls:{$userId}:{$tool->name()}";

        if (Cache::get($key, 0) >= 10) {
            return ToolResult::error('Rate limit exceeded');
        }

        Cache::increment($key);
        Cache::put($key, Cache::get($key), 60);

        return $next($data);
    }
}

$registry->register('tool.before_execute', RateLimitTools::class);
```

### Authentication Check

```php
class RequireAuthentication
{
    public function __invoke(array $data, Closure $next): mixed
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

## Disabling Pipelines

Temporarily disable a pipeline:

```php
$registry->setActive('agent.before_execute', false);

// Pipeline won't run
$response = Atlas::chat('agent', 'input');

// Re-enable
$registry->setActive('agent.before_execute', true);
```

## Complete Service Provider

```php
<?php

namespace App\Providers;

use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;
use Illuminate\Support\ServiceProvider;

class AtlasPipelineServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $registry = app(PipelineRegistry::class);

        // Log all agent executions
        $registry->register('agent.after_execute', function (array $data, $next) {
            $result = $next($data);

            Log::channel('atlas')->info('Agent executed', [
                'agent' => $data['agent']->key(),
                'tokens' => $result->totalTokens(),
            ]);

            return $result;
        }, priority: 10);

        // Log all tool calls
        $registry->register('tool.after_execute', function (array $data, $next) {
            $result = $next($data);

            Log::channel('atlas')->info('Tool executed', [
                'tool' => $data['tool']->name(),
                'success' => $result->succeeded(),
            ]);

            return $result;
        }, priority: 10);

        // Add timestamp to prompts
        $registry->register('agent.system_prompt.after_build', function (array $data, $next) {
            $data['prompt'] .= "\n\nCurrent time: " . now()->toDateTimeString();
            return $next($data);
        }, priority: 100);
    }
}
```

## Troubleshooting

### Handler Not Running

1. Verify the pipeline name is spelled correctly
2. Check that registration happens in `boot()`, not `register()`
3. Ensure the pipeline is active: `$registry->active('pipeline.name')`

### Wrong Execution Order

1. Check priority values (higher = earlier)
2. Use explicit priorities instead of defaults

## Next Steps

- [Extending Atlas](/guides/extending-atlas) — Complete extension guide
- [Error Handling](/advanced/error-handling) — Handle pipeline errors
- [Performance](/advanced/performance) — Optimize pipeline performance
