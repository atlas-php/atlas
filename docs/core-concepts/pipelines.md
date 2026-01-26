# Pipelines

Pipelines provide a middleware system for extending Atlas and Prism without modifying core code. Add logging, authentication, metrics, and more through composable handlers.

::: tip Extending Prism
Atlas pipelines are designed to extend Prism's capabilities. Since Atlas wraps Prism, pipelines give you hooks into all Prism operations (text generation, embeddings, images, audio, and moderation) allowing you to add observability, validation, and custom logic around any AI operation.
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
| `agent.context.validate` | After before_execute, before building the request (validate/modify context) |
| `agent.tools.merged` | After all tools are merged, before sending to Prism (filter/audit/inject tools) |
| `agent.after_execute` | After agent execution completes |
| `agent.stream.after` | After streaming completes (success or error) |
| `agent.system_prompt.before_build` | Before building system prompt |
| `agent.system_prompt.after_build` | After building system prompt |
| `agent.on_error` | When agent execution fails (supports recovery responses) |

</div>

### Tool Pipelines

<div class="full-width-table">

| Pipeline | Trigger |
|----------|---------|
| `tool.before_resolve` | Before tools are built for an agent (filter/modify tool list) |
| `tool.after_resolve` | After tools are built, before execution (audit/modify Prism tools) |
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

### Conditional Execution

Register handlers that only run when a condition is met:

```php
$registry->registerWhen(
    'agent.before_execute',
    PremiumOnlyHandler::class,
    fn(array $data) => $data['context']->getMeta('tier') === 'premium',
    priority: 100,
);
```

The condition callback receives the pipeline data and should return `true` if the handler should run:

```php
// Only run for specific agents
$registry->registerWhen(
    'agent.after_execute',
    SpecialAgentLogger::class,
    fn(array $data) => $data['agent']->key() === 'special-agent',
);

// Only run for authenticated users
$registry->registerWhen(
    'tool.before_execute',
    AuditToolUsage::class,
    fn(array $data) => $data['context']->getMeta('user_id') !== null,
);

// Only run for large inputs
$registry->registerWhen(
    'text.before_text',
    LogLargeRequests::class,
    fn(array $data) => strlen($data['metadata']['prompt'] ?? '') > 1000,
);
```

Conditional handlers can be mixed with regular handlers and respect priority ordering.

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

### agent.context.validate

Runs after `agent.before_execute` but before building the system prompt or request. Useful for validating or modifying the execution context.

```php
[
    'agent' => AgentContract,
    'input' => string,
    'context' => ExecutionContext,
]
```

You can modify the context by replacing it in the data array:

```php
class InjectMetadataHandler implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        // Modify context with injected metadata
        $data['context'] = new ExecutionContext(
            messages: $data['context']->messages,
            variables: $data['context']->variables,
            metadata: array_merge($data['context']->metadata, [
                'validated_at' => now()->toIso8601String(),
            ]),
        );

        return $next($data);
    }
}
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

### agent.stream.after

Fires when streaming completes (whether successful or with an error). Useful for analytics, logging stream completion, and cleanup.

```php
[
    'agent' => AgentContract,
    'input' => string,
    'context' => ExecutionContext,
    'system_prompt' => ?string,
    'events' => array,     // All stream events collected
    'error' => ?Throwable, // Exception if streaming failed, null on success
]
```

The `ExecutionContext` provides access to:
- `messages` — Conversation history (may include attachments per message)
- `variables` — System prompt variables
- `metadata` — Execution metadata (user_id, session_id, etc.)
- `prismMedia` — Prism media objects for current input (images, documents, audio, video)
- `tools` — Runtime Atlas tool class names (from `withTools()`)
- `mcpTools` — Runtime MCP Tool instances (from `withMcpTools()`)

See [ExecutionContext Reference](#executioncontext-reference) for the complete API.

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

### agent.tools.merged

Fires after all tools from all sources are merged, before sending to Prism. Provides complete visibility into the tool set and allows filtering, auditing, or injecting tools.

```php
[
    'agent' => AgentContract,
    'context' => ExecutionContext,
    'tool_context' => ToolContext,
    'agent_tools' => array,      // Native Atlas tools (from tools() + withTools())
    'agent_mcp_tools' => array,  // MCP tools (from mcpTools() + withMcpTools())
    'tools' => array,            // Final merged array (modify this to change tools sent to Prism)
]
```

Modify `tools` to filter, reorder, or inject tools:

```php
class FilterToolsByPermission implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        $userId = $data['context']->getMeta('user_id');
        $allowedToolNames = $this->getUserAllowedTools($userId);

        // Filter tools to only include allowed tools
        $data['tools'] = array_filter(
            $data['tools'],
            fn ($tool) => in_array($tool->name(), $allowedToolNames)
        );

        return $next($data);
    }
}
```

Use individual arrays to audit tool types:

```php
class AuditToolSources implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        Log::info('Tools for agent execution', [
            'agent' => $data['agent']->key(),
            'native_tools' => count($data['agent_tools']),
            'mcp_tools' => count($data['agent_mcp_tools']),
            'total_tools' => count($data['tools']),
        ]);

        return $next($data);
    }
}
```

### tool.before_resolve

Fires before tools are built for an agent. Allows filtering or modifying which tools are available.

```php
[
    'agent' => AgentContract,
    'tools' => array,  // Array of tool class names
    'context' => ToolContext,
]
```

Modify `tools` to filter which tools are available:

```php
class FilterToolsForUser implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        $allowedTools = $this->getUserAllowedTools($data['context']->getMeta('user_id'));

        $data['tools'] = array_filter(
            $data['tools'],
            fn ($tool) => in_array($tool, $allowedTools)
        );

        return $next($data);
    }
}
```

### tool.after_resolve

Fires after tools are built into Prism tool objects. Allows auditing or modifying the final tool list.

```php
[
    'agent' => AgentContract,
    'tools' => array,    // Original tool class names
    'prism_tools' => array,     // Built Prism Tool objects
    'context' => ToolContext,
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

**Recovery Support:** You can return a recovery response instead of letting the exception propagate:

```php
class ErrorRecoveryHandler implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        // Optionally provide a recovery response
        if ($this->shouldRecover($data['exception'])) {
            $data['recovery'] = $this->createFallbackResponse();
        }

        return $next($data);
    }

    protected function createFallbackResponse(): PrismResponse
    {
        return new PrismResponse(
            steps: collect([]),
            text: 'I apologize, but I encountered an issue. Please try again.',
            finishReason: FinishReason::Stop,
            toolCalls: [],
            toolResults: [],
            usage: new Usage(0, 0),
            meta: new Meta('fallback', 'fallback'),
            messages: collect([]),
            additionalContent: [],
        );
    }
}
```

When a `recovery` key is set with a valid `PrismResponse` or `StructuredResponse`, the exception will not be thrown and the recovery response will be returned instead.

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

## ExecutionContext Reference

The `ExecutionContext` object is available in most agent pipelines and provides access to the full request configuration:

```php
// Properties
$context->messages;          // array - Conversation history (array format)
$context->prismMessages;     // array - Conversation history (Prism message objects)
$context->variables;         // array - System prompt variables
$context->metadata;          // array - Execution metadata
$context->providerOverride;  // ?string - Provider override
$context->modelOverride;     // ?string - Model override
$context->prismCalls;        // array - Captured Prism method calls
$context->prismMedia;        // array - Prism media objects (Image, Document, Audio, Video)
$context->tools;             // array - Runtime Atlas tool class names (from withTools())
$context->mcpTools;          // array - Runtime MCP Tool instances (from withMcpTools())

// Helper methods
$context->hasMessages(): bool;         // Has conversation history (either format)
$context->hasPrismMessages(): bool;    // Has Prism message objects specifically
$context->hasAttachments(): bool;      // Has media attachments for current input
$context->hasTools(): bool;            // Has runtime Atlas tools
$context->hasMcpTools(): bool;         // Has runtime MCP tools
$context->hasProviderOverride(): bool; // Has provider override
$context->hasModelOverride(): bool;    // Has model override
$context->hasPrismCalls(): bool;       // Has captured Prism calls
$context->hasSchemaCall(): bool;       // Has withSchema() in Prism calls

// Value accessors
$context->getVariable(string $key, mixed $default = null): mixed;
$context->getMeta(string $key, mixed $default = null): mixed;
$context->hasVariable(string $key): bool;
$context->hasMeta(string $key): bool;
$context->getSchemaFromCalls(): ?Schema;
$context->getPrismCallsWithoutSchema(): array;
```

### Example: Checking for Tools

```php
class ToolAwareHandler implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        $context = $data['context'];

        // Check if any runtime tools are configured
        if ($context->hasTools()) {
            Log::info('Runtime tools added', [
                'tools' => $context->tools,
            ]);
        }

        // Check if MCP tools are configured
        if ($context->hasMcpTools()) {
            Log::info('MCP tools added', [
                'count' => count($context->mcpTools),
            ]);
        }

        return $next($data);
    }
}
```

## ToolContext Reference

The `ToolContext` object is available in tool pipelines and provides access to execution metadata:

```php
// Methods
$toolContext->getMeta(string $key, mixed $default = null): mixed;
$toolContext->hasMeta(string $key): bool;
```

The tool context receives metadata from `ExecutionContext->metadata`, allowing tools to access request-level information like user IDs, session data, or feature flags.

## API Reference

```php
// PipelineRegistry methods
$registry = app(PipelineRegistry::class);

$registry->define(string $pipeline, string $description, bool $active = true): void;
$registry->register(string $pipeline, string|PipelineContract $handler, int $priority = 0): void;
$registry->registerWhen(string $pipeline, string|PipelineContract $handler, callable $condition, int $priority = 0): void;
$registry->has(string $pipeline): bool;
$registry->pipelines(): array;
$registry->definitions(): array;
$registry->active(string $pipeline): bool;
$registry->setActive(string $pipeline, bool $active): void;

// PipelineContract interface
interface PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed;
}
```

## Next Steps

- [Error Handling](/advanced/error-handling) — Handle pipeline errors
