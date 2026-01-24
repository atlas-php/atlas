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

use Atlasphp\Atlas\Contracts\Tools\Support\ToolContext;use Atlasphp\Atlas\Contracts\Tools\Support\ToolParameter;use Atlasphp\Atlas\Contracts\Tools\Support\ToolResult;use Atlasphp\Atlas\Contracts\Tools\ToolDefinition;

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
use App\Tools\LookupOrderTool;use Atlasphp\Atlas\Contracts\Tools\Contracts\ToolRegistryContract;

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

## Security Best Practices

Tools execute real operations on behalf of users. Implement proper security measures.

### Authorization Checks

Always verify the user has permission to perform the action:

```php
public function handle(array $arguments, ToolContext $context): ToolResult
{
    $userId = $context->getMeta('user_id');

    // Verify user is authenticated
    if (! $userId) {
        return ToolResult::error('Authentication required');
    }

    // Verify user can access this resource
    $order = Order::find($arguments['order_id']);

    if (! $order || $order->user_id !== $userId) {
        return ToolResult::error('Order not found');
    }

    return ToolResult::json($order);
}
```

### Input Validation

Validate inputs beyond basic parameter types:

```php
public function handle(array $arguments, ToolContext $context): ToolResult
{
    $orderId = $arguments['order_id'];

    // Validate format (prevent injection attacks)
    if (! preg_match('/^[A-Z]{2}-\d{6}$/', $orderId)) {
        return ToolResult::error('Invalid order ID format');
    }

    // Validate business rules
    if ($arguments['quantity'] > 100) {
        return ToolResult::error('Quantity exceeds maximum allowed');
    }

    // Sanitize string inputs
    $note = strip_tags($arguments['note'] ?? '');

    // Continue processing...
}
```

### Protect Sensitive Data

Never expose internal system details or sensitive information:

```php
public function handle(array $arguments, ToolContext $context): ToolResult
{
    $user = User::find($arguments['user_id']);

    // Return only safe, necessary fields
    return ToolResult::json([
        'id' => $user->id,
        'name' => $user->name,
        'tier' => $user->subscription_tier,
        // Never expose:
        // - 'password_hash' => $user->password
        // - 'api_key' => $user->api_key
        // - 'internal_notes' => $user->admin_notes
        // - 'payment_details' => $user->stripe_customer_id
    ]);
}
```

### Rate Limiting

Prevent abuse of expensive operations:

```php
use Illuminate\Support\Facades\RateLimiter;

public function handle(array $arguments, ToolContext $context): ToolResult
{
    $userId = $context->getMeta('user_id');
    $key = "tool:search_products:{$userId}";

    // Limit to 10 searches per minute
    if (RateLimiter::tooManyAttempts($key, 10)) {
        return ToolResult::error('Too many requests. Please wait a moment.');
    }

    RateLimiter::hit($key, 60);

    // Proceed with search...
    return ToolResult::json($results);
}
```

### Audit Logging

Log sensitive operations for compliance:

```php
public function handle(array $arguments, ToolContext $context): ToolResult
{
    $userId = $context->getMeta('user_id');

    // Perform the action
    $result = $this->performSensitiveAction($arguments);

    // Log for audit trail
    Log::info('Sensitive tool executed', [
        'tool' => $this->name(),
        'user_id' => $userId,
        'arguments' => $this->sanitizeForLogging($arguments),
        'result' => $result->succeeded() ? 'success' : 'failure',
        'timestamp' => now()->toIso8601String(),
    ]);

    return $result;
}

private function sanitizeForLogging(array $arguments): array
{
    // Remove sensitive fields before logging
    unset($arguments['password'], $arguments['credit_card']);
    return $arguments;
}
```

### Multi-Tenant Isolation

Ensure data isolation in multi-tenant applications:

```php
public function handle(array $arguments, ToolContext $context): ToolResult
{
    $tenantId = $context->getMeta('tenant_id');

    if (! $tenantId) {
        return ToolResult::error('Tenant context required');
    }

    // Always scope queries to tenant
    $orders = Order::where('tenant_id', $tenantId)
        ->where('id', $arguments['order_id'])
        ->first();

    if (! $orders) {
        return ToolResult::error('Order not found');
    }

    return ToolResult::json($orders);
}
```

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

- [Creating Agents](/guides/creating-agents) — Add tools to agents
- [Tools](/core-concepts/tools) — Tool concepts deep dive
- [Pipelines](/core-concepts/pipelines) — Add middleware to tool execution
