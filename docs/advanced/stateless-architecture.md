# Stateless Architecture

Atlas is designed around a stateless architecture. Understanding this design philosophy is key to building effective applications.

## Why Stateless?

Atlas agents don't store conversation history, user context, or session data internally. This deliberate choice provides:

### 1. Full Control Over Persistence

You decide where and how to store conversations:

```php
// Store in database
$conversation->messages = $updatedMessages;
$conversation->save();

// Store in cache
Cache::put("conversation:{$id}", $messages, 3600);

// Store in session
session()->put('conversation', $messages);
```

### 2. Custom Trimming and Summarization

Implement your own strategies for managing conversation length:

```php
class ConversationManager
{
    public function trim(array $messages): array
    {
        // Keep only recent messages
        return array_slice($messages, -20);
    }

    public function summarize(array $messages): array
    {
        // Summarize old messages
        $old = array_slice($messages, 0, -10);
        $recent = array_slice($messages, -10);

        $summary = Atlas::agent('summarizer')->chat(json_encode($old));

        return [
            ['role' => 'system', 'content' => "Summary: {$summary->text}"],
            ...$recent,
        ];
    }
}
```

### 3. Replay and Debugging

Replay conversations for debugging or analysis:

```php
// Replay a conversation up to a specific point
$messagesUpToError = array_slice($conversation->messages, 0, 15);
$response = Atlas::agent('agent')->withMessages($messagesUpToError)->chat($originalInput);
```

### 4. Multi-Tenant Support

Handle multiple tenants without state collision:

```php
// Each request explicitly provides tenant context
$response = Atlas::agent('agent')
    ->withMessages($messages)
    ->withMetadata(['tenant_id' => $tenant->id])
    ->chat($input);
```

### 5. Horizontal Scaling

Stateless design enables easy horizontal scaling:

```
Request 1 → Server A → Atlas (no state)
Request 2 → Server B → Atlas (no state)
Request 3 → Server C → Atlas (no state)
```

All servers can handle any request because state is externalized.

## The Stateless Flow

```
┌─────────────────┐
│   Application   │
│   (Your Code)   │
└────────┬────────┘
         │
         │ 1. Load conversation from storage
         │ 2. Build context (messages, variables, metadata)
         ▼
┌─────────────────┐
│     Atlas       │
│   (Stateless)   │
└────────┬────────┘
         │
         │ 3. Execute agent
         │ 4. Return response
         ▼
┌─────────────────┐
│   Application   │
│   (Your Code)   │
└────────┬────────┘
         │
         │ 5. Update conversation in storage
         ▼
┌─────────────────┐
│    Storage      │
│ (DB/Cache/etc)  │
└─────────────────┘
```

## Implementation Pattern

### Controller Example

```php
class ChatController extends Controller
{
    public function respond(Request $request, Conversation $conversation)
    {
        // 1. Get input
        $userMessage = $request->input('message');

        // 2. Build context from stored state
        $response = Atlas::agent('support-agent')
            ->withMessages($conversation->messages)
            ->withVariables([
                'user_name' => $request->user()->name,
            ])
            ->withMetadata([
                'user_id' => $request->user()->id,
            ])
            ->chat($userMessage);

        // 3. Update stored state
        $conversation->messages = array_merge($conversation->messages, [
            ['role' => 'user', 'content' => $userMessage],
            ['role' => 'assistant', 'content' => $response->text],
        ]);
        $conversation->save();

        // 4. Return response
        return response()->json([
            'message' => $response->text,
        ]);
    }
}
```

### Service Example

```php
class ChatService
{
    public function __construct(
        private ConversationRepository $conversations,
        private ContextBuilder $contextBuilder,
    ) {}

    public function respond(int $conversationId, string $input): AgentResponse
    {
        // Load state
        $conversation = $this->conversations->find($conversationId);

        // Build context
        $context = $this->contextBuilder->build($conversation);

        // Execute (stateless)
        $response = Atlas::agent($conversation->agent_key)
            ->withMessages($context['messages'])
            ->withVariables($context['variables'])
            ->withMetadata($context['metadata'])
            ->chat($input);

        // Persist state
        $this->conversations->appendMessages($conversation, [
            ['role' => 'user', 'content' => $input],
            ['role' => 'assistant', 'content' => $response->text],
        ]);

        return $response;
    }
}
```

## State Management Strategies

### Database Storage

Best for: Persistence, audit trails, multi-device access

```php
class Conversation extends Model
{
    protected $casts = ['messages' => 'array'];
}
```

### Cache Storage

Best for: Performance, temporary conversations

```php
class CacheConversationStore
{
    public function get(string $id): array
    {
        return Cache::get("conversation:{$id}", []);
    }

    public function set(string $id, array $messages): void
    {
        Cache::put("conversation:{$id}", $messages, 3600);
    }
}
```

### Session Storage

Best for: Simple use cases, no persistence needed

```php
$messages = session()->get('chat_messages', []);
// ... use messages ...
session()->put('chat_messages', $updatedMessages);
```

### Hybrid Storage

Best for: Performance + persistence

```php
class HybridConversationStore
{
    public function get(string $id): array
    {
        // Try cache first
        $messages = Cache::get("conversation:{$id}");

        if ($messages === null) {
            // Fall back to database
            $conversation = Conversation::find($id);
            $messages = $conversation->messages;

            // Warm cache
            Cache::put("conversation:{$id}", $messages, 3600);
        }

        return $messages;
    }

    public function set(string $id, array $messages): void
    {
        // Update both
        Cache::put("conversation:{$id}", $messages, 3600);
        Conversation::where('id', $id)->update(['messages' => $messages]);
    }
}
```

## Context vs State

Understanding the difference:

| Concept | Description | Where It Lives |
|---------|-------------|----------------|
| **State** | Persistent data (conversations, users) | Your application |
| **Context** | Data passed to Atlas per-request | ExecutionContext |

Atlas receives **context**, not **state**. Your application manages state and builds context from it.

## Benefits Summary

| Benefit | Description |
|---------|-------------|
| **Testability** | No hidden state makes testing straightforward |
| **Scalability** | Any server can handle any request |
| **Flexibility** | Choose your own persistence strategy |
| **Debuggability** | Replay conversations exactly |
| **Multi-tenancy** | Clean separation per request |
| **Reliability** | No state corruption between requests |

## Next Steps

- [Conversations](/core-concepts/conversations) — Conversation handling
- [Error Handling](/advanced/error-handling) — Handle failures gracefully
- [Performance](/advanced/performance) — Optimize your application
