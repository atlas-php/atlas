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

## Conversation History Management

Long-running conversations can exceed model context windows. Here are strategies to manage history effectively.

### Message Pruning

Keep only the most recent messages:

```php
class ConversationService
{
    private int $maxMessages = 20;

    public function respond(Conversation $conversation, string $input): AgentResponse
    {
        $messages = $conversation->messages;

        // Keep only the last N messages
        if (count($messages) > $this->maxMessages) {
            $messages = array_slice($messages, -$this->maxMessages);
        }

        $response = Atlas::agent('support-agent')
            ->withMessages($messages)
            ->chat($input);

        // Save full history, but send pruned to API
        $conversation->messages = array_merge($conversation->messages, [
            ['role' => 'user', 'content' => $input],
            ['role' => 'assistant', 'content' => $response->text],
        ]);
        $conversation->save();

        return $response;
    }
}
```

### Summarization Strategy

Summarize older messages to preserve context while reducing token usage:

```php
class SmartConversationService
{
    private int $recentMessagesCount = 10;

    public function respond(Conversation $conversation, string $input): AgentResponse
    {
        $messages = $conversation->messages;

        if (count($messages) > $this->recentMessagesCount * 2) {
            $messages = $this->prepareWithSummary($messages);
        }

        return Atlas::agent('support-agent')
            ->withMessages($messages)
            ->chat($input);
    }

    private function prepareWithSummary(array $messages): array
    {
        $oldMessages = array_slice($messages, 0, -$this->recentMessagesCount);
        $recentMessages = array_slice($messages, -$this->recentMessagesCount);

        // Generate summary of older messages
        $summary = Atlas::agent('summarizer')
            ->chat('Summarize this conversation briefly: ' . json_encode($oldMessages));

        // Prepend summary as context
        return array_merge(
            [['role' => 'system', 'content' => "Previous context: {$summary->text}"]],
            $recentMessages,
        );
    }
}
```

### Token Budget Management

Estimate tokens before sending to avoid context window errors:

```php
class TokenAwareConversationService
{
    private int $maxContextTokens = 100000;
    private int $estimatedResponseTokens = 4000;

    public function respond(Conversation $conversation, string $input): AgentResponse
    {
        $messages = $conversation->messages;
        $budgetForHistory = $this->maxContextTokens - $this->estimatedResponseTokens;

        // Estimate tokens (rough: ~4 chars per token)
        while ($this->estimateTokens($messages) > $budgetForHistory && count($messages) > 2) {
            // Remove oldest messages until within budget
            array_shift($messages);
        }

        return Atlas::agent('support-agent')
            ->withMessages($messages)
            ->chat($input);
    }

    private function estimateTokens(array $messages): int
    {
        $totalChars = array_sum(array_map(
            fn($m) => strlen($m['content'] ?? ''),
            $messages
        ));

        return (int) ceil($totalChars / 4);
    }
}
```

### Conversation Expiry

Clean up stale conversations:

```php
// In a scheduled command
class CleanupConversations extends Command
{
    protected $signature = 'conversations:cleanup';

    public function handle(): void
    {
        // Delete conversations older than 30 days
        Conversation::where('updated_at', '<', now()->subDays(30))->delete();

        // Archive conversations with excessive messages
        Conversation::where('message_count', '>', 500)
            ->where('updated_at', '<', now()->subDays(7))
            ->update(['status' => 'archived']);
    }
}
```

### Sliding Window with Anchors

Keep important messages (like the first exchange) while pruning middle content:

```php
class AnchoredConversationService
{
    private int $anchorCount = 2;   // Keep first N messages
    private int $recentCount = 10;  // Keep last N messages

    public function prepareMessages(array $messages): array
    {
        if (count($messages) <= $this->anchorCount + $this->recentCount) {
            return $messages;
        }

        $anchors = array_slice($messages, 0, $this->anchorCount);
        $recent = array_slice($messages, -$this->recentCount);

        return array_merge($anchors, $recent);
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

### Context Length Exceeded

If you get context length errors:
1. Implement message pruning (keep recent messages only)
2. Use summarization for older messages
3. Monitor token usage with `$response->totalTokens()`

## Next Steps

- [Extending Atlas](/guides/extending-atlas) — Add pipeline middleware
- [Conversations](/core-concepts/conversations) — Conversation concepts
- [Structured Output](/core-concepts/structured-output) — Schema-based responses
