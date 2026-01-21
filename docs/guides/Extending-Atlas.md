# Extending Atlas

## Goal

Extend Atlas functionality using pipeline middleware and extensions.

## Prerequisites

- Atlas installed and configured
- Understanding of Laravel service providers

## Pipeline System

Atlas uses a pipeline middleware system for extensibility. Pipelines run before/after key operations.

### Available Pipelines

| Pipeline | Trigger |
|----------|---------|
| `agent.before_execute` | Before agent execution starts |
| `agent.after_execute` | After agent execution completes |
| `agent.system_prompt.before_build` | Before building system prompt |
| `agent.system_prompt.after_build` | After building system prompt |
| `tool.before_execute` | Before tool execution |
| `tool.after_execute` | After tool execution |

## Steps

### 1. Create a Pipeline Handler

Pipeline handlers are invocable classes:

```php
<?php

namespace App\Pipelines;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Closure;

class LogAgentExecution
{
    public function __invoke(
        array $data,
        Closure $next,
    ): mixed {
        // Data contains: agent, input, context
        $agent = $data['agent'];
        $input = $data['input'];
        $context = $data['context'];

        // Before execution
        Log::info('Agent execution started', [
            'agent' => $agent->key(),
            'input_length' => strlen($input),
        ]);

        // Continue pipeline
        $result = $next($data);

        // After execution (result is AgentResponse)
        Log::info('Agent execution completed', [
            'agent' => $agent->key(),
            'tokens' => $result->totalTokens(),
        ]);

        return $result;
    }
}
```

### 2. Register the Handler

In a service provider:

```php
use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;
use App\Pipelines\LogAgentExecution;

public function boot(): void
{
    $registry = app(PipelineRegistry::class);

    $registry->register(
        'agent.after_execute',
        LogAgentExecution::class,
        priority: 100, // Higher = runs first
    );
}
```

### 3. System Prompt Middleware

Modify system prompts dynamically:

```php
class AddContextToSystemPrompt
{
    public function __invoke(array $data, Closure $next): mixed
    {
        // Data contains: agent, context, prompt (after build)
        $prompt = $data['prompt'];
        $context = $data['context'];

        // Add dynamic content
        $timestamp = now()->toDateTimeString();
        $data['prompt'] = $prompt . "\n\nCurrent time: {$timestamp}";

        return $next($data);
    }
}

// Register on after_build to modify the built prompt
$registry->register('agent.system_prompt.after_build', AddContextToSystemPrompt::class);
```

### 4. Tool Execution Middleware

Add logging, validation, or rate limiting to tools:

```php
class RateLimitTools
{
    public function __invoke(array $data, Closure $next): mixed
    {
        $tool = $data['tool'];
        $context = $data['context'];

        // Check rate limit
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
```

## Priority

Handlers run in priority order (highest first):

```php
$registry->register('agent.after_execute', HighPriorityHandler::class, priority: 200);
$registry->register('agent.after_execute', LowPriorityHandler::class, priority: 50);
// HighPriorityHandler runs before LowPriorityHandler
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

## Extension Registries

For more complex extensions, use extension registries:

### Agent Extensions

Register custom resolvers or decorators:

```php
use Atlasphp\Atlas\Agents\Services\AgentExtensionRegistry;

$extensions = app(AgentExtensionRegistry::class);

$extensions->register('custom_decorator', function (AgentContract $agent) {
    return new DecoratedAgent($agent);
});
```

### Tool Extensions

Register tool transformers:

```php
use Atlasphp\Atlas\Tools\Services\ToolExtensionRegistry;

$extensions = app(ToolExtensionRegistry::class);

$extensions->register('validator', function (ToolContract $tool) {
    return new ValidatedTool($tool);
});
```

## Complete Example: Audit Logging

```php
<?php

namespace App\Providers;

use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;
use Illuminate\Support\ServiceProvider;

class AtlasExtensionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $registry = app(PipelineRegistry::class);

        // Log all agent executions
        $registry->register('agent.after_execute', function (array $data, $next) {
            $result = $next($data);

            AuditLog::create([
                'type' => 'agent_execution',
                'agent' => $data['agent']->key(),
                'user_id' => $data['context']?->getMeta('user_id'),
                'tokens' => $result->totalTokens(),
                'created_at' => now(),
            ]);

            return $result;
        }, priority: 10);

        // Log all tool calls
        $registry->register('tool.after_execute', function (array $data, $next) {
            $result = $next($data);

            AuditLog::create([
                'type' => 'tool_execution',
                'tool' => $data['tool']->name(),
                'user_id' => $data['context']?->getMeta('user_id'),
                'success' => $result->succeeded(),
                'created_at' => now(),
            ]);

            return $result;
        }, priority: 10);
    }
}
```

## Common Issues

### Handler Not Running

If your handler doesn't execute:
1. Verify the pipeline name is spelled correctly
2. Check that registration happens in `boot()`, not `register()`
3. Ensure the pipeline is active (`setActive(true)`)

### Wrong Execution Order

If handlers run in unexpected order:
1. Check priority values (higher = earlier)
2. Use explicit priorities instead of defaults

## Next Steps

- [SPEC-Foundation](../spec/SPEC-Foundation.md) - Pipeline system internals
- [Creating Agents](./Creating-Agents.md) - Agent configuration
