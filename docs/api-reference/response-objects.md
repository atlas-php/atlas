# Response Objects

Atlas uses immutable response objects to represent the results of AI operations.

## AgentResponse

The primary response object returned from chat operations.

### Factory Methods

**text()** — Create response with text:

```php
AgentResponse::text(string $text): AgentResponse
```

**structured()** — Create response with structured data:

```php
AgentResponse::structured(mixed $data): AgentResponse
```

**withToolCalls()** — Create response with tool calls:

```php
AgentResponse::withToolCalls(array $toolCalls): AgentResponse
```

**empty()** — Create empty response:

```php
AgentResponse::empty(): AgentResponse
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `text` | `?string` | Text response content |
| `structured` | `mixed` | Structured data (when using schema) |
| `toolCalls` | `array` | Tool calls made during execution |
| `usage` | `array` | Token usage information |
| `metadata` | `array` | Additional metadata |

### Accessor Methods

**hasText()** — Check if response has text:

```php
public function hasText(): bool
```

**hasStructured()** — Check if response has structured data:

```php
public function hasStructured(): bool
```

**hasToolCalls()** — Check if response has tool calls:

```php
public function hasToolCalls(): bool
```

**hasUsage()** — Check if response has usage data:

```php
public function hasUsage(): bool
```

### Token Usage Methods

**totalTokens()** — Get total tokens used:

```php
public function totalTokens(): int
```

**promptTokens()** — Get tokens in prompt:

```php
public function promptTokens(): int
```

**completionTokens()** — Get tokens in completion:

```php
public function completionTokens(): int
```

### Metadata Methods

**get()** — Get metadata value:

```php
public function get(string $key, mixed $default = null): mixed
```

**withMetadata()** — Create new response with metadata:

```php
public function withMetadata(array $metadata): self
```

**withUsage()** — Create new response with usage:

```php
public function withUsage(array $usage): self
```

### Example Usage

```php
$response = Atlas::agent('agent')->chat('Hello');

// Check response type
if ($response->hasText()) {
    echo $response->text;
}

if ($response->hasStructured()) {
    $data = $response->structured;
}

if ($response->hasToolCalls()) {
    foreach ($response->toolCalls as $call) {
        echo $call['name'];
        print_r($call['arguments']);
    }
}

// Token usage
echo "Total tokens: " . $response->totalTokens();
echo "Prompt tokens: " . $response->promptTokens();
echo "Completion tokens: " . $response->completionTokens();

// Metadata
$value = $response->get('custom_key', 'default');
```

## ToolResult

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

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `text` | `string` | Result content |
| `isError` | `bool` | Whether this is an error |

### Methods

**succeeded()** — Check if successful:

```php
public function succeeded(): bool
```

**failed()** — Check if failed:

```php
public function failed(): bool
```

**toArray()** — Convert to array:

```php
public function toArray(): array
// Returns: ['text' => '...', 'is_error' => false]
```

### Example Usage

```php
// In a tool handler
public function handle(array $arguments, ToolContext $context): ToolResult
{
    $order = Order::find($arguments['order_id']);

    if (! $order) {
        return ToolResult::error('Order not found');
    }

    return ToolResult::json([
        'id' => $order->id,
        'status' => $order->status,
    ]);
}
```

## Structured Response with Schema

When using structured output, the response contains parsed data:

```php
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\NumberSchema;

$schema = new ObjectSchema(
    name: 'analysis',
    description: 'Analysis result',
    properties: [
        new StringSchema('sentiment', 'The sentiment'),
        new NumberSchema('confidence', 'Confidence score'),
    ],
    requiredFields: ['sentiment', 'confidence'],
);

$response = Atlas::agent('analyzer')->withSchema($schema)->chat('Analyze: Great product!');

if ($response->hasStructured()) {
    $data = $response->structured;
    echo $data['sentiment'];    // "positive"
    echo $data['confidence'];   // 0.95
}
```

## Usage Data Structure

The usage array contains token information:

```php
$response->usage = [
    'prompt_tokens' => 100,
    'completion_tokens' => 50,
    'total_tokens' => 150,
];
```

## Tool Calls Structure

Tool calls are arrays with name and arguments:

```php
$response->toolCalls = [
    [
        'name' => 'lookup_order',
        'arguments' => [
            'order_id' => 'ORD-123',
        ],
    ],
    [
        'name' => 'search_products',
        'arguments' => [
            'query' => 'laptop',
            'limit' => 5,
        ],
    ],
];
```

## Immutability

Both `AgentResponse` and `ToolResult` are immutable. Methods that "modify" the object return new instances:

```php
$response1 = AgentResponse::text('Hello');
$response2 = $response1->withUsage(['total_tokens' => 100]);

// $response1 still has no usage data
// $response2 has the usage data
```

## Next Steps

- [Context Objects](/api-reference/context-objects) — ExecutionContext, ToolContext
- [Atlas Facade](/api-reference/atlas-facade) — Facade API
- [Structured Output](/core-concepts/structured-output) — Schema-based responses
