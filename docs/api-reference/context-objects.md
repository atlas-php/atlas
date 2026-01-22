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
);
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `messages` | `array` | Conversation history |
| `variables` | `array` | System prompt variables |
| `metadata` | `array` | Execution metadata |

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

**hasVariables()** — Check if context has variables:

```php
public function hasVariables(): bool
```

**hasMetadata()** — Check if context has metadata:

```php
public function hasMetadata(): bool
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

## MessageContextBuilder

Immutable builder for constructing execution context via the facade.

### Methods

**withVariables()** — Add variables:

```php
public function withVariables(array $variables): self
```

**withMetadata()** — Add metadata:

```php
public function withMetadata(array $metadata): self
```

**chat()** — Execute chat with context:

```php
public function chat(
    string|AgentContract $agent,
    string $input,
    ?Schema $schema = null,
): AgentResponse
```

**Accessors:**

```php
public function getMessages(): array
public function getVariables(): array
public function getMetadata(): array
```

### Example Usage

```php
$response = Atlas::forMessages($messages)
    ->withVariables([
        'user_name' => $user->name,
        'company' => 'Acme',
    ])
    ->withMetadata([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
    ])
    ->chat('support-agent', 'Hello');
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
MessageContextBuilder (builds ExecutionContext)
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
$response = Atlas::forMessages($messages)
    ->withMetadata([
        'user_id' => 123,        // Available in ToolContext
        'tenant_id' => 'acme',   // Available in ToolContext
    ])
    ->chat('agent', 'Look up my order');

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
