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
use Atlasphp\Atlas\Atlas;

$response = Atlas::text('openai', 'gpt-4o')
    ->withTools([LookupOrderTool::class])
    ->withMeta(['user_id' => auth()->id()])
    ->message('Look up order ORD-123456')
    ->asText();
```

## Forcing Tool Use (Tool Choice)

By default the model decides whether to call a tool. Use a **tool choice** to control that — Atlas translates it to each provider's own `tool_choice` shape, so you never hand-write provider-specific payloads.

```php
use Atlasphp\Atlas\Tools\ToolChoice;

// Require the model to call *some* tool this turn (sugar: ->forceTools()).
Atlas::text('openai', 'gpt-4o')
    ->withTools([LookupOrderTool::class])
    ->forceTools()                      // === ->toolChoice(ToolChoice::required())
    ->message('Where is my order?')
    ->asText();

// Require a *specific* tool by name.
->toolChoice(ToolChoice::tool('lookup_order'))

// Let the model decide (the default), or forbid tools entirely.
->toolChoice(ToolChoice::auto())
->toolChoice(ToolChoice::none())
```

The choice maps per provider — `required` becomes the OpenAI/xAI/Chat-Completions string `"required"`, Anthropic `{"type":"any"}`, and Google `tool_config.function_calling_config.mode = "ANY"`; a named tool maps to each provider's "force this function" form.

**In an agent tool loop**, a forced choice is applied only to the **opening step** and then relaxed to `auto`, so the model calls a tool to start the turn but can still produce a final text reply (a permanently-forced choice would loop until `max_steps`). Agents can declare a default in code:

```php
class SupportAgent extends Agent
{
    public function toolChoice(): ?ToolChoice
    {
        return ToolChoice::required();
    }
}
```

> Forcing a tool on OpenAI uses strict-mode function schemas — Atlas tools that declare `parameters()` already emit a valid strict schema. Tool choice is ignored during structured output (`->asStructured()`), which forces its own schema tool. Providers without a native tool-choice knob (e.g. provider-native server tools) fall back to their default behavior.

## Provider Compatibility

Atlas adapts tool definitions to each provider's tool-calling format. For example, the Google provider normalizes tool parameter schemas
for Gemini function declarations and preserves Gemini continuation metadata when a tool call must be sent back with the next request.

## Provider Tools

Provider tools are native capabilities offered by AI providers (not your PHP code). They run server-side at the provider level. Atlas includes configuration objects for common provider tools and translates each to the provider's native request shape:

```php
use Atlasphp\Atlas\Providers\Tools\WebSearch;
use Atlasphp\Atlas\Providers\Tools\FileSearch;
use Atlasphp\Atlas\Providers\Tools\CodeInterpreter;

// Add to a direct request — restrict ("include") search to specific sites
$response = Atlas::text('openai', 'gpt-4o')
    ->withProviderTools([
        new WebSearch(allowedDomains: ['laravel.com', 'php.net']),
    ])
    ->message('What are the latest Laravel releases?')
    ->asText();

// Add to an agent
class ResearchAgent extends Agent
{
    public function providerTools(): array
    {
        return [
            new WebSearch(allowedDomains: ['laravel.com']),
            new CodeInterpreter,
            new FileSearch(stores: ['vs_abc123']),
        ];
    }
}
```

### Domain scoping (site inclusion)

`WebSearch` accepts `allowedDomains` / `blockedDomains`. Atlas places them where each
provider expects: nested under `filters` for OpenAI/xAI, top-level for Anthropic.

```php
new WebSearch(
    allowedDomains: ['laravel.com', 'php.net'], // only these sites
    blockedDomains: ['example-spam.com'],       // never these
);
```

### Custom attributes (forward-compatible)

Well-known attributes have typed constructor parameters, but every provider tool also
accepts an `options` bag that is merged verbatim into the native request. Use it for any
attribute a provider supports that Atlas doesn't model yet — nothing to wait on:

```php
// Anthropic web_search: max_uses + user_location pass straight through.
new WebSearch(
    allowedDomains: ['laravel.com'],
    options: ['max_uses' => 5, 'user_location' => ['type' => 'approximate', 'country' => 'US']],
);

// OpenAI web_search: search_context_size passes straight through.
new WebSearch(options: ['search_context_size' => 'high']);
```

### Available Provider Tools

Support is verified against each provider's live API. Some tools need their own attributes to
run (e.g. `FileSearch` needs `stores`; `CodeInterpreter` auto-provisions a `container`).

| Class | Type | Providers | Notes |
|-------|------|-----------|-------|
| `WebSearch` | `web_search` | OpenAI, Anthropic, xAI | Web search. `allowedDomains` / `blockedDomains` for site scoping. |
| `WebFetch` | `web_fetch` | Anthropic | Fetch and read a web page. |
| `FileSearch` | `file_search` | OpenAI | Search vector stores. Requires `stores` (`vector_store_ids`). |
| `CodeInterpreter` | `code_interpreter` | OpenAI, xAI | Run code in a sandbox container. |
| `GoogleSearch` | `google_search` | Google | Google Search grounding for Gemini. |
| `CodeExecution` | `code_execution` | Google | Code execution for Gemini. |
| `XSearch` | `x_search` | xAI | Search X/Twitter posts. `fromDate`, `toDate`, `allowedXHandles`, `enableImageUnderstanding`, `enableVideoUnderstanding`. |

Provider docs for the native attributes each tool accepts:
[OpenAI web search](https://developers.openai.com/api/docs/guides/tools-web-search) ·
[Anthropic web search](https://platform.claude.com/docs/en/agents-and-tools/tool-use/web-search-tool) ·
[Anthropic web fetch](https://platform.claude.com/docs/en/agents-and-tools/tool-use/web-fetch-tool) ·
[Gemini grounding](https://ai.google.dev/gemini-api/docs/grounding) ·
[xAI live search](https://docs.x.ai/docs/guides/live-search).

### Querying support per provider

Build provider-aware UI and validation without hardcoding the matrix — query the registry,
which is the single source of truth above:

```php
use Atlasphp\Atlas\Providers\Tools\ProviderToolRegistry;

ProviderToolRegistry::forProvider('anthropic');   // ['web_search', 'web_fetch']
ProviderToolRegistry::supports('openai', 'web_fetch'); // false
ProviderToolRegistry::all();                       // full provider → tool-type map
```

::: warning Provider Compatibility
Provider tools run on OpenAI, Anthropic, Google, and xAI. Attaching a tool a first-party provider
can't run (per the table above) throws `UnsupportedFeatureException` up front, before the request is
sent — so you catch the mistake immediately instead of seeing a cryptic provider API error. Chat
Completions and other custom providers aren't validated against the table; their tools pass through
as configured.
:::

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

When [persistence](/advanced/persistence) is enabled, provider tool calls are automatically
logged as `ExecutionToolCall` records with `type = provider`, and the citations are stored on
the search/fetch action that produced them — so the "sources" trail survives the turn:

```php
use Atlasphp\Atlas\Persistence\Models\ExecutionToolCall;

ExecutionToolCall::whereNotNull('annotations')->get()->each(function ($call) {
    $call->tool_call_id;   // which provider action produced the citations
    $call->annotations;    // the cited url_citation / web_search_result_location entries
});
```

See [Persistence → ExecutionToolCall](/advanced/persistence#executiontoolcall) for the full field
and relationship reference.

## Built-in Tools

Atlas ships one tool consumers can attach to an agent without writing any PHP: `SimilaritySearch`. It runs semantic search over an Eloquent model and auto-detects whether to query the model's whole-record embedding column or its chunked embeddings — same agent-facing shape either way.

```php
use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Tools\SimilaritySearch;

class SupportAgent extends Agent
{
    public function tools(): array
    {
        return [
            SimilaritySearch::usingModel(Project::class, limit: 5)
                ->withName('search_projects')
                ->withDescription('Search project briefs by semantic similarity.'),
        ];
    }
}
```

See [Similarity Search](/features/similarity-search) for the full options reference, including `minSimilarity`, owner-scope callbacks, and the legacy custom-column path for non-standard models.

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
