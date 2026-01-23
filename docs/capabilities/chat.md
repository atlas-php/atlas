# Chat

Execute conversations with AI agents using the Atlas chat API.

## Basic Chat

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

$response = Atlas::agent('support-agent')->chat('Hello, I need help with my order');

echo $response->text;
// "Hi! I'd be happy to help with your order. Could you provide your order number?"
```

## Agent Reference

Agents can be referenced three ways:

```php
// By registry key
$response = Atlas::agent('support-agent')->chat('Hello');

// By class name
$response = Atlas::agent(SupportAgent::class)->chat('Hello');

// By instance
$response = Atlas::agent(new SupportAgent())->chat('Hello');
```

## Chat with History

Pass conversation history for context:

```php
$messages = [
    ['role' => 'user', 'content' => 'My order number is 12345'],
    ['role' => 'assistant', 'content' => 'I found your order. How can I help?'],
];

$response = Atlas::agent('support-agent')
    ->withMessages($messages)
    ->chat('Where is my package?');
```

## Multi-Turn Conversations

Use the fluent builder to configure all context:

```php
$response = Atlas::agent('support-agent')
    ->withMessages($messages)
    ->withVariables([
        'user_name' => 'Alice',
        'account_tier' => 'premium',
    ])
    ->withMetadata([
        'user_id' => 123,
        'session_id' => 'abc-456',
    ])
    ->chat('Check my recent orders');
```

### Configuration Methods

| Method | Description |
|--------|-------------|
| `withProvider(string $provider, ?string $model = null)` | Override agent's provider and optionally model |
| `withModel(string $model)` | Override agent's model at runtime |
| `withProviderOptions(array $options)` | Provider-specific options |
| `withMessages(array $messages)` | Conversation history array |
| `withVariables(array $variables)` | Variables for system prompt interpolation |
| `withMetadata(array $metadata)` | Metadata for pipeline middleware and tools |
| `withSchema(Schema $schema)` | Schema for structured output |
| `withToolChoice(ToolChoice\|string $choice)` | Control tool usage behavior |
| `withRetry($times, $delay)` | Retry configuration for resilience |
| `withImage($data, $source, $mimeType, $disk)` | Attach image(s) for vision analysis |
| `withDocument($data, $source, $mimeType, $title, $disk)` | Attach document(s) |
| `withAudio($data, $source, $mimeType, $disk)` | Attach audio file(s) |
| `withVideo($data, $source, $mimeType, $disk)` | Attach video file(s) |

See [Multimodal](/capabilities/multimodal) for detailed attachment usage.

## Tool Choice Control

Control when and how the agent uses tools with `withToolChoice()`:

```php
use Atlasphp\Atlas\Tools\Enums\ToolChoice;

// Default - model decides when to use tools
$response = Atlas::agent('agent')
    ->withToolChoice(ToolChoice::Auto)
    ->chat('Help me with my order');

// Must use a tool - forces tool invocation
$response = Atlas::agent('agent')
    ->withToolChoice(ToolChoice::Any)
    ->chat('Calculate 42 * 17');

// Cannot use tools - text-only response
$response = Atlas::agent('agent')
    ->withToolChoice(ToolChoice::None)
    ->chat('Explain how calculators work');

// Force a specific tool by name
$response = Atlas::agent('agent')
    ->withToolChoice('calculator')
    ->chat('What is 100 divided by 4?');
```

### Convenience Methods

```php
// Shorthand for ToolChoice::Any
$response = Atlas::agent('agent')
    ->requireTool()
    ->chat('...');

// Shorthand for ToolChoice::None
$response = Atlas::agent('agent')
    ->disableTools()
    ->chat('...');

// Shorthand for forcing a specific tool
$response = Atlas::agent('agent')
    ->forceTool('weather')
    ->chat('What\'s the weather in Paris?');
```

### Tool Choice Options

| Option | Description |
|--------|-------------|
| `ToolChoice::Auto` | Model decides whether to use tools (default) |
| `ToolChoice::Any` | Model must use at least one tool |
| `ToolChoice::None` | Model cannot use any tools |
| `'tool_name'` | Model must use the specified tool |

### Chat Method Parameters

| Parameter | Description |
|-----------|-------------|
| `$input` | User message (required) |
| `stream: true` | Enable streaming response |

## Structured Output

Get schema-based responses using the `withSchema()` fluent method:

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

$response = Atlas::agent('analyzer')
    ->withSchema($schema)
    ->chat('I love this product!');

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

        $response = Atlas::agent('support-agent')
            ->withMessages($conversation->messages)
            ->withVariables([
                'user_name' => $request->user()->name,
                'account_tier' => $request->user()->tier,
            ])
            ->withMetadata([
                'user_id' => $request->user()->id,
                'conversation_id' => $conversation->id,
            ])
            ->chat($userMessage);

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
$response = Atlas::agent('agent')
    ->withRetry(3, 1000)
    ->chat('Hello');

// Exponential backoff
$response = Atlas::agent('agent')
    ->withRetry(3, fn($attempt) => (2 ** $attempt) * 100)
    ->chat('Hello');

// With multi-turn context
$response = Atlas::agent('agent')
    ->withMessages($messages)
    ->withVariables(['user_name' => 'Alice'])
    ->withRetry(3, 1000)
    ->chat('Continue');

// Only retry on rate limits
$response = Atlas::agent('agent')
    ->withRetry(3, 1000, fn($e) => $e->getCode() === 429)
    ->chat('Hello');
```

## API Summary

| Method | Description |
|--------|-------------|
| `Atlas::agent($agent)->chat($input)` | Simple chat with agent |
| `->withMessages($msgs)->chat($input)` | Chat with history |
| `->withVariables($vars)->chat($input)` | Chat with variables |
| `->withSchema($schema)->chat($input)` | Structured output |
| `->withToolChoice($choice)->chat($input)` | Control tool usage |
| `->withRetry(...)->chat(...)` | Chat with retry |

## Next Steps

- [Multimodal](/capabilities/multimodal) — Add images, documents, audio to conversations
- [Conversations](/core-concepts/conversations) — Conversation management
- [Structured Output](/core-concepts/structured-output) — Schema-based responses
- [Multi-Turn Conversations](/guides/multi-turn-conversations) — Complete guide
