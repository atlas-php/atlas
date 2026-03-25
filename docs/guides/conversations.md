# Conversations

Atlas provides optional conversation persistence — store message history, track executions, and enable features like retry and respond mode.

::: info Persistence Reference
For the complete database schema, table details, model overrides, and configuration options, see the [Persistence Reference](/advanced/persistence).
:::

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

This creates tables for conversations, messages, executions, execution steps, execution tool calls, and assets.

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

### Set Message Owner

In multi-user scenarios, track who sent each message using the `as:` parameter:

```php
$response = Atlas::agent('support')
    ->for($team, as: $currentUser)
    ->message('Can someone help?')
    ->asText();
```

When `as:` is omitted, the conversation owner is used as the message sender.

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

## Message Attachments

Messages in a conversation can have file attachments — both from user input and from agent-generated content.

### User Attachments

When a user sends a message with media (images, documents, audio), the media is part of the message sent to the provider. The provider processes it inline (e.g. vision for images, transcription for audio):

```php
use Atlasphp\Atlas\Input\Image;

$response = Atlas::agent('support')
    ->forConversation($conversationId)
    ->message('What does this receipt show?', Image::fromUpload($request->file('receipt')))
    ->asText();
```

To persist the uploaded file as a tracked asset, store it before or after the call:

```php
$image = Image::fromUpload($request->file('photo'));
$path = $image->store('public');  // Store to disk

$response = Atlas::agent('support')
    ->forConversation($conversationId)
    ->message('Describe this photo', $image)
    ->asText();
```

### Agent-Generated Attachments

When a tool generates files during an agent execution — images, audio, PDFs, reports — those files are automatically attached to the assistant message in the conversation.

```php
// Inside a tool's handle() method
use Atlasphp\Atlas\Persistence\ToolAssets;

class GenerateChartTool extends Tool
{
    public function handle(array $args, array $context): mixed
    {
        $chartImage = $this->renderChart($args['data']);

        $asset = ToolAssets::store($chartImage, [
            'type' => 'image',
            'mime_type' => 'image/png',
            'description' => 'Sales chart',
        ]);

        return "Chart generated: {$asset->path}";
    }
}
```

When the agent execution completes, Atlas links the tool-generated asset to the stored assistant message via `MessageAttachment`. This happens automatically — no extra code needed.

### Media from Atlas Modality Calls

If a tool calls an Atlas modality (e.g. `Atlas::image()` inside a tool), the generated file is also auto-attached:

```php
class CreateImageTool extends Tool
{
    public function handle(array $args, array $context): mixed
    {
        $response = Atlas::image('openai', 'dall-e-3')
            ->instructions($args['prompt'])
            ->asImage();

        return "Image created at: {$response->asset->path}";
    }
}
```

### Querying Attachments

Retrieve attachments from a conversation message:

```php
$message = Message::find($messageId);

foreach ($message->attachments as $attachment) {
    $asset = $attachment->asset;

    $asset->type;       // "image", "audio", "document"
    $asset->mime_type;  // "image/png"
    $asset->path;       // Storage path
    $asset->disk;       // Filesystem disk

    // Generate a URL
    $url = Storage::disk($asset->disk)->url($asset->path);
}

// Attachment metadata shows which tool produced it
$attachment->metadata;
// ['tool_call_id' => 'call_abc123', 'tool_name' => 'generate_chart']
```

See [Media & Assets](/guides/media-storage) for the complete storage guide including manual storage, auto-storage configuration, and ToolAssets API.

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
    ->asText();
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

Every message has a `read_at` timestamp that tracks whether it has been seen. Atlas uses this in two ways:

### Agent Read Tracking

When an agent processes a user message, Atlas automatically marks it as read — the agent has "seen" and responded to it. This happens inside the `PersistConversation` middleware, so you don't need to do anything.

### User Read Tracking

For tracking whether a **user** has read the agent's responses, call `markAsRead()` from your application — typically when the user opens the conversation or scrolls to a message:

```php
// Mark a single message as read
$message->markAsRead();

// Mark all unread messages in a conversation as read
$conversation->messages()
    ->where('is_active', true)
    ->whereNull('read_at')
    ->update(['read_at' => now()]);
```

### Unread Counts

Use `read_at` for notification badges, unread indicators, or any visibility tracking:

```php
// Count unread assistant messages (agent responses the user hasn't seen)
$unreadFromAgent = $conversation->messages()
    ->where('is_active', true)
    ->where('role', 'assistant')
    ->whereNull('read_at')
    ->count();

// Count unread user messages (messages the agent hasn't processed yet)
$unreadFromUsers = $conversation->messages()
    ->where('is_active', true)
    ->where('role', 'user')
    ->whereNull('read_at')
    ->count();
```

::: tip Build Your Own
Atlas provides the `read_at` column and `markAsRead()` method — what you build on top is up to you. Common patterns include push notifications for unread agent responses, badge counts in a sidebar, or "new messages" indicators in a chat UI.
:::

## Queued Messages

Atlas supports message queuing for rate limiting or sequential processing:

```php
$service = app(ConversationService::class);

// Queue a message for later processing
$service->queueMessage($conversation, $userMessage, $owner);

// Process the next queued message
$service->deliverNextQueued($conversation);
```

When an agent execution completes, Atlas automatically checks for queued messages and dispatches a job to process the next one. See the [Queue & Background Jobs](/guides/queue) guide for retry behavior, timeout configuration, and execution tracking.

## Multi-Agent Conversations

Multiple agents can share a conversation thread. Atlas automatically remaps message roles so each agent sees a consistent view of the conversation from its own perspective.

### Multi-Agent Collaboration

Two or more agents can participate in the same conversation. Each agent reads the thread, sees its own messages as `assistant`, and sees other agents' messages as `user` with a name prefix.

```php
// Support agent handles the initial request
$response = Atlas::agent('support')
    ->for($user)
    ->message('I need a refund for order #123')
    ->asText();

// Billing agent responds in the same thread
$response = Atlas::agent('billing')
    ->forConversation($conversationId)
    ->respond()
    ->asText();
```

When the billing agent reads the thread, the support agent's messages appear as user messages with a `[Support]:` prefix. The billing agent treats these as context from another participant — it knows what was said, but sees itself as the assistant in the conversation. The support agent's earlier assistant responses are remapped to `user` role so the billing agent's provider receives a well-formed message history.

### Multi-User Conversations

Multiple users can participate in a single conversation with the same agent. Use the `as:` parameter on `for()` to identify who is sending each message.

```php
// User A sends a message
Atlas::agent('team-assistant')
    ->for($team, as: $userA)
    ->forConversation($conversationId)
    ->message('Can someone review the Q4 report?')
    ->asText();

// User B sends a message in the same thread
Atlas::agent('team-assistant')
    ->for($team, as: $userB)
    ->forConversation($conversationId)
    ->message('I can take a look at it.')
    ->asText();
```

The agent sees both messages as `user` role in the conversation history. The `as:` parameter tracks ownership in the database (the `owner` relationship on the message model) so your application can display who said what, but from the agent's perspective they are all user messages in a single thread.

### How Role Remapping Works

When multiple agents share a conversation, Atlas remaps roles before sending history to the provider. Each agent gets a perspective where it is the assistant and everyone else is a user.

| Message Source | What the Current Agent Sees | Example |
|---|---|---|
| Current agent's messages | `assistant` role (unchanged) | `assistant: "I've processed the refund."` |
| Other agent's messages | `user` role with `[AgentName]:` prefix | `user: "[Support]: The customer wants a refund."` |
| Other agent's tool results | `user` role with `[AgentName tool:name]:` prefix | `user: "[Support tool:lookup_order]: Order #123 is eligible."` |
| User messages | `user` role (unchanged) | `user: "I need a refund for order #123"` |
| System messages | `system` role (unchanged) | Always passed through as-is |

This remapping happens transparently in the persistence layer when loading conversation history. The original messages in the database are unchanged — only the view sent to the provider is transformed.

::: tip Why Remap?
AI providers expect a strict alternating pattern of `user` and `assistant` messages. Without remapping, a conversation with two agents would have multiple consecutive `assistant` messages, which most providers reject or handle poorly. Remapping ensures every agent gets a valid message history from its own perspective.
:::

## Database Schema

For the complete database schema including all tables, columns, and relationships, see the [Persistence Reference](/advanced/persistence).

## API Reference

### AgentRequest Methods

| Method | Description |
|--------|-------------|
| `->for(Model $owner, ?Model $as = null)` | Set conversation owner. Optionally pass `as:` to set a different message sender |
| `->forConversation(int $id)` | Join an existing conversation |
| `->asUser(Model $owner)` | *(Deprecated)* Use `for($owner, as: $user)` instead |
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
| `queueMessage($conversation, $message, $owner)` | `Message` | Queue a message for later |
| `deliverNextQueued($conversation)` | `?Message` | Process next queued message |

## Next Steps

- [Agents](/features/agents) — Agent configuration and usage
- [Middleware](/features/middleware) — Add middleware to agent execution
