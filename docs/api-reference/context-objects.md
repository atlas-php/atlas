# Context Objects

Atlas uses context objects to pass data through the execution pipeline.

## ExecutionContext

Immutable context for agent execution. Carries conversation history, variables, and metadata.

### Constructor

```php
use Atlasphp\Atlas\Agents\Support\ExecutionContext;

$context = new ExecutionContext(
    messages: [
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi there!'],
    ],
    variables: [
        'user_name' => 'John',
        'account_type' => 'premium',
    ],
    metadata: [
        'session_id' => 'abc123',
        'user_id' => 456,
    ],
    providerOverride: 'anthropic',  // Optional: override agent's default provider
    modelOverride: 'claude-3-opus', // Optional: override agent's default model
);
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `messages` | `array` | Conversation history |
| `variables` | `array` | System prompt variables |
| `metadata` | `array` | Execution metadata |
| `providerOverride` | `?string` | Override the agent's configured provider |
| `modelOverride` | `?string` | Override the agent's configured model |

### Accessor Methods

**getVariable()** — Get a variable value:

```php
public function getVariable(string $key, mixed $default = null): mixed
```

**getMeta()** — Get a metadata value:

```php
public function getMeta(string $key, mixed $default = null): mixed
```

**hasMessages()** — Check if context has messages:

```php
public function hasMessages(): bool
```

**hasVariable()** — Check if a specific variable exists:

```php
public function hasVariable(string $key): bool
```

**hasMeta()** — Check if a specific metadata key exists:

```php
public function hasMeta(string $key): bool
```

**hasProviderOverride()** — Check if provider override is set:

```php
public function hasProviderOverride(): bool
```

**hasModelOverride()** — Check if model override is set:

```php
public function hasModelOverride(): bool
```

### Immutable Update Methods

**withVariables()** — Create new context with replaced variables:

```php
public function withVariables(array $variables): self
```

**mergeVariables()** — Create new context with merged variables:

```php
public function mergeVariables(array $variables): self
```

**withMetadata()** — Create new context with replaced metadata:

```php
public function withMetadata(array $metadata): self
```

**mergeMetadata()** — Create new context with merged metadata:

```php
public function mergeMetadata(array $metadata): self
```

**withMessages()** — Create new context with replaced messages:

```php
public function withMessages(array $messages): self
```

**withProviderOverride()** — Create new context with provider override:

```php
public function withProviderOverride(?string $provider): self
```

**withModelOverride()** — Create new context with model override:

```php
public function withModelOverride(?string $model): self
```

### Provider/Model Override Example

Override the agent's default provider and model at runtime:

```php
// Create context with overrides
$context = new ExecutionContext(
    messages: $previousMessages,
    variables: ['user_name' => 'John'],
);

// Override provider for a specific request
$context = $context->withProviderOverride('anthropic');
$context = $context->withModelOverride('claude-3-opus');

// Check if overrides are set
if ($context->hasProviderOverride()) {
    Log::info('Using custom provider', ['provider' => $context->providerOverride]);
}

// Clear an override by passing null
$context = $context->withProviderOverride(null);
```

### Example Usage

```php
// Create initial context
$context = new ExecutionContext(
    messages: $previousMessages,
    variables: ['user_name' => 'John'],
    metadata: ['user_id' => 123],
);

// Immutable updates
$context = $context->mergeVariables(['company' => 'Acme']);
$context = $context->mergeMetadata(['session_id' => 'xyz']);

// Access values
$userName = $context->getVariable('user_name', 'Guest');
$userId = $context->getMeta('user_id');

// Check existence
if ($context->hasMessages()) {
    $messages = $context->messages;
}
```

## ToolContext

Immutable context for tool execution. Provides metadata access to tools.

### Constructor

```php
use Atlasphp\Atlas\Tools\Support\ToolContext;

$context = new ToolContext([
    'user_id' => 123,
    'tenant_id' => 'acme',
    'session_id' => 'abc123',
]);
```

### Methods

**getMeta()** — Get a metadata value:

```php
public function getMeta(string $key, mixed $default = null): mixed
```

**hasMeta()** — Check if metadata exists:

```php
public function hasMeta(string $key): bool
```

**withMetadata()** — Create new context with replaced metadata:

```php
public function withMetadata(array $metadata): ToolContext
```

**mergeMetadata()** — Create new context with merged metadata:

```php
public function mergeMetadata(array $metadata): ToolContext
```

### Example Usage in Tools

```php
public function handle(array $arguments, ToolContext $context): ToolResult
{
    // Get required metadata
    $userId = $context->getMeta('user_id');
    if (! $userId) {
        return ToolResult::error('User not authenticated');
    }

    // Get optional metadata with default
    $tenantId = $context->getMeta('tenant_id', 'default');

    // Check metadata existence
    if ($context->hasMeta('session_id')) {
        // Log session info
    }

    // Use metadata for authorization
    $order = Order::where('user_id', $userId)
        ->where('id', $arguments['order_id'])
        ->first();

    return $order
        ? ToolResult::json($order)
        : ToolResult::error('Order not found');
}
```

## PendingAgentRequest

Immutable fluent builder for constructing agent chat requests with messages, variables, metadata, and retry configuration.

### Methods

**withMessages()** — Set conversation history:

```php
public function withMessages(array $messages): self
```

**withVariables()** — Set variables for system prompt interpolation:

```php
public function withVariables(array $variables): self
```

**withMetadata()** — Set metadata for pipeline middleware and tools:

```php
public function withMetadata(array $metadata): self
```

**withRetry()** — Configure retry behavior:

```php
public function withRetry(
    array|int $times,
    Closure|int $sleepMilliseconds = 0,
    ?callable $when = null,
    bool $throw = true,
): self
```

**chat()** — Execute chat with configured context:

```php
public function chat(
    string $input,
    ?Schema $schema = null,
    bool $stream = false,
): AgentResponse|StreamResponse
```

### Example Usage

```php
$response = Atlas::agent('support-agent')
    ->withMessages($messages)
    ->withVariables([
        'user_name' => $user->name,
        'company' => 'Acme',
    ])
    ->withMetadata([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
    ])
    ->chat('Hello');
```

## PendingEmbeddingRequest

Immutable fluent builder for embedding operations with metadata and retry support.

### Methods

**withMetadata()** — Set metadata for pipeline middleware:

```php
public function withMetadata(array $metadata): self
```

**withRetry()** — Configure retry behavior:

```php
public function withRetry(
    array|int $times,
    Closure|int $sleepMilliseconds = 0,
    ?callable $when = null,
    bool $throw = true,
): self
```

**generate()** — Generate embedding for a single text:

```php
public function generate(string $text): array<int, float>
```

**generateBatch()** — Generate embeddings for multiple texts:

```php
public function generateBatch(array $texts): array<int, array<int, float>>
```

### Example Usage

```php
$embedding = Atlas::embeddings()
    ->withMetadata(['user_id' => 123])
    ->withRetry(3, 1000)
    ->generate('Hello world');
```

## Immutability Pattern

All context objects are immutable. Methods that appear to modify the object return new instances:

```php
$context1 = new ExecutionContext(
    messages: [],
    variables: ['name' => 'John'],
    metadata: [],
);

$context2 = $context1->withVariables(['name' => 'Jane']);

// $context1->getVariable('name') returns 'John'
// $context2->getVariable('name') returns 'Jane'
```

This ensures:
- Thread safety
- Predictable behavior
- No side effects
- Easy testing

## Context Flow

Context flows through the execution pipeline:

```
User Request
    ↓
PendingAgentRequest/AtlasManager (builds ExecutionContext)
    ↓
ExecutionContext (passed to agent executor)
    ↓
SystemPromptBuilder (uses variables)
    ↓
ToolBuilder (creates ToolContext from metadata)
    ↓
ToolContext (passed to tool handlers)
    ↓
AgentResponse (returned to user)
```

## Passing Context in Tools

When the agent calls a tool, the `ToolContext` is created from the `ExecutionContext` metadata:

```php
// In your application code
$response = Atlas::agent('agent')
    ->withMessages($messages)
    ->withMetadata([
        'user_id' => 123,        // Available in ToolContext
        'tenant_id' => 'acme',   // Available in ToolContext
    ])
    ->chat('Look up my order');

// In your tool
public function handle(array $args, ToolContext $context): ToolResult
{
    $userId = $context->getMeta('user_id');     // 123
    $tenantId = $context->getMeta('tenant_id'); // 'acme'
    // ...
}
```

## Next Steps

- [Response Objects](/api-reference/response-objects) — AgentResponse, ToolResult
- [Pipelines](/core-concepts/pipelines) — Context in pipeline middleware
- [Tools](/core-concepts/tools) — Using context in tools
