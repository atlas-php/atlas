# Creating Tools

## Goal

Create custom tools that agents can invoke during execution.

## Prerequisites

- Atlas installed and configured
- Understanding of function calling in AI systems

## Steps

### 1. Create a Tool Class

Create a class extending `ToolDefinition`:

```php
<?php

namespace App\Tools;

use Atlasphp\Atlas\Tools\ToolDefinition;
use Atlasphp\Atlas\Tools\Support\ToolContext;
use Atlasphp\Atlas\Tools\Support\ToolParameter;
use Atlasphp\Atlas\Tools\Support\ToolResult;

class LookupOrderTool extends ToolDefinition
{
    public function name(): string
    {
        return 'lookup_order';
    }

    public function description(): string
    {
        return 'Look up order details by order ID';
    }

    public function parameters(): array
    {
        return [
            ToolParameter::string('order_id', 'The order ID to look up', required: true),
        ];
    }

    public function handle(array $arguments, ToolContext $context): ToolResult
    {
        $orderId = $arguments['order_id'];

        // Fetch order from your database
        $order = Order::where('id', $orderId)->first();

        if (! $order) {
            return ToolResult::error("Order {$orderId} not found");
        }

        return ToolResult::json([
            'id' => $order->id,
            'status' => $order->status,
            'total' => $order->total,
            'items' => $order->items->count(),
        ]);
    }
}
```

### 2. Register the Tool

In a service provider:

```php
use Atlasphp\Atlas\Tools\Contracts\ToolRegistryContract;
use App\Tools\LookupOrderTool;

public function boot(): void
{
    $registry = app(ToolRegistryContract::class);
    $registry->register(LookupOrderTool::class);
}
```

### 3. Add Tool to Agent

Reference the tool in your agent's `tools()` method:

```php
public function tools(): array
{
    return [
        LookupOrderTool::class,
    ];
}
```

## Parameter Types

### String Parameter

```php
ToolParameter::string('name', 'Description', required: true, default: 'value');
```

### Integer Parameter

```php
ToolParameter::integer('count', 'Number of items', required: true);
```

### Number Parameter (Float)

```php
ToolParameter::number('price', 'Item price', required: true);
```

### Boolean Parameter

```php
ToolParameter::boolean('include_details', 'Include full details', required: false, default: false);
```

### Enum Parameter

```php
ToolParameter::enum('status', 'Order status', ['pending', 'shipped', 'delivered'], required: true);
```

### Array Parameter

```php
ToolParameter::array('tags', 'List of tags', 'string');
```

### Object Parameter

```php
ToolParameter::object('address', 'Shipping address', [
    ToolParameter::string('street', 'Street address', required: true),
    ToolParameter::string('city', 'City', required: true),
    ToolParameter::string('zip', 'ZIP code', required: true),
], required: true);
```

## Tool Results

### Text Result

```php
return ToolResult::text('Order shipped successfully');
```

### JSON Result

```php
return ToolResult::json([
    'status' => 'success',
    'data' => $data,
]);
```

### Error Result

```php
return ToolResult::error('Order not found');
```

## Using Tool Context

The `ToolContext` provides metadata from the execution:

```php
public function handle(array $arguments, ToolContext $context): ToolResult
{
    // Access metadata
    $userId = $context->getMeta('user_id');
    $tenantId = $context->getMeta('tenant_id');

    // Check if metadata exists
    if ($context->hasMeta('session_id')) {
        $sessionId = $context->getMeta('session_id');
    }

    // ... tool logic
}
```

Metadata is passed through the execution context.

## Common Issues

### Tool Not Available

If the agent doesn't use your tool:
1. Verify the tool is listed in the agent's `tools()` method
2. Check the tool description clearly explains when to use it
3. Ensure parameters have helpful descriptions

### Parameter Validation

If you receive unexpected parameter values:
1. Mark required parameters with `required: true`
2. Use appropriate parameter types
3. Provide meaningful descriptions

## Next Steps

- [Creating Agents](./Creating-Agents.md) - Add tools to agents
- [SPEC-Tools](../spec/SPEC-Tools.md) - Tool module specification
