# Tools

Tools let agents call your PHP code. Define typed parameters, implement a handler, and Atlas manages the tool call loop.


## Defining a Tool

Extend the `Tool` base class and implement the four methods:

```php
use Atlasphp\Atlas\Tools\Tool;
use Atlasphp\Atlas\Schema\Fields\StringField;

class LookupOrderTool extends Tool
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
            new StringField('order_id', 'The order ID to look up'),
        ];
    }

    public function handle(array $args, array $context): mixed
    {
        $order = Order::find($args['order_id']);

        return $order ? $order->toArray() : 'Order not found';
    }
}
```

## Return Values

The `handle()` method can return any type. Atlas automatically serializes the return value to a string the model can read using `ToolSerializer`:

| Return Type | Serialization |
|-------------|---------------|
| `string` | Passed through as-is |
| `array` | JSON encoded |
| `JsonSerializable` | JSON encoded |
| Object with `toArray()` | Calls `toArray()`, then JSON encodes |
| Object with `toJson()` | Calls `toJson()` |
| `bool` | `'true'` or `'false'` |
| `int` / `float` | Cast to string |
| `null` | `'No result returned.'` |
| Other objects | Cast to array, then JSON encoded |

```php
// All valid return values
return 'Order not found';                    // string passthrough
return $order->toArray();                    // array → JSON
return Order::where('active', true)->get();  // Collection → toJson()
return true;                                 // → 'true'
return null;                                 // → 'No result returned.'
```

## Parameters (Schema Fields)

Define tool parameters using schema field classes from `Atlasphp\Atlas\Schema\Fields\`. All fields are **required by default** -- call `->optional()` to make them optional.

### Available Field Types

```php
use Atlasphp\Atlas\Schema\Fields\StringField;
use Atlasphp\Atlas\Schema\Fields\IntegerField;
use Atlasphp\Atlas\Schema\Fields\NumberField;
use Atlasphp\Atlas\Schema\Fields\BooleanField;
use Atlasphp\Atlas\Schema\Fields\EnumField;
use Atlasphp\Atlas\Schema\Fields\ArrayField;
use Atlasphp\Atlas\Schema\Fields\ObjectField;

public function parameters(): array
{
    return [
        new StringField('query', 'The search query'),
        new IntegerField('limit', 'Maximum number of results'),
        new NumberField('min_price', 'Minimum price filter'),
        new BooleanField('include_details', 'Include full details'),
        new EnumField('status', 'Order status', ['pending', 'shipped', 'delivered']),
    ];
}
```

You can also use the `Schema` builder for a more compact syntax:

```php
use Atlasphp\Atlas\Schema\Schema;

public function parameters(): array
{
    return [
        Schema::string('query', 'The search query'),
        Schema::integer('limit', 'Maximum number of results')->optional(),
        Schema::number('min_price', 'Minimum price filter')->optional(),
        Schema::boolean('include_details', 'Include full details')->optional(),
        Schema::enum('status', 'Order status', ['pending', 'shipped', 'delivered']),
    ];
}
```

### Required vs Optional

Fields are required by default. Call `->optional()` to mark a field as optional:

```php
Schema::string('query', 'The search query'),              // required
Schema::integer('limit', 'Max results')->optional(),      // optional
```

### Array Fields

```php
// Array of strings
ArrayField::ofStrings('tags', 'List of tags'),

// Array of numbers
ArrayField::ofNumbers('scores', 'Score values'),

// Array of objects
ArrayField::ofObjects('items', 'Order items', function ($builder) {
    $builder->string('name', 'Item name');
    $builder->number('quantity', 'Quantity');
}),
```

### Object Fields

```php
new ObjectField('address', 'Shipping address', function ($obj) {
    $obj->string('street', 'Street address');
    $obj->string('city', 'City');
    $obj->string('zip', 'ZIP code');
    $obj->string('notes', 'Delivery notes')->optional();
}),
```

`ObjectField` supports a fluent builder with `->string()`, `->integer()`, `->number()`, `->boolean()`, `->enum()`, `->stringArray()`, `->numberArray()`, `->array()`, and `->object()` methods for defining nested properties.

## Dependency Injection

Tools are resolved from Laravel's container, so constructor injection works naturally:

```php
use Atlasphp\Atlas\Tools\Tool;
use Illuminate\Database\ConnectionInterface;

class DatabaseQueryTool extends Tool
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function name(): string { return 'query_database'; }
    public function description(): string { return 'Query the database for records'; }

    public function parameters(): array
    {
        return [
            Schema::string('table', 'Table name'),
            Schema::integer('limit', 'Max records')->optional(),
        ];
    }

    public function handle(array $args, array $context): mixed
    {
        return $this->db
            ->table($args['table'])
            ->limit($args['limit'] ?? 100)
            ->get()
            ->toArray();
    }
}
```

## Context

The `$context` array in `handle()` receives metadata passed via `withMeta()`. Use it for user identity, tenant isolation, feature flags, and other request-scoped data:

```php
// When calling the agent
Atlas::agent('support')
    ->withMeta(['user_id' => 1, 'tenant_id' => 5])
    ->message('What is the status of my order?')
    ->asText();
```

```php
// Inside your tool
public function handle(array $args, array $context): mixed
{
    $userId = $context['user_id'] ?? null;
    $tenantId = $context['tenant_id'] ?? null;

    $order = Order::where('user_id', $userId)
        ->where('tenant_id', $tenantId)
        ->where('id', $args['order_id'])
        ->first();

    return $order ? $order->toArray() : 'Order not found';
}
```

## Using Tools with Agents

Reference tools in your agent's `tools()` method. Atlas resolves them from the container and manages the tool call loop automatically:

```php
use Atlasphp\Atlas\Agent;

class SupportAgent extends Agent
{
    public function tools(): array
    {
        return [
            LookupOrderTool::class,
            SearchProductsTool::class,
            CreateTicketTool::class,
        ];
    }

    // ... other agent methods
}
```

When the model decides to call a tool, Atlas executes the handler, sends the result back, and continues the conversation until the model produces a final text response.

## Using Tools with Direct Calls

You can also attach tools to direct (non-agent) text requests:

```php
use Atlasphp\Atlas\Facades\Atlas;

$response = Atlas::text('openai', 'gpt-4o')
    ->withTools([LookupOrderTool::class])
    ->withMeta(['user_id' => auth()->id()])
    ->message('Look up order ORD-123456')
    ->asText();
```

## Provider Tools

Provider tools are native capabilities offered by AI providers (not your PHP code). They run server-side at the provider level. Atlas includes configuration objects for common provider tools:

```php
use Atlasphp\Atlas\Providers\Tools\WebSearch;
use Atlasphp\Atlas\Providers\Tools\FileSearch;
use Atlasphp\Atlas\Providers\Tools\CodeInterpreter;

// Add to a direct request
$response = Atlas::text('openai', 'gpt-4o')
    ->withProviderTools([
        new WebSearch(maxResults: 5, locale: 'en-US'),
    ])
    ->message('What are the latest Laravel releases?')
    ->asText();

// Add to an agent
class ResearchAgent extends Agent
{
    public function providerTools(): array
    {
        return [
            new WebSearch,
            new CodeInterpreter,
            new FileSearch(stores: ['vs_abc123'], maxResults: 10),
        ];
    }
}
```

### Available Provider Tools

| Class | Type | Description |
|-------|------|-------------|
| `WebSearch` | `web_search` | Search the web. Options: `maxResults`, `locale` |
| `FileSearch` | `file_search` | Search vector stores. Options: `stores`, `maxResults` |
| `CodeInterpreter` | `code_interpreter` | Execute code in a sandbox |

### Observability

Provider tool invocations are captured on the response for inspection:

```php
$response = Atlas::text('openai', 'gpt-4o')
    ->withProviderTools([new WebSearch])
    ->message('What is the latest PHP version?')
    ->asText();

// Raw provider tool call data (web_search_call, code_interpreter_call, etc.)
$response->providerToolCalls;

// Content annotations (url_citation, file_citation) from provider responses
$response->annotations;
```

When [persistence](/advanced/persistence) is enabled, provider tool calls are automatically logged as `ExecutionToolCall` records with `type = provider`.

## API Reference

### Tool Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `name()` | `string` | Tool name the model uses to call it |
| `description()` | `string` | When and how the model should use this tool |
| `parameters()` | `array<Field>` | Parameter definitions using Schema fields |
| `handle(array $args, array $context)` | `mixed` | Execute the tool — return value auto-serialized |

### Return Value Serialization

| Return Type | Serialized As |
|-------------|---------------|
| `string` | Passed through as-is |
| `array` or `object` | JSON encoded |
| `bool` | `'true'` or `'false'` |
| `int` or `float` | Cast to string |
| `null` | `'No result returned.'` |
| Object with `toArray()` | JSON encoded via `toArray()` |
| Object with `toJson()` | Passed through as JSON string |

## Artisan Command

```bash
php artisan make:tool LookupOrderTool
```

## Next Steps

- [Schema](/features/schema) — Field types for tool parameters
- [Agents](/features/agents) — Add tools to agents
- [Middleware](/features/middleware) — Add middleware to tool execution
