# Persistence Reference

Atlas persistence is an optional layer that tracks conversations, executions, assets, and agent memory. When enabled, Atlas automatically records every AI interaction with full observability.

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
| `atlas_messages` | Individual messages within conversations |
| `atlas_message_attachments` | Links messages to generated files (images, audio, etc.) |
| `atlas_executions` | Every AI provider call — agent or direct — with tokens and timing |
| `atlas_execution_steps` | Each round trip in the agent tool loop |
| `atlas_execution_tool_calls` | Individual tool invocations with arguments and results |
| `atlas_assets` | Generated files stored on disk with content hashing |
| `atlas_memories` | Agent memory entries for long-term context |
| `atlas_voice_calls` | Voice call sessions with complete transcripts |

## Conversations

**What it stores:** A thread of messages between one or more users and one or more agents, scoped to an owner model.

**Why it exists:** Agents need conversation history to maintain context across multiple interactions. The conversation record ties messages to an owner (user, team, or any Eloquent model) and tracks metadata like the title and which agent manages the thread.

| Column | Type | Why |
|--------|------|-----|
| `id` | `bigint` | Primary key |
| `owner_type` | `string` | Polymorphic — the model that owns this conversation (User, Team, etc.) |
| `owner_id` | `bigint` | Polymorphic — the owner's ID |
| `agent` | `string nullable` | Which agent manages this conversation. Allows one owner to have separate conversations with different agents |
| `title` | `string nullable` | Auto-generated from the first user message. Useful for conversation lists in a UI |
| `metadata` | `json` | Consumer-provided metadata from `withMeta()`. Stored on the conversation for app-specific context |
| `created_at` | `timestamp` | When the conversation started |
| `deleted_at` | `timestamp nullable` | Soft delete — conversations are never hard-deleted |

## Messages

**What it stores:** Every message in a conversation — user inputs, assistant responses, and system messages.

**Why it exists:** Messages are the core of conversation persistence. They store the full thread, support retry/sibling branching via `parent_id` and `is_active`, enable multi-agent conversations via the `agent` column, and link to execution data for tool call reconstruction.

| Column | Type | Why |
|--------|------|-----|
| `id` | `bigint` | Primary key |
| `conversation_id` | `bigint` | FK → conversations. Which conversation this message belongs to |
| `parent_id` | `bigint nullable` | FK → messages (self-reference). Links assistant responses to the user message they answer. Enables sibling tracking — multiple retry responses share the same parent |
| `step_id` | `bigint nullable` | FK → execution_steps. Links assistant messages to their execution step so tool calls can be reconstructed when loading history |
| `role` | `string` | `user`, `assistant`, or `system`. Determines how the message is sent to the AI provider |
| `status` | `string` | `delivered` (normal) or `queued` (waiting to be processed). Enables message queuing for rate limiting |
| `author_type` | `string nullable` | Polymorphic — who sent this message (User model, etc.). Separate from `role` because multiple users can send `user` role messages |
| `author_id` | `bigint nullable` | Polymorphic — the author's ID |
| `agent` | `string nullable` | Which agent authored this message. Enables multi-agent conversations where different agents respond in the same thread |
| `content` | `text` | The message text |
| `sequence` | `int` | Ordering within the conversation. Unique per conversation — ensures consistent message order |
| `is_active` | `boolean` | Controls visibility in conversation history. When you retry a response, the old one is deactivated (`false`) and only the active sibling appears in future history loads |
| `read_at` | `timestamp nullable` | When the message was read. Enables unread message counts and read receipts |
| `metadata` | `json` | Additional metadata |
| `embedding` | `vector nullable` | PostgreSQL only — vector embedding for semantic search over message history |

## Message Attachments

**What it stores:** Links between messages and generated assets (images, audio files, etc.).

**Why it exists:** When a tool generates an image or audio file during an agent execution, the asset needs to be associated with the assistant message so a UI can display it inline.

| Column | Type | Why |
|--------|------|-----|
| `id` | `bigint` | Primary key |
| `message_id` | `bigint` | FK → messages. The message this asset is attached to |
| `asset_id` | `bigint` | FK → assets. The generated file |
| `metadata` | `json` | Attachment context — which tool produced it, tool call ID |

## Executions

**What it stores:** A record of every AI provider interaction — both agent executions and direct modality calls (images, audio, etc.).

**Why it exists:** Full observability. Every call to an AI provider is tracked with the provider, model, token counts, timing, and status. This is the audit trail for cost tracking, debugging, and monitoring. Agent executions link to their conversation and message; direct calls are standalone.

| Column | Type | Why |
|--------|------|-----|
| `id` | `bigint` | Primary key |
| `conversation_id` | `bigint nullable` | FK → conversations. Set when the execution is part of a conversation. Null for standalone direct calls |
| `message_id` | `bigint nullable` | FK → messages. The assistant message this execution produced. Set after the message is stored |
| `asset_id` | `bigint nullable` | FK → assets. The asset this execution produced (image, audio, video). Set for file-producing modalities |
| `agent` | `string nullable` | Agent key. Null for direct modality calls |
| `type` | `string` | What type of execution: `text`, `stream`, `structured`, `image`, `audio`, `video`, `embed`, `moderate`, `rerank` |
| `provider` | `string` | Which provider was called (`openai`, `anthropic`, etc.) |
| `model` | `string` | Which model was used (`gpt-4o`, `claude-sonnet-4-20250514`, etc.) |
| `status` | `string` | Lifecycle state: `pending` → `processing` → `completed` or `failed`. Queued executions also pass through `queued` |
| `total_input_tokens` | `int` | Sum of input tokens across all steps. For single-step calls, this is the total prompt tokens |
| `total_output_tokens` | `int` | Sum of output tokens across all steps |
| `error` | `text nullable` | Error message when execution fails. Includes the exception message for debugging |
| `metadata` | `json nullable` | Consumer metadata from `withMeta()` |
| `started_at` | `timestamp nullable` | When the execution began processing |
| `completed_at` | `timestamp nullable` | When the execution finished (success or failure) |
| `duration_ms` | `int nullable` | Wall-clock duration in milliseconds. Measured from `beginExecution()` to `completeExecution()` |

## Execution Steps

**What it stores:** Each round trip in the agent's tool call loop.

**Why it exists:** An agent execution may involve multiple calls to the AI provider — the model responds, calls tools, gets results, and responds again. Each of these round trips is a step. Steps record the model's response at each point, the tokens consumed, and the finish reason (did it stop, or does it want to call more tools?).

| Column | Type | Why |
|--------|------|-----|
| `id` | `bigint` | Primary key |
| `execution_id` | `bigint` | FK → executions. Which execution this step belongs to |
| `sequence` | `int` | Step number (1, 2, 3...). Represents the round-trip order in the tool loop |
| `status` | `string` | `pending` → `processing` → `completed` or `failed` |
| `content` | `text nullable` | The model's response text at this step. May be an intermediate response before tool calls, or the final response |
| `reasoning` | `text nullable` | Reasoning/thinking content from models that support it (e.g. Anthropic extended thinking, OpenAI o-series) |
| `input_tokens` | `int` | Tokens in this step's prompt (grows with each step as conversation history accumulates) |
| `output_tokens` | `int` | Tokens generated by the model at this step |
| `finish_reason` | `string nullable` | Why the model stopped: `stop` (done), `tool_calls` (wants to call tools), `length` (hit token limit), `content_filter` (blocked) |
| `error` | `text nullable` | Error message if this step failed |
| `started_at` | `timestamp nullable` | |
| `completed_at` | `timestamp nullable` | |
| `duration_ms` | `int nullable` | How long this provider call took |

## Execution Tool Calls

**What it stores:** Each individual tool invocation within a step.

**Why it exists:** When the model requests tool calls, each tool runs independently. This table records what tool was called, what arguments it received, what it returned, and how long it took. Essential for debugging tool behavior and understanding the agent's decision-making process.

| Column | Type | Why |
|--------|------|-----|
| `id` | `bigint` | Primary key |
| `execution_id` | `bigint` | FK → executions. Top-level execution reference for fast querying |
| `step_id` | `bigint` | FK → execution_steps. Which step triggered this tool call |
| `tool_call_id` | `string nullable` | The provider's unique ID for this tool call (used to match results back to requests) |
| `name` | `string` | Tool name (e.g. `lookup_order`, `web_search`) |
| `type` | `string` | `atlas` for user-defined tools, `provider` for native provider tools (web search, code interpreter) |
| `status` | `string` | `pending` → `processing` → `completed` or `failed` |
| `arguments` | `json` | The arguments the model passed to the tool. Stored as JSON for inspection |
| `result` | `text nullable` | The serialized return value from the tool. What was sent back to the model |
| `started_at` | `timestamp nullable` | |
| `completed_at` | `timestamp nullable` | |
| `duration_ms` | `int nullable` | How long the tool took to execute |
| `metadata` | `json nullable` | Additional context |

## Assets

**What it stores:** Generated files — images, audio, video — stored on disk with content hashing for deduplication.

**Why it exists:** When Atlas generates an image, audio clip, or video, the binary content is stored on a configured disk (local, S3, etc.) and tracked in this table. Assets can be linked to executions (which call produced them) and to messages (for display in a conversation UI).

| Column | Type | Why |
|--------|------|-----|
| `id` | `bigint` | Primary key |
| `type` | `string` | Asset type: `image`, `audio`, `video`, `document` |
| `mime_type` | `string nullable` | MIME type (e.g. `image/png`, `audio/mpeg`) |
| `filename` | `string` | Generated filename (UUID-based) |
| `original_filename` | `string nullable` | Original filename if uploaded |
| `path` | `string` | Storage path on disk |
| `disk` | `string` | Laravel filesystem disk name |
| `size_bytes` | `int` | File size in bytes |
| `content_hash` | `string` | SHA-256 hash of the content. Enables deduplication |
| `description` | `text nullable` | Optional description |
| `author_type` | `string nullable` | Polymorphic — who generated this asset |
| `author_id` | `bigint nullable` | |
| `agent` | `string nullable` | Which agent generated this asset |
| `execution_id` | `bigint nullable` | FK → executions. Which execution produced this asset |
| `metadata` | `json nullable` | Additional context (tool_call_id, tool_name, provider, model) |

## Memories

**What it stores:** Long-term memory entries for agents — facts, documents, and contextual information that persists across conversations.

**Why it exists:** Agents can remember information between conversations. Memories are scoped to an owner (user) and optionally an agent. They support semantic search via vector embeddings (PostgreSQL) for retrieval-augmented generation (RAG) patterns.

| Column | Type | Why |
|--------|------|-----|
| `id` | `bigint` | Primary key |
| `memoryable_type` | `string nullable` | Polymorphic — the owner of this memory (User, etc.) |
| `memoryable_id` | `bigint nullable` | |
| `agent` | `string nullable` | Scoped to a specific agent, or null for shared across agents |
| `type` | `string` | Memory type (e.g. `fact`, `document`, `preference`) |
| `namespace` | `string nullable` | Optional grouping (e.g. `user_preferences`, `project_notes`) |
| `key` | `string nullable` | Unique key for named documents (upsert support) |
| `content` | `text` | The memory content |
| `importance` | `float` | Importance score (default 0.5). Higher scores surface first in recall |
| `source` | `string nullable` | How this memory was created (e.g. `tool`, `agent`, `manual`) |
| `last_accessed_at` | `timestamp nullable` | When this memory was last recalled. Enables decay-based retrieval |
| `expires_at` | `timestamp nullable` | Optional expiration for time-limited memories |
| `metadata` | `json nullable` | Additional context |
| `embedding` | `vector nullable` | PostgreSQL only — vector embedding for semantic search |

## Relationships

```
Conversation
├── has many Messages
├── has many Executions
└── belongs to Owner (polymorphic)

Message
├── belongs to Conversation
├── has many siblings (same parent_id)
├── has many responses (children where parent_id = this.id)
├── has many MessageAttachments
└── belongs to Author (polymorphic)

Execution
├── belongs to Conversation (optional)
├── belongs to Message (optional)
├── has many ExecutionSteps
└── has one Asset (optional)

ExecutionStep
├── belongs to Execution
└── has many ExecutionToolCalls

ExecutionToolCall
├── belongs to Execution
└── belongs to ExecutionStep

Asset
├── has many MessageAttachments
└── belongs to Execution (optional)

Memory
└── belongs to Owner (polymorphic)
```

## Models

All persistence models live in the `Atlasphp\Atlas\Persistence\Models` namespace.

### Conversation

`Atlasphp\Atlas\Persistence\Models\Conversation`

A conversation thread owned by a polymorphic model (User, Team, etc.).

| Relationship | Type | Description |
|-------------|------|-------------|
| `owner()` | `MorphTo` | The owning model |
| `messages()` | `HasMany` | All messages in the conversation |
| `executions()` | `HasMany` | All executions linked to this conversation |

| Method | Description |
|--------|-------------|
| `recentMessages(int $limit)` | Get the last N active, delivered messages |
| `nextSequence()` | Get the next message sequence number |

| Scope | Description |
|-------|-------------|
| `forOwner(Model $owner)` | Filter by polymorphic owner |
| `forAgent(string $agent)` | Filter by agent key |

### Message

`Atlasphp\Atlas\Persistence\Models\Message`

A single message in a conversation — user input, assistant response, or system message. Supports sibling branching for retry/regenerate.

| Relationship | Type | Description |
|-------------|------|-------------|
| `conversation()` | `BelongsTo` | Parent conversation |
| `parent()` | `BelongsTo` | The message this is a response to |
| `siblings()` | `HasMany` | All messages sharing the same parent (retry alternatives) |
| `responses()` | `HasMany` | Child messages (responses to this message) |
| `attachments()` | `HasMany` | Linked file assets (images, audio, documents) |
| `step()` | `BelongsTo` | Linked execution step (for tool call reconstruction) |

| Method | Description |
|--------|-------------|
| `canRetry()` | Whether this message can be retried (no later user message exists) |
| `siblingGroups()` | Group siblings by execution (multi-step responses stay together) |
| `siblingIndex()` | 1-based index in the sibling list |
| `markAsRead()` | Set `read_at` to now |
| `toAtlasMessage()` | Convert to a typed message object for the provider |

| Scope | Description |
|-------|-------------|
| `active()` | Only active messages (`is_active = true`) |
| `byAgent(string $agent)` | Filter by agent key |
| `byAuthor(Model $author)` | Filter by polymorphic author |
| `read()` / `unread()` | Filter by read status |
| `delivered()` / `queued()` | Filter by delivery status |

### Execution

`Atlasphp\Atlas\Persistence\Models\Execution`

A tracked AI provider call — agent execution or direct modality call — with tokens, timing, and status.

| Relationship | Type | Description |
|-------------|------|-------------|
| `conversation()` | `BelongsTo` | Linked conversation (null for standalone calls) |
| `triggerMessage()` | `BelongsTo` | The assistant message this execution produced |
| `steps()` | `HasMany` | Round trips in the tool call loop |
| `toolCalls()` | `HasMany` | All tool invocations across all steps |
| `asset()` | `BelongsTo` | Generated file (image, audio, video) |

| Method | Description |
|--------|-------------|
| `markQueued()` | Transition to queued status |
| `markCompleted(?int $durationMs)` | Transition to completed with duration |
| `markFailed(string $error, ?int $durationMs)` | Transition to failed with error |

### ExecutionStep

`Atlasphp\Atlas\Persistence\Models\ExecutionStep`

A single round trip in the agent's tool call loop — one provider call and its response.

| Relationship | Type | Description |
|-------------|------|-------------|
| `execution()` | `BelongsTo` | Parent execution |
| `toolCalls()` | `HasMany` | Tool calls made during this step |

| Method | Description |
|--------|-------------|
| `recordResponse(...)` | Record the provider response (content, tokens, finish reason) |
| `markCompleted(?int $durationMs)` | Transition to completed |
| `markFailed(string $error, ?int $durationMs)` | Transition to failed |
| `hasToolCalls()` | Whether this step triggered tool calls |
| `totalTokens` | Attribute: input + output tokens |

| Scope | Description |
|-------|-------------|
| `pending()` / `processing()` / `completed()` | Filter by status |

### ExecutionToolCall

`Atlasphp\Atlas\Persistence\Models\ExecutionToolCall`

An individual tool invocation with arguments, result, and timing.

| Relationship | Type | Description |
|-------------|------|-------------|
| `execution()` | `BelongsTo` | Parent execution |
| `step()` | `BelongsTo` | The step that triggered this call |

| Method | Description |
|--------|-------------|
| `markCompleted(string $result, int $durationMs)` | Record result and complete |
| `markFailed(string $error, int $durationMs)` | Record error and fail |

| Scope | Description |
|-------|-------------|
| `pending()` / `processing()` / `completed()` / `failed()` | Filter by status |
| `forTool(string $name)` | Filter by tool name |

### Asset

`Atlasphp\Atlas\Persistence\Models\Asset`

A stored file (image, audio, video, document) with content hashing for deduplication.

| Relationship | Type | Description |
|-------------|------|-------------|
| `attachments()` | `HasMany` | Messages this asset is attached to |
| `execution()` | `BelongsTo` | The execution that produced this asset |

| Method | Description |
|--------|-------------|
| `url(string $prefix)` | Generate a URL for this asset |
| `extension()` | Get the file extension |
| `isMedia()` | Whether this is an image, audio, or video |

| Scope | Description |
|-------|-------------|
| `forExecution(int $executionId)` | Filter by execution |

### MessageAttachment

`Atlasphp\Atlas\Persistence\Models\MessageAttachment`

Join model linking a message to an asset. Carries metadata about which tool produced it.

| Relationship | Type | Description |
|-------------|------|-------------|
| `message()` | `BelongsTo` | The message |
| `asset()` | `BelongsTo` | The asset |

### Memory

`Atlasphp\Atlas\Persistence\Models\Memory`

A persistent memory entry scoped to an owner and/or agent. Supports vector embeddings for semantic search (PostgreSQL).

| Relationship | Type | Description |
|-------------|------|-------------|
| `memoryable()` | `MorphTo` | The owner of this memory |

| Scope | Description |
|-------|-------------|
| `forOwner(Model $owner)` | Filter by polymorphic owner |
| `global()` | Memories without an owner |
| `forAgent(string $agent)` | Scoped to a specific agent |
| `agentAgnostic()` | Shared across agents |
| `ofType(string $type)` | Filter by memory type |
| `inNamespace(string $namespace)` | Filter by namespace |
| `active()` | Non-expired memories |
| `expired()` | Past expiration date |

### VoiceCall

`Atlasphp\Atlas\Persistence\Models\VoiceCall`

A complete voice call session with its transcript stored as a JSON array. Voice transcripts are isolated from the messages table — they live here. Consumers listen for `VoiceCallCompleted` to post-process transcripts (create summaries, embed into memory, generate conversation messages).

| Column | Type | Description |
|--------|------|-------------|
| `voice_session_id` | `string` | Unique session ID from provider |
| `conversation_id` | `bigint nullable` | FK → conversations |
| `agent` | `string nullable` | Agent key |
| `provider` | `string` | Provider name |
| `model` | `string` | Model name |
| `status` | `string` | `active`, `completed`, `failed` |
| `transcript` | `json` | `[{role: 'user'\|'assistant', content: string}]` |
| `summary` | `text nullable` | Consumer-generated summary |
| `duration_ms` | `int nullable` | Wall-clock duration |
| `metadata` | `json nullable` | Custom metadata |

The `executions` table has a `voice_call_id` FK pointing to this table (same pattern as `message_id` for text executions).

| Relationship | Type | Description |
|-------------|------|-------------|
| `conversation()` | `BelongsTo` | Linked conversation |
| `executions()` | `HasMany` | Executions linked to this call (via `executions.voice_call_id`) |

| Method | Description |
|--------|-------------|
| `saveTranscript(array $turns)` | Atomically replace transcript |
| `markCompleted(array $turns)` | Complete with final transcript and duration |
| `markFailed()` | Mark as failed |

| Scope | Description |
|-------|-------------|
| `forConversation(int $id)` | Filter by conversation |
| `forSession(string $sessionId)` | Filter by session ID |
| `active()` | Only active calls |
| `completed()` | Only completed calls |

## Model Overrides

Extend the base models with your own:

```php
// config/atlas.php → persistence.models
'models' => [
    'conversation'        => \App\Models\AtlasConversation::class,
    'message'             => \App\Models\AtlasMessage::class,
    'execution'           => \App\Models\AtlasExecution::class,
    'execution_step'      => \App\Models\AtlasExecutionStep::class,
    'execution_tool_call' => \App\Models\AtlasExecutionToolCall::class,
    'asset'               => \App\Models\AtlasAsset::class,
    'memory'              => \App\Models\AtlasMemory::class,
    'voice_call'          => \App\Models\AtlasVoiceCall::class,
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
    'memory_auto_embed' => env('ATLAS_MEMORY_AUTO_EMBED', true),
],
```

| Option | Default | Purpose |
|--------|---------|---------|
| `enabled` | `false` | Enable persistence globally |
| `table_prefix` | `atlas_` | Prefix for all persistence tables |
| `message_limit` | `50` | Default conversation history limit |
| `auto_store_assets` | `true` | Automatically store generated files |
| `memory_auto_embed` | `true` | Auto-generate embeddings for memories on create/update |
