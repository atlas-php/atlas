# Multi-Turn Conversations

## Goal

Handle multi-turn conversations with history, variables, and metadata.

## Prerequisites

- Atlas installed and configured
- An agent created (see [Creating Agents](./creating-agents.md))

## Steps

### 1. Store Conversation History

Atlas is stateless. Your application manages conversation history:

```php
// Example: Store in database
class Conversation extends Model
{
    protected $casts = [
        'messages' => 'array',
    ];
}
```

### 2. Pass Messages via withMessages()

Pass conversation history using the fluent `withMessages()` method:

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

// Load previous messages
$conversation = Conversation::find($id);
$messages = $conversation->messages;

// Continue the conversation
$response = Atlas::agent('support-agent')
    ->withMessages($messages)
    ->chat($userInput);

// Save the updated history
$conversation->messages = array_merge($messages, [
    ['role' => 'user', 'content' => $userInput],
    ['role' => 'assistant', 'content' => $response->text],
]);
$conversation->save();
```

### 3. Add Variables for System Prompt

Variables interpolate into the agent's system prompt. Use `withVariables()` to set them:

```php
$response = Atlas::agent('support-agent')
    ->withMessages($messages)
    ->withVariables([
        'user_name' => $user->name,
        'account_tier' => $user->subscription->tier,
        'app_name' => config('app.name'),
    ])
    ->chat($userInput);
```

If the agent's system prompt contains `{user_name}`, it becomes the actual user name.

### 4. Add Metadata for Pipeline Middleware

Metadata passes through to pipeline middleware and tools. Use `withMetadata()`:

```php
$response = Atlas::agent('support-agent')
    ->withMessages($messages)
    ->withVariables(['user_name' => $user->name])
    ->withMetadata([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
        'session_id' => session()->getId(),
    ])
    ->chat($userInput);
```

Tools can access metadata via `ToolContext`:

```php
public function handle(array $arguments, ToolContext $context): ToolResult
{
    $userId = $context->getMeta('user_id');
    // ...
}
```

### 5. Structured Output

For structured responses, use `withSchema()`:

```php
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

$schema = new ObjectSchema(
    name: 'sentiment',
    description: 'Sentiment analysis result',
    properties: [
        new StringSchema('sentiment', 'The sentiment (positive, negative, neutral)'),
        new StringSchema('confidence', 'Confidence level'),
    ],
    requiredFields: ['sentiment'],
);

$response = Atlas::agent('analysis-agent')
    ->withMessages($messages)
    ->withSchema($schema)
    ->chat('Analyze the sentiment');

$sentiment = $response->structured['sentiment'];
```

## Message Format

Messages follow the standard chat format:

```php
$messages = [
    ['role' => 'user', 'content' => 'Hello!'],
    ['role' => 'assistant', 'content' => 'Hi there! How can I help?'],
    ['role' => 'user', 'content' => 'I have a question about my order'],
];
```

Valid roles: `user`, `assistant`, `system` (for system messages in history).

## Immutability

`PendingAgentRequest` is immutable. Each method returns a new instance:

```php
$builder1 = Atlas::agent('support-agent')->withVariables(['name' => 'John']);
$builder2 = $builder1->withMetadata(['session' => 'abc']);

// $builder1 has only variables
// $builder2 has both variables and metadata
```

This allows safe method chaining without side effects.

## Complete Example

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

class ChatController extends Controller
{
    public function respond(Request $request, Conversation $conversation)
    {
        $userMessage = $request->input('message');

        // Build context with full state
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

## Common Issues

### Variables Not Applied

If system prompt variables aren't replaced:
1. Ensure variable names match exactly (case-sensitive)
2. Use snake_case for variable names
3. Check that `withVariables()` is called before `chat()`

### Empty Response

If responses seem to ignore history:
1. Verify messages are in the correct format
2. Check that roles are valid (`user`, `assistant`)
3. Ensure messages aren't empty strings

## Next Steps

- [Extending Atlas](/guides/extending-atlas) — Add pipeline middleware
- [Conversations](/core-concepts/conversations) — Conversation concepts
- [Structured Output](/core-concepts/structured-output) — Schema-based responses
