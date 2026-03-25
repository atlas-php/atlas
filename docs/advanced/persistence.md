# Persistence Reference

Atlas persistence is an optional layer that tracks conversations, executions, and assets. When enabled, Atlas automatically records every AI interaction with full observability.

## Setup

```env
ATLAS_PERSISTENCE_ENABLED=true
```

```bash
php artisan vendor:publish --tag=atlas-migrations
php artisan migrate
```

## Tables Overview

All tables are prefixed with `atlas_` by default (configurable via `persistence.table_prefix` in `config/atlas.php`).

| Table | Purpose |
|-------|---------|
| `atlas_conversations` | Conversation threads between users and agents |
| `atlas_conversation_messages` | Individual messages within conversations |
| `atlas_conversation_message_assets` | Links messages to generated files (images, audio, etc.) |
| `atlas_executions` | Every AI provider call — agent or direct — with tokens and timing |
| `atlas_execution_steps` | Each round trip in the agent tool loop |
| `atlas_execution_tool_calls` | Individual tool invocations with arguments and results |
| `atlas_assets` | Generated files stored on disk with tool call linkage |
| `atlas_conversation_voice_calls` | Voice call sessions with complete transcripts |

## Conversations

**What it stores:** A thread of messages between one or more users and one or more agents, scoped to an owner model.

**Why it exists:** Agents need conversation history to maintain context across multiple interactions. The conversation record ties messages to an owner (user, team, or any Eloquent model) and tracks metadata like the title and which agent manages the thread.

| Column | Type | Why |
|--------|------|-----|
| `id` | `bigint` | Primary key |
| `owner_type` | `string(255) nullable` | Polymorphic — the model that owns this conversation (User, Team, etc.) |
| `owner_id` | `unsignedBigInteger nullable` | Polymorphic — the owner's ID |
| `agent` | `string(255) nullable` | Which agent manages this conversation. Allows one owner to have separate conversations with different agents |
| `title` | `string(255) nullable` | Auto-generated from the first user message. Useful for conversation lists in a UI |
| `summary` | `text nullable` | Consumer-provided or auto-generated summary of the conversation |
| `metadata` | `json nullable` | Consumer-provided metadata from `withMeta()`. Stored on the conversation for app-specific context |
| `created_at` | `timestamp` | When the conversation started |
| `updated_at` | `timestamp` | When the conversation was last updated |
| `deleted_at` | `timestamp nullable` | Soft delete — conversations are never hard-deleted |

## Messages

**What it stores:** Every message in a conversation — user inputs, assistant responses, and system messages.

**Why it exists:** Messages are the core of conversation persistence. They store the full thread, support retry/sibling branching via `parent_id` and `is_active`, enable multi-agent conversations via the `agent` column, and link to execution data for tool call reconstruction.

| Column | Type | Why |
|--------|------|-----|
| `id` | `bigint` | Primary key |
| `conversation_id` | `bigint` | FK → conversations. Which conversation this message belongs to |
| `parent_id` | `bigint nullable` | FK → conversation_messages (self-reference). Links assistant responses to the user message they answer. Enables sibling tracking — multiple retry responses share the same parent |
| `step_id` | `unsignedBigInteger nullable` | FK → execution_steps. Links assistant messages to their execution step so tool calls can be reconstructed when loading history |
| `execution_id` | `unsignedBigInteger nullable` | FK → executions. Links this message to the execution that produced it |
| `owner_type` | `string nullable` | Polymorphic — who sent this message (User model, etc.). Separate from `role` because multiple users can send `user` role messages |
| `owner_id` | `unsignedBigInteger nullable` | Polymorphic — the owner's ID |
| `agent` | `string(255) nullable` | Which agent authored this message. Enables multi-agent conversations where different agents respond in the same thread |
| `role` | `string(20)` | `user`, `assistant`, or `system` (backed by `MessageRole` enum). Determines how the message is sent to the AI provider |
| `status` | `string(20)` | `delivered` (normal), `queued` (waiting to be processed), or `failed` (backed by `MessageStatus` enum) |
| `content` | `text nullable` | The message text |
| `sequence` | `unsignedInteger` | Ordering within the conversation (starts at 1). Unique per conversation — ensures consistent message order |
| `is_active` | `boolean` | Controls visibility in conversation history. When you retry a response, the old one is deactivated (`false`) and only the active sibling appears in future history loads |
| `read_at` | `timestamp nullable` | When the message was read. Enables unread message counts and read receipts |
| `metadata` | `json nullable` | Additional metadata |
| `embedding` | `vector nullable` | PostgreSQL only — vector embedding for semantic search over message history |
| `embedding_at` | `timestamp nullable` | PostgreSQL only — when the embedding was generated |
| `created_at` | `timestamp` | When the message was created |
| `updated_at` | `timestamp` | When the message was last updated |

## Message Attachments

**What it stores:** Links between messages and generated assets (images, audio files, etc.).

**Why it exists:** When a tool generates an image or audio file during an agent execution, the asset needs to be associated with the assistant message so a UI can display it inline.

| Column | Type | Why |
|--------|------|-----|
| `id` | `bigint` | Primary key |
| `message_id` | `bigint` | FK → conversation_messages. The message this asset is attached to |
| `asset_id` | `bigint` | FK → assets. The generated file |
| `metadata` | `json nullable` | Attachment context — which tool produced it, tool call ID |
| `created_at` | `timestamp nullable` | When the attachment was created |

## Executions

**What it stores:** A record of every AI provider interaction — both agent executions and direct modality calls (images, audio, etc.).

**Why it exists:** Full observability. Every call to an AI provider is tracked with the provider, model, token counts, timing, and status. This is the audit trail for cost tracking, debugging, and monitoring. Messages and voice calls link back to their execution via `execution_id`.

| Column | Type | Why |
|--------|------|-----|
| `id` | `bigint` | Primary key |
| `conversation_id` | `bigint nullable` | FK → conversations. Set when the execution is part of a conversation. Null for standalone direct calls |
| `status` | `unsignedTinyInteger` | Lifecycle state as int-backed `ExecutionStatus` enum: `0` (Pending) → `1` (Queued) → `2` (Processing) → `3` (Completed) or `4` (Failed) |
| `agent` | `string(255) nullable` | Agent key. Null for direct modality calls |
| `type` | `string(30)` | What type of execution, backed by `ExecutionType` enum: `text`, `structured`, `stream`, `image`, `image_to_text`, `audio`, `audio_to_text`, `video`, `video_to_text`, `music`, `sfx`, `speech`, `embed`, `moderate`, `rerank`, `voice` |
| `provider` | `string(50)` | Which provider was called (`openai`, `anthropic`, etc.) |
| `model` | `string(100)` | Which model was used (`gpt-4o`, `claude-sonnet-4-20250514`, etc.) |
| `usage` | `json nullable` | Token usage data: `{input_tokens, output_tokens, reasoning_tokens?, cached_tokens?, cache_write_tokens?}` |
| `error` | `text nullable` | Error message when execution fails. Includes the exception message for debugging |
| `metadata` | `json nullable` | Consumer metadata from `withMeta()` |
| `started_at` | `timestamp nullable` | When the execution began processing |
| `completed_at` | `timestamp nullable` | When the execution finished (success or failure) |
| `duration_ms` | `unsignedInteger nullable` | Wall-clock duration in milliseconds |
| `created_at` | `timestamp` | When the execution record was created |
| `updated_at` | `timestamp` | When the execution record was last updated |

## Execution Steps

**What it stores:** Each round trip in the agent's tool call loop.

**Why it exists:** An agent execution may involve multiple calls to the AI provider — the model responds, calls tools, gets results, and responds again. Each of these round trips is a step. Steps record the model's response at each point and the finish reason (did it stop, or does it want to call more tools?).

| Column | Type | Why |
|--------|------|-----|
| `id` | `bigint` | Primary key |
| `execution_id` | `bigint` | FK → executions. Which execution this step belongs to |
| `sequence` | `unsignedInteger` | Step number (1, 2, 3...). Represents the round-trip order in the tool loop |
| `status` | `unsignedTinyInteger` | Int-backed `ExecutionStatus` enum: `0` (Pending) → `2` (Processing) → `3` (Completed) or `4` (Failed) |
| `content` | `text nullable` | The model's response text at this step. May be an intermediate response before tool calls, or the final response |
| `reasoning` | `text nullable` | Reasoning/thinking content from models that support it (e.g. Anthropic extended thinking, OpenAI o-series) |
| `finish_reason` | `string(30) nullable` | Why the model stopped: `stop` (done), `tool_calls` (wants to call tools), `length` (hit token limit), `content_filter` (blocked) |
| `error` | `text nullable` | Error message if this step failed |
| `metadata` | `json nullable` | Additional context |
| `started_at` | `timestamp nullable` | When this step started |
| `completed_at` | `timestamp nullable` | When this step completed |
| `duration_ms` | `unsignedInteger nullable` | How long this provider call took |
| `created_at` | `timestamp` | When the step record was created |
| `updated_at` | `timestamp` | When the step record was last updated |

## Execution Tool Calls

**What it stores:** Each individual tool invocation within a step.

**Why it exists:** When the model requests tool calls, each tool runs independently. This table records what tool was called, what arguments it received, what it returned, and how long it took. Essential for debugging tool behavior and understanding the agent's decision-making process.

| Column | Type | Why |
|--------|------|-----|
| `id` | `bigint` | Primary key |
| `execution_id` | `bigint` | FK → executions. Top-level execution reference for fast querying |
| `step_id` | `bigint nullable` | FK → execution_steps. Which step triggered this tool call |
| `tool_call_id` | `string(100)` | The provider's unique ID for this tool call (used to match results back to requests) |
| `status` | `unsignedTinyInteger` | Int-backed `ExecutionStatus` enum: `0` (Pending) → `2` (Processing) → `3` (Completed) or `4` (Failed) |
| `name` | `string(100)` | Tool name (e.g. `lookup_order`, `web_search`) |
| `type` | `string(20)` | `local` for user-defined tools, `mcp` for MCP tools, `provider` for native provider tools (backed by `ToolCallType` enum) |
| `arguments` | `json nullable` | The arguments the model passed to the tool. Stored as JSON for inspection |
| `result` | `text nullable` | The serialized return value from the tool. What was sent back to the model |
| `started_at` | `timestamp nullable` | When tool execution started |
| `completed_at` | `timestamp nullable` | When tool execution completed |
| `duration_ms` | `unsignedInteger nullable` | How long the tool took to execute |
| `metadata` | `json nullable` | Additional context |
| `created_at` | `timestamp` | When the record was created |
| `updated_at` | `timestamp` | When the record was last updated |

## Assets

**What it stores:** Generated files — images, audio, video — stored on disk with optional vector embeddings for semantic search.

**Why it exists:** When Atlas generates an image, audio clip, or video, the binary content is stored on a configured disk (local, S3, etc.) and tracked in this table. Assets can be linked to executions (which call produced them), to tool calls (which tool generated them), and to messages (for display in a conversation UI).

| Column | Type | Why |
|--------|------|-----|
| `id` | `bigint` | Primary key |
| `execution_id` | `unsignedBigInteger nullable` | FK → executions. Which execution produced this asset |
| `tool_call_id` | `unsignedBigInteger nullable` | FK → execution_tool_calls. Which tool call generated this asset. Null for direct modality calls and user uploads |
| `owner_type` | `string nullable` | Polymorphic — who generated this asset. Derived from the execution's conversation owner |
| `owner_id` | `unsignedBigInteger nullable` | Polymorphic — the owner's ID |
| `agent` | `string(255) nullable` | Which agent generated this asset |
| `type` | `string(20)` | Asset type backed by `AssetType` enum: `image`, `audio`, `video`, `document`, `text`, `json`, `file` |
| `mime_type` | `string(100) nullable` | MIME type (e.g. `image/png`, `audio/mpeg`) |
| `filename` | `string(255)` | Generated filename (UUID-based) |
| `path` | `string(500)` | Storage path on disk |
| `disk` | `string(50)` | Laravel filesystem disk name |
| `size_bytes` | `unsignedBigInteger nullable` | File size in bytes |
| `description` | `text nullable` | Optional description |
| `metadata` | `json nullable` | Consumer-provided metadata only. No internal Atlas data is stored here |
| `created_at` | `timestamp` | When the asset was stored |
| `updated_at` | `timestamp` | When the asset record was last updated |
| `deleted_at` | `timestamp nullable` | Soft delete |
| `embedding` | `vector nullable` | PostgreSQL only — vector embedding for semantic search |
| `embedding_at` | `timestamp nullable` | PostgreSQL only — when the embedding was generated |

## Voice Calls

**What it stores:** A complete voice call session with its transcript stored as a JSON array. Voice transcripts are isolated from the messages table — they live here. Consumers listen for `VoiceCallCompleted` to post-process transcripts (create summaries, embed into memory, generate conversation messages).

**Table name:** `atlas_conversation_voice_calls`

| Column | Type | Why |
|--------|------|-----|
| `id` | `bigint` | Primary key |
| `conversation_id` | `bigint nullable` | FK → conversations |
| `execution_id` | `unsignedBigInteger nullable` | FK → executions. Links this voice call to its execution record |
| `voice_session_id` | `string(100)` | Unique session ID from provider |
| `owner_type` | `string nullable` | Polymorphic — who initiated this call |
| `owner_id` | `unsignedBigInteger nullable` | Polymorphic — the owner's ID |
| `agent` | `string(255) nullable` | Agent key |
| `provider` | `string(50)` | Provider name |
| `model` | `string(100)` | Model name |
| `status` | `string(20)` | `active`, `completed`, `failed` (backed by `VoiceCallStatus` enum) |
| `transcript` | `json nullable` | `[{role: 'user'|'assistant', content: string}]` |
| `summary` | `text nullable` | Consumer-generated summary |
| `duration_ms` | `unsignedInteger nullable` | Wall-clock duration |
| `metadata` | `json nullable` | Custom metadata |
| `started_at` | `timestamp nullable` | When the voice session started |
| `completed_at` | `timestamp nullable` | When the voice session ended |
| `created_at` | `timestamp` | When the record was created |
| `updated_at` | `timestamp` | When the record was last updated |

## Relationships

```
Conversation
├── has many ConversationMessages
├── has many Executions
└── belongs to Owner (polymorphic)

ConversationMessage
├── belongs to Conversation
├── belongs to Execution (optional)
├── belongs to ExecutionStep (via step_id, optional)
├── has many siblings (same parent_id)
├── has many responses (children where parent_id = this.id)
├── has many ConversationMessageAssets
└── belongs to Owner (polymorphic)

Execution
├── belongs to Conversation (optional)
├── has one ConversationMessage (via conversation_messages.execution_id)
├── has one VoiceCall (via conversation_voice_calls.execution_id)
├── has many ExecutionSteps
├── has many ExecutionToolCalls
└── has many Assets

ExecutionStep
├── belongs to Execution
└── has many ExecutionToolCalls

ExecutionToolCall
├── belongs to Execution
└── belongs to ExecutionStep

Asset
├── has many ConversationMessageAssets
├── belongs to Execution (optional)
└── belongs to ExecutionToolCall (optional, via tool_call_id)

VoiceCall
├── belongs to Conversation (optional)
└── belongs to Execution (optional)
```

## Models

All persistence models live in the `Atlasphp\Atlas\Persistence\Models` namespace.

### Conversation

`Atlasphp\Atlas\Persistence\Models\Conversation`

A conversation thread owned by a polymorphic model (User, Team, etc.).

| Relationship | Type | Description |
|-------------|------|-------------|
| `owner()` | `MorphTo` | The owning model |
| `messages()` | `HasMany → ConversationMessage` | All messages in the conversation, ordered by sequence |
| `executions()` | `HasMany → Execution` | All executions linked to this conversation |

| Method | Description |
|--------|-------------|
| `recentMessages(int $limit)` | Get the last N active, delivered messages |
| `nextSequence()` | Get the next message sequence number |

| Scope | Description |
|-------|-------------|
| `forOwner(Model $owner)` | Filter by polymorphic owner |
| `forAgent(string $agent)` | Filter by agent key |

### ConversationMessage

`Atlasphp\Atlas\Persistence\Models\ConversationMessage`

A single message in a conversation — user input, assistant response, or system message. Supports sibling branching for retry/regenerate.

| Relationship | Type | Description |
|-------------|------|-------------|
| `conversation()` | `BelongsTo → Conversation` | Parent conversation |
| `execution()` | `BelongsTo → Execution` | The execution that produced this message |
| `step()` | `BelongsTo → ExecutionStep` | Linked execution step (for tool call reconstruction) |
| `parent()` | `BelongsTo → self` | The message this is a response to |
| `siblings()` | `HasMany → self` | All messages sharing the same parent (retry alternatives) |
| `responses()` | `HasMany → self` | Child messages (responses to this message) |
| `assets()` | `HasMany → ConversationMessageAsset` | Linked file assets (images, audio, documents) |
| `owner()` | `MorphTo` | Who sent this message (from HasOwner trait) |

| Method | Description |
|--------|-------------|
| `toAtlasMessage()` | Convert to a typed message object for the provider |
| `toAtlasMessagesWithTools()` | Convert to AssistantMessage with tool calls reconstructed from the execution step |
| `ownerInfo()` | Get unified owner info array for UI rendering |
| `canRetry()` | Whether this message can be retried (no later user message exists) |
| `siblingGroups()` | Group siblings by execution (multi-step responses stay together) |
| `siblingCount()` | Number of sibling groups |
| `siblingIndex()` | 1-based index in the sibling list |
| `markAsRead()` | Set `read_at` to now |
| `markDelivered()` | Transition queued message to delivered |
| `isFromUser()` | Whether this is a user role message |
| `isFromAssistant()` | Whether this is an assistant role message |
| `isSystem()` | Whether this is a system role message |
| `isRead()` / `isUnread()` | Check read status |
| `isDelivered()` / `isQueued()` | Check delivery status |

| Scope | Description |
|-------|-------------|
| `active()` | Only active messages (`is_active = true`) |
| `read()` / `unread()` | Filter by read status |
| `delivered()` / `queued()` | Filter by delivery status |

### Execution

`Atlasphp\Atlas\Persistence\Models\Execution`

A tracked AI provider call — agent execution or direct modality call — with usage, timing, and status.

| Relationship | Type | Description |
|-------------|------|-------------|
| `conversation()` | `BelongsTo → Conversation` | Linked conversation (null for standalone calls) |
| `message()` | `HasOne → ConversationMessage` | The message this execution produced |
| `voiceCall()` | `HasOne → VoiceCall` | The voice call linked to this execution |
| `steps()` | `HasMany → ExecutionStep` | Round trips in the tool call loop, ordered by sequence |
| `toolCalls()` | `HasMany → ExecutionToolCall` | All tool invocations across all steps |
| `assets()` | `HasMany → Asset` | Generated files (images, audio, video) |

| Method | Description |
|--------|-------------|
| `markQueued()` | Transition to queued status |
| `markCompleted(?int $durationMs, ?Usage $usage)` | Transition to completed with duration and usage |
| `markFailed(string $error, ?int $durationMs, ?Usage $usage)` | Transition to failed with error and usage |
| `getUsageObject()` | Get the usage as a `Usage` DTO |
| `getTotalTokensAttribute()` | Accessor for total token count (input + output) |

| Scope | Description |
|-------|-------------|
| `pending()` / `processing()` / `completed()` / `failed()` | Filter by `ExecutionStatus` (from `HasExecutionStatus` trait) |
| `queued()` | Filter for queued executions |
| `forAgent(string $agent)` | Filter by agent key |
| `forProvider(string $provider)` | Filter by provider |
| `ofType(ExecutionType $type)` | Filter by execution type |
| `producedAssets()` | Filter to executions that have related assets |

### ExecutionStep

`Atlasphp\Atlas\Persistence\Models\ExecutionStep`

A single round trip in the agent's tool call loop — one provider call and its response.

| Relationship | Type | Description |
|-------------|------|-------------|
| `execution()` | `BelongsTo → Execution` | Parent execution |
| `toolCalls()` | `HasMany → ExecutionToolCall` | Tool calls made during this step |

| Method | Description |
|--------|-------------|
| `recordResponse(?string $content, ?string $reasoning, string $finishReason)` | Record the provider response (content, reasoning, finish reason) |
| `markCompleted(?int $durationMs)` | Transition to completed |
| `markFailed(string $error, ?int $durationMs)` | Transition to failed |
| `hasToolCalls()` | Whether this step triggered tool calls (finish reason is `tool_calls`) |

| Scope | Description |
|-------|-------------|
| `pending()` / `processing()` / `completed()` / `failed()` | Filter by `ExecutionStatus` (from `HasExecutionStatus` trait) |

### ExecutionToolCall

`Atlasphp\Atlas\Persistence\Models\ExecutionToolCall`

An individual tool invocation with arguments, result, and timing.

| Relationship | Type | Description |
|-------------|------|-------------|
| `execution()` | `BelongsTo → Execution` | Parent execution |
| `step()` | `BelongsTo → ExecutionStep` | The step that triggered this call |

| Method | Description |
|--------|-------------|
| `markCompleted(string $result, int $durationMs)` | Record result and complete |
| `markFailed(string $error, int $durationMs)` | Record error and fail |

| Scope | Description |
|-------|-------------|
| `pending()` / `processing()` / `completed()` / `failed()` | Filter by `ExecutionStatus` (from `HasExecutionStatus` trait) |
| `forTool(string $name)` | Filter by tool name |

### Asset

`Atlasphp\Atlas\Persistence\Models\Asset`

A stored file (image, audio, video, document) with optional vector embeddings for semantic search.

| Relationship | Type | Description |
|-------------|------|-------------|
| `messageAssets()` | `HasMany → ConversationMessageAsset` | Messages this asset is linked to |
| `execution()` | `BelongsTo → Execution` | The execution that produced this asset |
| `toolCall()` | `BelongsTo → ExecutionToolCall` | The tool call that generated this asset (null for direct calls and user uploads) |
| `owner()` | `MorphTo` | Who generated this asset (from HasOwner trait) |

| Method | Description |
|--------|-------------|
| `url(string $prefix)` | Generate a URL for this asset |
| `extension()` | Get the file extension |
| `isMedia()` | Whether this is an image, audio, or video |

| Scope | Description |
|-------|-------------|
| `forExecution(int $executionId)` | Filter by execution |

### ConversationMessageAsset

`Atlasphp\Atlas\Persistence\Models\ConversationMessageAsset`

Join model linking a message to an asset. Carries metadata about which tool produced it.

| Relationship | Type | Description |
|-------------|------|-------------|
| `message()` | `BelongsTo → ConversationMessage` | The message |
| `asset()` | `BelongsTo → Asset` | The asset |

### VoiceCall

`Atlasphp\Atlas\Persistence\Models\VoiceCall`

A complete voice call session with its transcript stored as a JSON array. Voice transcripts are isolated from the messages table — they live here. Consumers listen for `VoiceCallCompleted` to post-process transcripts (create summaries, embed into memory, generate conversation messages).

| Relationship | Type | Description |
|-------------|------|-------------|
| `conversation()` | `BelongsTo → Conversation` | Linked conversation |
| `execution()` | `BelongsTo → Execution` | The execution record for this voice call |
| `owner()` | `MorphTo` | Who initiated this call (from HasOwner trait) |

| Method | Description |
|--------|-------------|
| `saveTranscript(array $turns)` | Atomically replace transcript |
| `markCompleted(array $turns)` | Complete with final transcript and duration |
| `markFailed()` | Mark as failed |
| `isActive()` | Whether this call is currently active |
| `isCompleted()` | Whether this call has completed |

| Scope | Description |
|-------|-------------|
| `forConversation(int $id)` | Filter by conversation |
| `forSession(string $sessionId)` | Filter by session ID |
| `active()` | Only active calls |
| `completed()` | Only completed calls |

### Lifecycle

The framework handles lifecycle transitions automatically:

- **`active`** — Created when `asVoice()` is called. Transcript is checkpointed as turns complete.
- **`completed`** — Set when the browser sends a close request, or when the stale cleanup command runs. You don't need to call `markCompleted()` — the close endpoint does it.
- **`failed`** — Available for consumer use. Call `$voiceCall->markFailed()` in your error handling if needed.

### Querying

```php
use Atlasphp\Atlas\Persistence\Models\VoiceCall;

// All calls for a conversation
VoiceCall::forConversation($conversationId)->get();

// By provider session ID
VoiceCall::forSession('rt_xai_abc123...')->first();

// Recent completed calls with their execution
VoiceCall::completed()->with('execution.toolCalls')->latest()->take(10)->get();

// Active calls (still in progress)
VoiceCall::active()->get();
```

### Execution Relationship

The `conversation_voice_calls` table has an `execution_id` FK pointing to the executions table. This links the voice call to its execution record, which tracks tool calls made during the session:

```php
$call = VoiceCall::forSession($sessionId)->first();

// Get tool calls from the voice session via the execution
$toolCalls = $call->execution?->toolCalls;
```

## Model Overrides

Extend the base models with your own:

```php
// config/atlas.php → persistence.models
'models' => [
    'conversation'              => \App\Models\AtlasConversation::class,
    'conversation_message'      => \App\Models\AtlasMessage::class,
    'asset'                     => \App\Models\AtlasAsset::class,
    'conversation_message_asset' => \App\Models\AtlasMessageAsset::class,
    'execution'                 => \App\Models\AtlasExecution::class,
    'execution_step'            => \App\Models\AtlasExecutionStep::class,
    'execution_tool_call'       => \App\Models\AtlasExecutionToolCall::class,
    'voice_call'                => \App\Models\AtlasVoiceCall::class,
],
```

Your custom models must extend the corresponding Atlas base model.

## Configuration

```php
// config/atlas.php → persistence
'persistence' => [
    'enabled' => env('ATLAS_PERSISTENCE_ENABLED', false),
    'table_prefix' => env('ATLAS_TABLE_PREFIX', 'atlas_'),
    'message_limit' => (int) env('ATLAS_MESSAGE_LIMIT', 50),
    'auto_store_assets' => env('ATLAS_AUTO_STORE_ASSETS', true),
],
```

| Option | Default | Purpose |
|--------|---------|---------|
| `enabled` | `false` | Enable persistence globally |
| `table_prefix` | `atlas_` | Prefix for all persistence tables |
| `message_limit` | `50` | Default conversation history limit |
| `auto_store_assets` | `true` | Automatically store generated files |
