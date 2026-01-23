# Chat

Execute conversations with AI agents using the Atlas chat API.

## Basic Chat

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

$response = Atlas::chat('support-agent', 'Hello, I need help with my order');

echo $response->text;
// "Hi! I'd be happy to help with your order. Could you provide your order number?"
```

## Agent Reference

Agents can be referenced three ways:

```php
// By registry key
$response = Atlas::chat('support-agent', 'Hello');

// By class name
$response = Atlas::chat(SupportAgent::class, 'Hello');

// By instance
$response = Atlas::chat(new SupportAgent(), 'Hello');
```

## Chat with History

Pass conversation history for context:

```php
$messages = [
    ['role' => 'user', 'content' => 'My order number is 12345'],
    ['role' => 'assistant', 'content' => 'I found your order. How can I help?'],
];

$response = Atlas::chat('support-agent', 'Where is my package?', messages: $messages);
```

## Multi-Turn Conversations

Use `forMessages()` for richer context:

```php
$response = Atlas::forMessages($messages)
    ->withVariables([
        'user_name' => 'Alice',
        'account_tier' => 'premium',
    ])
    ->withMetadata([
        'user_id' => 123,
        'session_id' => 'abc-456',
    ])
    ->chat('support-agent', 'Check my recent orders');
```

### MessageContextBuilder Methods

```php
// Add variables for system prompt interpolation
->withVariables(array $variables)

// Add metadata for pipeline middleware and tools
->withMetadata(array $metadata)

// Execute chat with current context
->chat(string|AgentContract $agent, string $input, ?Schema $schema = null)

// Accessors
->getMessages()
->getVariables()
->getMetadata()
```

## Structured Output

Get schema-based responses:

```php
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\NumberSchema;

$schema = new ObjectSchema(
    name: 'sentiment',
    description: 'Sentiment analysis result',
    properties: [
        new StringSchema('sentiment', 'positive, negative, or neutral'),
        new NumberSchema('confidence', 'Confidence score 0-1'),
    ],
    requiredFields: ['sentiment', 'confidence'],
);

$response = Atlas::chat('analyzer', 'I love this product!', schema: $schema);

echo $response->structured['sentiment'];    // "positive"
echo $response->structured['confidence'];   // 0.95
```

## Response Handling

All chat operations return an `AgentResponse`:

### Text Response

```php
if ($response->hasText()) {
    echo $response->text;
}
```

### Structured Response

```php
if ($response->hasStructured()) {
    $data = $response->structured;
    // Access as array: $data['field']
}
```

### Tool Calls

```php
if ($response->hasToolCalls()) {
    foreach ($response->toolCalls as $call) {
        echo $call['name'];          // Tool name
        print_r($call['arguments']); // Tool arguments
    }
}
```

### Token Usage

```php
echo $response->totalTokens();      // Total tokens used
echo $response->promptTokens();     // Tokens in prompt
echo $response->completionTokens(); // Tokens in response
```

### Metadata

```php
$value = $response->get('key', 'default');

if ($response->hasUsage()) {
    $usage = $response->usage;
}
```

## Complete Example

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

class ChatController extends Controller
{
    public function respond(Request $request, Conversation $conversation)
    {
        $userMessage = $request->input('message');

        $response = Atlas::forMessages($conversation->messages)
            ->withVariables([
                'user_name' => $request->user()->name,
                'account_tier' => $request->user()->tier,
            ])
            ->withMetadata([
                'user_id' => $request->user()->id,
                'conversation_id' => $conversation->id,
            ])
            ->chat('support-agent', $userMessage);

        // Update conversation history
        $conversation->messages = array_merge($conversation->messages, [
            ['role' => 'user', 'content' => $userMessage],
            ['role' => 'assistant', 'content' => $response->text],
        ]);
        $conversation->save();

        return response()->json([
            'message' => $response->text,
            'usage' => [
                'total_tokens' => $response->totalTokens(),
            ],
        ]);
    }
}
```

## Retry & Resilience

Enable automatic retries for chat requests:

```php
// Simple retry: 3 attempts, 1 second delay
$response = Atlas::withRetry(3, 1000)->chat('agent', 'Hello');

// Exponential backoff
$response = Atlas::withRetry(3, fn($attempt) => (2 ** $attempt) * 100)
    ->chat('agent', 'Hello');

// With multi-turn context
$response = Atlas::withRetry(3, 1000)
    ->forMessages($messages)
    ->withVariables(['user_name' => 'Alice'])
    ->chat('agent', 'Continue');

// Only retry on rate limits
$response = Atlas::withRetry(3, 1000, fn($e) => $e->getCode() === 429)
    ->chat('agent', 'Hello');
```

## API Summary

| Method | Description |
|--------|-------------|
| `Atlas::chat($agent, $input)` | Simple chat with agent |
| `Atlas::chat($agent, $input, $messages)` | Chat with history |
| `Atlas::chat($agent, $input, null, $schema)` | Structured output |
| `Atlas::forMessages($messages)` | Multi-turn context builder |
| `Atlas::withRetry(...)->chat(...)` | Chat with retry |

## Next Steps

- [Conversations](/core-concepts/conversations) — Conversation management
- [Structured Output](/core-concepts/structured-output) — Schema-based responses
- [Multi-Turn Conversations](/guides/multi-turn-conversations) — Complete guide
