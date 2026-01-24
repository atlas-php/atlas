# ToolContract

The `ToolContract` interface defines the required methods for all tool implementations.

## Interface Definition

```php
namespace Atlasphp\Atlas\Tools\Contracts;

interface ToolContract
{
    public function name(): string;
    public function description(): string;
    public function parameters(): array;
    public function handle(array $args, ToolContext $context): ToolResult;
}
```

## Required Methods

### name()

Returns the unique tool identifier.

```php
public function name(): string
```

**Returns:** Snake_case identifier (e.g., `lookup_order`)

### description()

Returns the tool description for the AI.

```php
public function description(): string
```

**Returns:** Clear description of what the tool does and when to use it

### parameters()

Returns the tool parameter definitions.

```php
public function parameters(): array
```

**Returns:** Array of `ToolParameter` instances

### handle()

Executes the tool logic.

```php
public function handle(array $arguments, ToolContext $context): ToolResult
```

**Parameters:**
- `$arguments` — Parameters passed by the AI
- `$context` — Execution context with metadata

**Returns:** `ToolResult` instance

## ToolParameter Class

Defines tool parameters with type information.

### Factory Methods

**string()** — String parameter:

```php
ToolParameter::string(
    string $name,
    string $description,
    bool $required = false,
    ?string $default = null,
)
```

**integer()** — Integer parameter:

```php
ToolParameter::integer(
    string $name,
    string $description,
    bool $required = false,
    ?int $default = null,
)
```

**number()** — Float parameter:

```php
ToolParameter::number(
    string $name,
    string $description,
    bool $required = false,
    ?float $default = null,
)
```

**boolean()** — Boolean parameter:

```php
ToolParameter::boolean(
    string $name,
    string $description,
    bool $required = false,
    ?bool $default = null,
)
```

**enum()** — Enum parameter:

```php
ToolParameter::enum(
    string $name,
    string $description,
    array $values,
    bool $required = false,
    ?string $default = null,
)
```

**array()** — Array parameter:

```php
ToolParameter::array(
    string $name,
    string $description,
    array|string $items = 'string',
)
```

**object()** — Object parameter:

```php
ToolParameter::object(
    string $name,
    string $description,
    array $properties,
    bool $required = false,
)
```

## ToolResult Class

Represents the result of tool execution.

### Factory Methods

**text()** — Success with text:

```php
ToolResult::text(string $text): ToolResult
```

**json()** — Success with JSON data:

```php
ToolResult::json(mixed $data): ToolResult
```

**error()** — Error result:

```php
ToolResult::error(string $message): ToolResult
```

### Instance Methods

**succeeded()** — Check if successful:

```php
$result->succeeded(): bool
```

**failed()** — Check if failed:

```php
$result->failed(): bool
```

**toArray()** — Convert to array:

```php
$result->toArray(): array
// ['text' => '...', 'is_error' => false]
```

## ToolContext Class

Provides execution context to tools.

### Methods

**getMeta()** — Get metadata value:

```php
$context->getMeta(string $key, mixed $default = null): mixed
```

**hasMeta()** — Check if metadata exists:

```php
$context->hasMeta(string $key): bool
```

**withMetadata()** — Create new context with metadata:

```php
$context->withMetadata(array $metadata): ToolContext
```

**mergeMetadata()** — Create new context with merged metadata:

```php
$context->mergeMetadata(array $metadata): ToolContext
```

## ToolRegistryContract

Interface for the tool registry:

```php
interface ToolRegistryContract
{
    public function register(string $toolClass, bool $override = false): void;
    public function registerInstance(ToolContract $tool, bool $override = false): void;
    public function get(string $name): ToolContract;
    public function has(string $name): bool;
    public function all(): array;
    public function only(array $names): array;
    public function names(): array;
    public function unregister(string $name): bool;
    public function count(): int;
    public function clear(): void;
}
```

### Registry Methods

**register()** — Register a tool class:

```php
$registry->register(LookupOrderTool::class);
```

**registerInstance()** — Register a tool instance:

```php
$registry->registerInstance(new LookupOrderTool());
```

**get()** — Retrieve a tool by name:

```php
$tool = $registry->get('lookup_order');
```

**has()** — Check if tool exists:

```php
if ($registry->has('lookup_order')) {
    // ...
}
```

**all()** — Get all registered tools:

```php
$tools = $registry->all();
// ['lookup_order' => LookupOrderTool, 'search' => SearchTool]
```

**only()** — Get specific tools:

```php
$tools = $registry->only(['lookup_order', 'search']);
```

**names()** — Get all registered tool names:

```php
$names = $registry->names();
// ['lookup_order', 'search', 'send_email']
```

**unregister()** — Remove a tool from the registry:

```php
$removed = $registry->unregister('old_tool');
// Returns true if removed, false if not found
```

**count()** — Get the number of registered tools:

```php
$count = $registry->count();
// 3
```

**clear()** — Remove all tools from the registry:

```php
$registry->clear();
```

### Dynamic Tool Management

The registry methods support dynamic tool management for scenarios like per-tenant tools or runtime configuration:

```php
use Atlasphp\Atlas\Contracts\Tools\Contracts\ToolRegistryContract;

class TenantToolManager
{
    public function __construct(
        private ToolRegistryContract $registry,
    ) {}

    public function configureForTenant(Tenant $tenant): void
    {
        // Clear existing tools
        $this->registry->clear();

        // Register tenant-specific tools
        foreach ($tenant->enabledTools as $toolClass) {
            $this->registry->register($toolClass);
        }

        // Log available tools
        Log::info('Configured tools for tenant', [
            'tenant' => $tenant->id,
            'tool_count' => $this->registry->count(),
            'tools' => $this->registry->names(),
        ]);
    }

    public function disableTool(string $toolName): bool
    {
        return $this->registry->unregister($toolName);
    }
}

## ToolDefinition Base Class

Abstract base class for tool implementations:

```php
use Atlasphp\Atlas\Tools\ToolDefinition;

class MyTool extends ToolDefinition
{
    public function name(): string
    {
        return 'my_tool';
    }

    public function description(): string
    {
        return 'Does something useful';
    }

    public function parameters(): array
    {
        return [
            ToolParameter::string('input', 'The input', required: true),
        ];
    }

    public function handle(array $arguments, ToolContext $context): ToolResult
    {
        return ToolResult::text('Done!');
    }
}
```

## Complete Example

```php
<?php

namespace App\Tools;

use App\Models\Order;use Atlasphp\Atlas\Contracts\Tools\Support\ToolContext;use Atlasphp\Atlas\Contracts\Tools\Support\ToolParameter;use Atlasphp\Atlas\Contracts\Tools\Support\ToolResult;use Atlasphp\Atlas\Contracts\Tools\ToolDefinition;

class LookupOrderTool extends ToolDefinition
{
    public function name(): string
    {
        return 'lookup_order';
    }

    public function description(): string
    {
        return 'Look up order details by order ID. Use when the customer asks about a specific order.';
    }

    public function parameters(): array
    {
        return [
            ToolParameter::string(
                'order_id',
                'The order ID to look up',
                required: true,
            ),
            ToolParameter::boolean(
                'include_items',
                'Whether to include item details',
                required: false,
                default: false,
            ),
        ];
    }

    public function handle(array $arguments, ToolContext $context): ToolResult
    {
        $orderId = $arguments['order_id'];
        $includeItems = $arguments['include_items'] ?? false;

        // Verify user can access this order
        $userId = $context->getMeta('user_id');
        if (! $userId) {
            return ToolResult::error('User not authenticated');
        }

        $order = Order::where('id', $orderId)
            ->where('user_id', $userId)
            ->first();

        if (! $order) {
            return ToolResult::error("Order {$orderId} not found");
        }

        $data = [
            'id' => $order->id,
            'status' => $order->status,
            'total' => $order->total,
            'created_at' => $order->created_at->toDateString(),
        ];

        if ($includeItems) {
            $data['items'] = $order->items->map(fn($item) => [
                'name' => $item->name,
                'quantity' => $item->quantity,
                'price' => $item->price,
            ])->toArray();
        }

        return ToolResult::json($data);
    }
}
```

## Next Steps

- [AgentContract](/api-reference/agent-contract) — Agent interface
- [Context Objects](/api-reference/context-objects) — ToolContext details
- [Creating Tools](/guides/creating-tools) — Step-by-step guide
