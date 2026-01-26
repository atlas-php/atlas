# Tools

Tools are functions that agents can invoke during execution. They connect AI agents to your application's business logic, databases, and external services.

::: tip Prism Reference
Atlas tools wrap Prism's tool system. For underlying tool concepts, schemas, and function calling details, see the [Prism Tools documentation](https://prismphp.com/core-concepts/tools-function-calling.html).
:::

::: info External Tools
For tools from external MCP servers, see [MCP Integration](/capabilities/mcp).
:::

## What is a Tool?

A tool defines:
- **Name** — Unique identifier for the function
- **Description** — What the tool does (helps the AI decide when to use it)
- **Parameters** — Typed inputs with JSON Schema validation
- **Handler** — The function that executes when called

```php
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
            ToolParameter::string('order_id', 'The order ID to look up'),
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

## Tool Registry

Tools are automatically discovered and registered from your configured directory (default: `app/Tools`). Just create your tool class and it's ready to use:

```php
// app/Tools/LookupOrderTool.php
class LookupOrderTool extends ToolDefinition
{
    // ... tool definition
}

// Use immediately in agents - no manual registration needed
public function tools(): array
{
    return [LookupOrderTool::class];
}
```

Configure auto-discovery in `config/atlas.php`:

```php
'tools' => [
    'path' => app_path('Tools'),
    'namespace' => 'App\\Tools',
],
```

### Manual Registration

If you prefer manual control or need to register tools from other locations:

```php
use Atlasphp\Atlas\Tools\Contracts\ToolRegistryContract;

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

Atlas provides `ToolParameter` as a convenience factory for creating Prism schemas.

```php
use Atlasphp\Atlas\Tools\Support\ToolParameter;

public function parameters(): array
{
    return [
        ToolParameter::string('query', 'The search query'),
        ToolParameter::integer('limit', 'Maximum results'),
        ToolParameter::number('price', 'Item price'),
        ToolParameter::boolean('include_details', 'Include full details'),
        ToolParameter::enum('status', 'Order status', ['pending', 'shipped', 'delivered']),
    ];
}
```

### Available Types

<div class="full-width-table">

| Method | Description |
|--------|-------------|
| `ToolParameter::string($name, $description, $nullable)` | Text values |
| `ToolParameter::number($name, $description, $nullable)` | Float/decimal values |
| `ToolParameter::integer($name, $description, $nullable)` | Integer values (alias for number) |
| `ToolParameter::boolean($name, $description, $nullable)` | True/false values |
| `ToolParameter::enum($name, $description, $options)` | Predefined set of options |
| `ToolParameter::array($name, $description, $items)` | List of items (pass a Schema for item type) |
| `ToolParameter::object($name, $description, $properties, $requiredFields, $allowAdditionalProperties)` | Nested object |

</div>

### Nullable Parameters

Mark parameters as nullable when they're optional:

```php
ToolParameter::string('notes', 'Optional notes', nullable: true);
```

### Array Parameters

```php
// Array of strings
ToolParameter::array('tags', 'List of tags', ToolParameter::string('tag', 'A tag'));

// Array of objects
ToolParameter::array('items', 'Order items', ToolParameter::object('item', 'An item', [
    ToolParameter::string('name', 'Item name'),
    ToolParameter::number('quantity', 'Quantity'),
]));
```

### Object Parameters

```php
ToolParameter::object('address', 'Shipping address', [
    ToolParameter::string('street', 'Street address'),
    ToolParameter::string('city', 'City'),
    ToolParameter::string('zip', 'ZIP code'),
], requiredFields: ['street', 'city', 'zip']);
```

## Using Prism Schemas Directly

You can also use Prism schema classes directly instead of `ToolParameter`:

```php
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\ObjectSchema;

public function parameters(): array
{
    return [
        new StringSchema('query', 'The search query'),
        new NumberSchema('limit', 'Maximum results'),
        new BooleanSchema('active', 'Is active'),
        new EnumSchema('status', 'Status', ['pending', 'complete']),
    ];
}
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

### Accessing Results

```php
$result->toText();    // String representation (JSON encoded if array)
$result->toArray();   // Array data, or ['text' => $data] for text results
$result->succeeded(); // true if not an error
$result->failed();    // true if error
```

## Tool Context

Access execution metadata via `ToolContext`. Metadata is passed from the agent using `withMetadata()`:

```php
// When calling the agent
Atlas::agent('support')->withMetadata(['user_id' => 1, 'tenant_id' => 5])->chat('...');
```

```php
// Inside your tool
public function handle(array $arguments, ToolContext $context): ToolResult
{
    // Get metadata passed from execution context
    $userId = $context->getMeta('user_id');
    $tenantId = $context->getMeta('tenant_id');

    // Get with default value
    $limit = $context->getMeta('limit', 100);

    // Check if metadata exists
    if ($context->hasMeta('session_id')) {
        $sessionId = $context->getMeta('session_id');
    }

    // Use metadata in your logic
    $orders = Order::where('user_id', $userId)->limit($limit)->get();

    return ToolResult::json($orders);
}
```

## Example: Tool with Dependencies

Tools can use dependency injection via Laravel's container:

```php
use Atlasphp\Atlas\Tools\ToolDefinition;
use Atlasphp\Atlas\Tools\Support\ToolContext;
use Atlasphp\Atlas\Tools\Support\ToolParameter;
use Atlasphp\Atlas\Tools\Support\ToolResult;
use Illuminate\Database\ConnectionInterface;

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
            ToolParameter::string('table', 'Table name'),
            ToolParameter::integer('limit', 'Max records', nullable: true),
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

## Example: Order Lookup Tool

A tool for retrieving order details, commonly used in sales and support agents.

```php
class LookupOrderTool extends ToolDefinition
{
    public function name(): string { return 'lookup_order'; }
    public function description(): string { return 'Look up order details by order ID'; }

    public function parameters(): array
    {
        return [
            ToolParameter::string('order_id', 'The order ID to look up'),
        ];
    }

    public function handle(array $arguments, ToolContext $context): ToolResult
    {
        $userId = $context->getMeta('user_id');

        $order = Order::where('id', $arguments['order_id'])
            ->where('user_id', $userId)
            ->first();

        if (! $order) {
            return ToolResult::error('Order not found');
        }

        return ToolResult::json([
            'id' => $order->id,
            'status' => $order->status,
            'total' => $order->total,
            'items' => $order->items->count(),
            'created_at' => $order->created_at->toDateString(),
        ]);
    }
}
```

## Example: Search Knowledge Base Tool

A tool for searching FAQs and help articles, useful for customer service agents.

```php
class SearchKnowledgeBaseTool extends ToolDefinition
{
    public function __construct(private KnowledgeBaseService $kb) {}

    public function name(): string { return 'search_knowledge_base'; }
    public function description(): string { return 'Search FAQs and help articles for answers'; }

    public function parameters(): array
    {
        return [
            ToolParameter::string('query', 'The search query'),
            ToolParameter::integer('limit', 'Maximum results to return', nullable: true),
        ];
    }

    public function handle(array $arguments, ToolContext $context): ToolResult
    {
        $results = $this->kb->search(
            query: $arguments['query'],
            limit: $arguments['limit'] ?? 5
        );

        if ($results->isEmpty()) {
            return ToolResult::text('No articles found matching your query.');
        }

        return ToolResult::json($results->map(fn ($article) => [
            'title' => $article->title,
            'summary' => Str::limit($article->content, 200),
            'url' => $article->url,
        ])->toArray());
    }
}
```

## Example: Create Support Ticket Tool

A tool for creating support tickets, with input validation and context awareness.

```php
class CreateTicketTool extends ToolDefinition
{
    public function name(): string { return 'create_ticket'; }
    public function description(): string { return 'Create a support ticket for issues requiring follow-up'; }

    public function parameters(): array
    {
        return [
            ToolParameter::string('subject', 'Brief description of the issue'),
            ToolParameter::string('description', 'Detailed description of the problem'),
            ToolParameter::enum('priority', 'Ticket priority', ['low', 'medium', 'high']),
            ToolParameter::string('category', 'Issue category', nullable: true),
        ];
    }

    public function handle(array $arguments, ToolContext $context): ToolResult
    {
        $userId = $context->getMeta('user_id');

        if (! $userId) {
            return ToolResult::error('User authentication required');
        }

        $ticket = Ticket::create([
            'user_id' => $userId,
            'subject' => $arguments['subject'],
            'description' => $arguments['description'],
            'priority' => $arguments['priority'],
            'category' => $arguments['category'] ?? 'general',
            'status' => 'open',
        ]);

        return ToolResult::json([
            'ticket_id' => $ticket->id,
            'message' => "Ticket #{$ticket->id} created successfully",
        ]);
    }
}
```

## Example: Send Notification Tool

A tool for sending notifications via multiple channels.

```php
class SendNotificationTool extends ToolDefinition
{
    public function name(): string { return 'send_notification'; }
    public function description(): string { return 'Send a notification to the user via email or SMS'; }

    public function parameters(): array
    {
        return [
            ToolParameter::enum('channel', 'Notification channel', ['email', 'sms']),
            ToolParameter::string('subject', 'Notification subject (email only)', nullable: true),
            ToolParameter::string('message', 'The notification message'),
        ];
    }

    public function handle(array $arguments, ToolContext $context): ToolResult
    {
        $user = User::find($context->getMeta('user_id'));

        if (! $user) {
            return ToolResult::error('User not found');
        }

        match ($arguments['channel']) {
            'email' => $user->notify(new GenericEmailNotification(
                subject: $arguments['subject'] ?? 'Notification',
                message: $arguments['message']
            )),
            'sms' => $user->notify(new SmsNotification($arguments['message'])),
        };

        return ToolResult::text("Notification sent via {$arguments['channel']}");
    }
}
```

## Security

Tools execute real operations on behalf of users. Implement proper security measures.

### Authorization

Always verify the user has permission:

```php
public function handle(array $arguments, ToolContext $context): ToolResult
{
    $userId = $context->getMeta('user_id');

    if (! $userId) {
        return ToolResult::error('Authentication required');
    }

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

    if (! preg_match('/^[A-Z]{2}-\d{6}$/', $orderId)) {
        return ToolResult::error('Invalid order ID format');
    }

    if ($arguments['quantity'] > 100) {
        return ToolResult::error('Quantity exceeds maximum allowed');
    }

    // Continue processing...
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

    $order = Order::where('tenant_id', $tenantId)
        ->where('id', $arguments['order_id'])
        ->first();

    if (! $order) {
        return ToolResult::error('Order not found');
    }

    return ToolResult::json($order);
}
```

## Pipeline Hooks

Tool execution supports pipeline middleware:

<div class="full-width-table">

| Pipeline | Trigger |
|----------|---------|
| `tool.before_resolve` | Before tool is resolved from registry |
| `tool.after_resolve` | After tool is resolved, before building |
| `tool.before_execute` | Before tool execution |
| `tool.after_execute` | After tool execution |
| `tool.on_error` | When tool execution fails |

</div>

```php
use Atlasphp\Atlas\Contracts\PipelineContract;

class LogToolExecution implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        $result = $next($data);

        Log::info('Tool executed', [
            'tool' => $data['tool']->name(),
            'success' => $result['result']->succeeded(),
        ]);

        return $result;
    }
}

$registry->register('tool.after_execute', LogToolExecution::class);
```

## Advanced Prism Configuration

For advanced use cases, implement `ConfiguresPrismTool` to access the full Prism Tool API directly. This gives you control over error handling, provider options, and other Prism-specific features.

```php
use Atlasphp\Atlas\Tools\ToolDefinition;
use Atlasphp\Atlas\Tools\Contracts\ConfiguresPrismTool;
use Atlasphp\Atlas\Tools\Support\ToolContext;
use Atlasphp\Atlas\Tools\Support\ToolParameter;
use Atlasphp\Atlas\Tools\Support\ToolResult;
use Prism\Prism\Tool as PrismTool;
use Illuminate\Support\Facades\Log;

class RiskyOperationTool extends ToolDefinition implements ConfiguresPrismTool
{
    public function name(): string
    {
        return 'risky_operation';
    }

    public function description(): string
    {
        return 'Performs an operation that may fail';
    }

    public function parameters(): array
    {
        return [
            ToolParameter::string('input', 'The input data'),
        ];
    }

    public function handle(array $arguments, ToolContext $context): ToolResult
    {
        // Your tool logic here
        return ToolResult::text('Operation completed');
    }

    public function configurePrismTool(PrismTool $tool): PrismTool
    {
        return $tool->failed(function ($error) {
            Log::warning('Tool failed', ['error' => $error]);
            return 'Tool encountered an error, please try again.';
        });
    }
}
```

### Available Prism Tool Methods

The `configurePrismTool` method gives you access to:

- `failed(callable $handler)` — Custom error handling with user-friendly messages
- `withErrorHandling()` / `withoutErrorHandling()` — Toggle automatic error handling
- `withProviderOptions(array $options)` — Provider-specific tool configuration

See [Prism Tools documentation](https://prismphp.com/core-concepts/tools-function-calling.html) for the full API.

## API Reference

```php
// ToolDefinition methods (override in your tool class)
public function name(): string;
public function description(): string;
public function parameters(): array;
public function handle(array $arguments, ToolContext $context): ToolResult;

// ToolParameter factory methods
ToolParameter::string(string $name, string $description, bool $nullable = false);
ToolParameter::number(string $name, string $description, bool $nullable = false);
ToolParameter::integer(string $name, string $description, bool $nullable = false);
ToolParameter::boolean(string $name, string $description, bool $nullable = false);
ToolParameter::enum(string $name, string $description, array $options);
ToolParameter::array(string $name, string $description, Schema $items);
ToolParameter::object(string $name, string $description, array $properties, array $requiredFields = [], bool $allowAdditionalProperties = false);

// ToolResult factory methods
ToolResult::text(string $text): ToolResult;
ToolResult::json(array $data): ToolResult;
ToolResult::error(string $message): ToolResult;

// ToolResult instance methods
$result->toText(): string;   // String representation (JSON encoded if array)
$result->toArray(): array;   // Array data, or ['text' => $data] for text results
$result->succeeded(): bool;
$result->failed(): bool;

// ToolContext methods
$context->getMeta(string $key, mixed $default = null): mixed;
$context->hasMeta(string $key): bool;

// ToolRegistryContract methods
$registry->register(string $class): void;
$registry->registerInstance(ToolContract $tool): void;
$registry->has(string $name): bool;
$registry->get(string $name): ToolContract;
$registry->all(): array;
$registry->only(array $names): array;

// Runtime tools on PendingAgentRequest
->withTools(array $tools): static;  // Add tools at runtime, accumulates across calls

// ConfiguresPrismTool interface (optional)
public function configurePrismTool(PrismTool $tool): PrismTool;
```

## Next Steps

- [Agents](/core-concepts/agents) — Add tools to agents
- [MCP](/capabilities/mcp) — External tools from MCP servers
- [Pipelines](/core-concepts/pipelines) — Add middleware to tool execution
