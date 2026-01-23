# Conversations

Atlas is stateless by design. Your application manages conversation history and passes it to Atlas on each request.

## Stateless Architecture

Atlas doesn't store conversation history. This means:

- **You control persistence** — Store in database, cache, or session
- **You control trimming** — Decide when to summarize or truncate
- **You control replay** — Re-run conversations as needed
- **Clean separation** — AI logic stays separate from application state

## Message Format

Messages follow the standard chat format:

```php
$messages = [
    ['role' => 'user', 'content' => 'Hello!'],
    ['role' => 'assistant', 'content' => 'Hi there! How can I help?'],
    ['role' => 'user', 'content' => 'I have a question about my order'],
];
```

### Roles

| Role | Description |
|------|-------------|
| `user` | Messages from the user |
| `assistant` | Responses from the AI |
| `system` | System messages in history (use sparingly) |

## Passing History

### Simple Chat with History

```php
$response = Atlas::agent('support-agent')
    ->withMessages($previousMessages)
    ->chat('What about my refund?');
```

### With Variables and Metadata

For richer context with variables and metadata:

```php
$response = Atlas::agent('support-agent')
    ->withMessages($messages)
    ->withVariables(['user_name' => 'Alice'])
    ->withMetadata(['user_id' => 123])
    ->chat('Continue our conversation');
```

## Storing Conversations

### Database Example

```php
// Migration
Schema::create('conversations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->json('messages');
    $table->timestamps();
});

// Model
class Conversation extends Model
{
    protected $casts = [
        'messages' => 'array',
    ];
}
```

### Controller Example

```php
class ChatController extends Controller
{
    public function respond(Request $request, Conversation $conversation)
    {
        $userMessage = $request->input('message');

        // Execute with full history
        $response = Atlas::agent('support-agent')
            ->withMessages($conversation->messages)
            ->withVariables([
                'user_name' => $request->user()->name,
            ])
            ->withMetadata([
                'user_id' => $request->user()->id,
            ])
            ->chat($userMessage);

        // Update history
        $conversation->messages = array_merge($conversation->messages, [
            ['role' => 'user', 'content' => $userMessage],
            ['role' => 'assistant', 'content' => $response->text],
        ]);
        $conversation->save();

        return response()->json([
            'message' => $response->text,
            'tokens' => $response->totalTokens(),
        ]);
    }
}
```

## Context Immutability

`PendingAgentRequest` is immutable. Each method returns a new instance:

```php
$builder1 = Atlas::agent('support-agent')->withVariables(['name' => 'John']);
$builder2 = $builder1->withMetadata(['session' => 'abc']);

// $builder1 has only variables
// $builder2 has both variables and metadata
```

This allows safe method chaining without side effects.

## Token Management

Long conversations consume tokens. Consider:

### Truncation

Keep only recent messages:

```php
$recentMessages = array_slice($messages, -10); // Last 10 messages
```

### Summarization

Periodically summarize older messages:

```php
// Summarize first N messages, keep recent ones
$summary = $this->summarizeMessages(array_slice($messages, 0, -5));
$recent = array_slice($messages, -5);

$messages = [
    ['role' => 'system', 'content' => "Previous conversation summary: {$summary}"],
    ...$recent,
];
```

### Token Counting

Monitor token usage:

```php
$response = Atlas::agent('agent')->withMessages($messages)->chat('Hello');

if ($response->totalTokens() > 3000) {
    // Time to trim the conversation
}
```

## ExecutionContext

For programmatic use, create an `ExecutionContext`:

```php
use Atlasphp\Atlas\Agents\Support\ExecutionContext;

$context = new ExecutionContext(
    messages: $conversationHistory,
    variables: ['user_name' => 'John'],
    metadata: ['session_id' => 'abc123'],
);

// Immutable updates
$context = $context->withVariables(['user_name' => 'Jane']);
$context = $context->mergeMetadata(['trace_id' => 'xyz']);

// Accessors
$context->getVariable('user_name', 'default');
$context->getMeta('session_id');
$context->hasMessages();
```

## Next Steps

- [Multi-Turn Conversations](/guides/multi-turn-conversations) — Complete guide
- [System Prompts](/core-concepts/system-prompts) — Variable interpolation
- [Structured Output](/core-concepts/structured-output) — Schema-based responses
