# Tools

Tools are functions that agents can invoke during execution. They connect AI agents to your application's business logic, databases, and external services.

## What is a Tool?

A tool defines:
- **Name** — Unique identifier for the function
- **Description** — What the tool does (helps the AI decide when to use it)
- **Parameters** — Typed inputs with JSON Schema validation
- **Handler** — The function that executes when called

```php
use Atlasphp\Atlas\Contracts\Tools\Support\{ToolContext};use Atlasphp\Atlas\Contracts\Tools\Support\ToolParameter;use Atlasphp\Atlas\Contracts\Tools\Support\ToolResult;use Atlasphp\Atlas\Contracts\Tools\ToolDefinition;

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
        $order = Order::find($arguments['order_id']);

        if (! $order) {
            return ToolResult::error('Order not found');
        }

        return ToolResult::json([
            'id' => $order->id,
            'status' => $order->status,
            'total' => $order->total,
        ]);
    }
}
```

## Stateless Design

Like agents, tools are stateless. They receive all context through:
- `$arguments` — The parameters passed by the AI
- `$context` — Metadata from the execution context

## Tool Registry

Register tools for use across agents:

```php
use Atlasphp\Atlas\Contracts\Tools\Contracts\ToolRegistryContract;

$registry = app(ToolRegistryContract::class);

// Register by class
$registry->register(LookupOrderTool::class);

// Register instance
$registry->registerInstance(new LookupOrderTool());

// Query tools
$registry->has('lookup_order');
$registry->get('lookup_order');
$registry->all();
$registry->only(['lookup_order', 'search']);
```

## Adding Tools to Agents

Reference tools in your agent's `tools()` method:

```php
public function tools(): array
{
    return [
        LookupOrderTool::class,
        SearchProductsTool::class,
        CalculatorTool::class,
    ];
}
```

## Parameter Types

Atlas supports all JSON Schema parameter types:

### String

```php
ToolParameter::string('query', 'The search query', required: true);
ToolParameter::string('format', 'Output format', required: false, default: 'json');
```

### Integer

```php
ToolParameter::integer('limit', 'Maximum results', required: true);
ToolParameter::integer('page', 'Page number', required: false, default: 1);
```

### Number (Float)

```php
ToolParameter::number('price', 'Item price', required: true);
ToolParameter::number('threshold', 'Confidence threshold', required: false, default: 0.8);
```

### Boolean

```php
ToolParameter::boolean('include_details', 'Include full details', required: false, default: false);
```

### Enum

```php
ToolParameter::enum('status', 'Order status', ['pending', 'shipped', 'delivered'], required: true);
```

### Array

```php
ToolParameter::array('tags', 'List of tags', items: ['type' => 'string']);
```

### Object

```php
ToolParameter::object('address', 'Shipping address', [
    ToolParameter::string('street', 'Street address', required: true),
    ToolParameter::string('city', 'City', required: true),
    ToolParameter::string('zip', 'ZIP code', required: true),
], required: true);
```

## Tool Results

Return results using the `ToolResult` class:

### Text Result

```php
return ToolResult::text('Operation completed successfully');
```

### JSON Result

```php
return ToolResult::json([
    'status' => 'success',
    'data' => $results,
]);
```

### Error Result

```php
return ToolResult::error('Failed to process: invalid input');
```

### Checking Results

```php
$result->succeeded(); // true if not an error
$result->failed();    // true if error
$result->toArray();   // ['text' => '...', 'is_error' => false]
```

## Tool Context

Access execution metadata via `ToolContext`:

```php
public function handle(array $arguments, ToolContext $context): ToolResult
{
    // Get metadata passed from execution context
    $userId = $context->getMeta('user_id');
    $tenantId = $context->getMeta('tenant_id');

    // Check if metadata exists
    if ($context->hasMeta('session_id')) {
        $sessionId = $context->getMeta('session_id');
    }

    // Use metadata in your logic
    $orders = Order::where('user_id', $userId)->get();

    return ToolResult::json($orders);
}
```

## Example: Tool with Dependencies

Tools can use dependency injection:

```php
class DatabaseQueryTool extends ToolDefinition
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function name(): string
    {
        return 'query_database';
    }

    public function description(): string
    {
        return 'Query the database for records';
    }

    public function parameters(): array
    {
        return [
            ToolParameter::string('table', 'Table name', required: true),
            ToolParameter::integer('limit', 'Max records', required: false, default: 100),
        ];
    }

    public function handle(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $results = $this->db
                ->table($arguments['table'])
                ->limit($arguments['limit'] ?? 100)
                ->get();

            return ToolResult::json($results->toArray());
        } catch (\Exception $e) {
            return ToolResult::error("Query failed: {$e->getMessage()}");
        }
    }
}
```

## Max Steps

Control how many tool calls an agent can make:

```php
public function maxSteps(): ?int
{
    return 5; // Allow up to 5 tool iterations
}
```

## Next Steps

- [Creating Tools](/guides/creating-tools) — Step-by-step guide
- [Agents](/core-concepts/agents) — Add tools to agents
- [Pipelines](/core-concepts/pipelines) — Add middleware to tool execution
