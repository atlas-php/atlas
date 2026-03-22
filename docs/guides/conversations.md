# Conversations

Atlas provides optional conversation persistence — store message history, track executions, and enable features like retry and respond mode.

## Setup

### 1. Enable Persistence

```env
ATLAS_PERSISTENCE_ENABLED=true
```

### 2. Publish and Run Migrations

```bash
php artisan vendor:publish --tag=atlas-migrations
php artisan migrate
```

This creates tables for conversations, messages, executions, execution steps, execution tool calls, assets, and memory.

## Basic Usage

### Start a Conversation

Use `for()` to bind a conversation to a model (typically a user). Atlas creates the conversation automatically on first use.

```php
$response = Atlas::agent('support')
    ->for($user)
    ->message('Hello, I need help with my order.')
    ->asText();
```

The conversation is created with `$user` as the owner. Subsequent calls with the same owner and agent find the existing conversation.

### Continue a Conversation

```php
// Join an existing conversation by ID
$response = Atlas::agent('support')
    ->forConversation($conversationId)
    ->message('What about my refund?')
    ->asText();
```

Atlas automatically loads the conversation history and sends it to the provider so the agent has full context.

### Set Message Author

In multi-user scenarios, track who sent each message:

```php
$response = Atlas::agent('support')
    ->for($team)
    ->asUser($currentUser)
    ->message('Can someone help?')
    ->asText();
```

## Message History

### Automatic Loading

When a conversation is active, Atlas loads the message history automatically. You don't need to pass messages manually — they're prepended to the request before the provider call.

### Message Limit

Control how many messages are loaded:

```php
// Per-call override
$response = Atlas::agent('support')
    ->forConversation($conversationId)
    ->withMessageLimit(20)
    ->message('Hello')
    ->asText();
```

The message limit can also be set:
- In `config/atlas.php` → `persistence.message_limit` (default: 50)
- On the agent class via the `HasConversations` trait
- Per-call via `->withMessageLimit()`

## Respond Mode

Have the agent respond to a conversation thread without a new user message. The agent sees the full conversation history and generates a response as if continuing the thread.

### Use Cases

- **Proactive follow-ups** — agent checks back after a delay
- **Background results** — a job completes and the agent reports back
- **Scheduled check-ins** — cron-triggered agent responses
- **Multi-agent handoff** — one agent triggers another to respond in the same thread

### Usage

```php
$response = Atlas::agent('support')
    ->forConversation($conversationId)
    ->respond()
    ->asText();
```

The response is stored as a new assistant message, parented to the last user message in the thread. No user message is created.

::: warning Requires Existing Conversation
`respond()` requires `forConversation($id)`. There must be an existing conversation with at least one user message.
:::

### Queued Respond

```php
Atlas::agent('support')
    ->forConversation($conversationId)
    ->respond()
    ->queue()
    ->dispatch();
```

## Retry Mode

Regenerate the last assistant response. Creates a new sibling response while deactivating the previous one.

### How It Works

1. Atlas finds the last active assistant message in the conversation
2. Deactivates all messages sharing that `parent_id` (the entire sibling group)
3. Runs the agent with the same conversation context
4. Stores the new response with the same `parent_id` — creating a sibling
5. Only the new response is active and included in future history

### Usage

```php
$response = Atlas::agent('support')
    ->forConversation($conversationId)
    ->retry()
    ->asText();
```

### When Can You Retry?

A response can only be retried if no user message was sent after it:

```php
$message->canRetry();  // true if retryable, false if conversation continued
```

Once a user sends a new message, all previous assistant responses are locked — you can cycle between existing siblings but not create new ones.

## Sibling Messages

Retries create sibling messages — multiple responses to the same user message. This is the "regenerate response" feature seen in chat UIs.

### Sibling Structure

```
User: "What is Laravel?"
├── [Active]   Assistant: "Laravel is a PHP framework..." (retry 3)
├── [Inactive] Assistant: "Laravel is a web application..." (retry 2)
└── [Inactive] Assistant: "Laravel is an open-source..." (retry 1)
```

All siblings share the same `parent_id`. Only one sibling group is active at a time. When conversation history is loaded, only active messages are included.

### Multi-Step Siblings

When a response involves tool calls, the entire execution chain (assistant message + tool results + final response) is treated as a single sibling group. Retrying or cycling replaces the entire group, not individual messages.

### Sibling Info

Get information about a message's siblings for UI display:

```php
use Atlasphp\Atlas\Persistence\Services\ConversationService;

$service = app(ConversationService::class);
$info = $service->siblingInfo($message);

// [
//     'current' => 2,          // This is response #2 of 3
//     'total' => 3,            // 3 total response alternatives
//     'groups' => [...]        // The sibling group collections
// ]
```

### Cycling Between Siblings

Switch which sibling is active — like clicking "< 2/3 >" in a chat UI:

```php
// Show the first response alternative
$service->cycleSibling($conversation, $message->parent_id, 0);

// Show the third response alternative
$service->cycleSibling($conversation, $message->parent_id, 2);
```

This deactivates all siblings and activates only the target group. Subsequent conversation history loading reflects the change.

## Read Status

Track which messages have been read:

```php
$message->markAsRead();

// Query unread messages
$unread = $conversation->messages()
    ->where('is_active', true)
    ->whereNull('read_at')
    ->count();
```

## Queued Messages

Atlas supports message queuing for rate limiting or sequential processing:

```php
$service = app(ConversationService::class);

// Queue a message for later processing
$service->queueMessage($conversation, $userMessage, $author);

// Process the next queued message
$service->deliverNextQueued($conversation);
```

When an agent execution completes, Atlas automatically checks for queued messages and dispatches a job to process the next one.

## Multi-Agent Conversations

Multiple agents can share a conversation. Atlas remaps message roles so each agent sees a consistent view:

- Messages from the current agent → `assistant` role
- Messages from other agents → `user` role with `[AgentName]:` prefix
- Tool results from other agents → `user` role with context

This allows agent-to-agent collaboration within a single conversation thread.

## Database Schema

### Key Columns (Messages Table)

| Column | Type | Purpose |
|--------|------|---------|
| `parent_id` | `bigint nullable` | Links responses to their parent message (enables siblings) |
| `is_active` | `boolean` | Controls which sibling is visible in history |
| `sequence` | `int` | Ordering within the conversation |
| `role` | `string` | user, assistant, system |
| `status` | `string` | delivered, queued |
| `agent` | `string nullable` | Which agent authored this message |
| `step_id` | `bigint nullable` | Links to execution step for tool call reconstruction |
| `read_at` | `timestamp nullable` | When the message was read |

### Relationships

- Conversation → has many Messages
- Conversation → has many Executions
- Message → belongs to Conversation
- Message → has many siblings (same parent_id)
- Message → has many responses (children where parent_id = this.id)

## API Reference

### AgentRequest Methods

| Method | Description |
|--------|-------------|
| `->for(Model $owner)` | Set conversation owner (creates/finds conversation) |
| `->forConversation(int $id)` | Join an existing conversation |
| `->asUser(Model $author)` | Set message author |
| `->withMessageLimit(int $limit)` | Override message history limit |
| `->respond()` | Respond without a new user message |
| `->retry()` | Retry the last assistant response |

### Message Model Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `canRetry()` | `bool` | Whether this message can be retried |
| `siblingGroups()` | `Collection` | Groups of siblings by execution |
| `siblingIndex()` | `int` | 1-based index in sibling list |
| `siblings()` | `HasMany` | All messages with same parent_id |
| `responses()` | `HasMany` | Child messages (responses to this message) |
| `markAsRead()` | `void` | Mark message as read |

### ConversationService Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `prepareRetry($conversation)` | `int` | Deactivate current response, return parent_id |
| `cycleSibling($conversation, $parentId, $index)` | `void` | Switch active sibling group |
| `siblingInfo($message)` | `array` | Current index, total count, groups |
| `lastUserMessageId($conversation)` | `?int` | Last active user message ID |
| `queueMessage($conversation, $message, $author)` | `Message` | Queue a message for later |
| `deliverNextQueued($conversation)` | `?Message` | Process next queued message |

## Next Steps

- [Agents](/core-concepts/agents) — Agent configuration and usage
- [Middleware](/core-concepts/pipelines) — Add middleware to agent execution
