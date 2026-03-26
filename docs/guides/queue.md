# Queue & Background Jobs

Dispatch any Atlas request to a background queue with a single `->queue()` call. The consumer gets an execution ID immediately for UI tracking, while the job processes asynchronously.

## Basic Usage

Call `->queue()` before any terminal method. The terminal returns a `PendingExecution` instead of a response:

```php
$pending = Atlas::text('openai', 'gpt-4o')
    ->message('Write a long essay about AI')
    ->queue()
    ->asText();

$pending->executionId;  // Available immediately for UI display
```

The job dispatches automatically when `$pending` goes out of scope. Register callbacks before dispatch:

```php
Atlas::text('openai', 'gpt-4o')
    ->message('Write a long essay about AI')
    ->queue()
    ->asText()
    ->then(fn ($response) => logger()->info($response->text))
    ->catch(fn ($e) => logger()->error($e->getMessage()));
```

Every modality supports queuing: text, image, audio, video, embeddings, moderation, reranking, speech, music, sound effects, and agents.

## Configuration

The default queue name is set in `config/atlas.php`:

```php
'queue' => env('ATLAS_QUEUE', 'default'),
```

Worker configuration (retry attempts, backoff, job timeout) belongs in your queue supervisor or `config/horizon.php`, not in Atlas config. Atlas jobs default to 3 tries, 30s backoff, and 300s timeout.

## Per-Request Overrides

Override queue settings on a per-request basis:

```php
Atlas::text('openai', 'gpt-4o')
    ->message('Generate comprehensive report')
    ->queue('atlas-heavy')              // Custom queue name
    ->onConnection('redis')             // Custom connection
    ->withQueueTimeout(3600)            // 1 hour job timeout
    ->withQueueTries(1)                 // No retries (expensive operation)
    ->withQueueBackoff(60)              // 60s between retries
    ->withQueueDelay(30)                     // Delay 30s before processing
    ->asText();
```

### Method Reference

| Method | Description | Default |
|--------|-------------|---------|
| `queue(?string $name)` | Enable queuing, optionally set queue name | `'default'` |
| `onConnection(string $conn)` | Override queue connection | Laravel default |
| `onQueue(string $queue)` | Override queue name | Config value |
| `withQueueTimeout(int $seconds)` | Override job timeout | `300` (5 min) |
| `withQueueTries(int $tries)` | Override retry attempts (min: 1) | `3` |
| `withQueueBackoff(int $seconds)` | Override retry backoff | `30` |
| `withQueueDelay(int $seconds)` | Delay before processing | `0` |
| `broadcastOn(Channel $ch)` | Broadcast events to WebSocket channel | None |

## HTTP Retry (separate from queue retry)

Atlas also has built-in HTTP-level retry for transient provider failures. This is separate from queue job retries — it handles rate limits (429) and server errors (5xx) automatically:

```php
// Override HTTP timeout for a slow call
Atlas::agent('research')
    ->withTimeout(180)
    ->message('Deep analysis')
    ->asText();

// Be more patient on rate limits
Atlas::agent('batch')
    ->withRetry(rateLimit: 6)
    ->message('Process this')
    ->asText();

// Disable all HTTP retry for a real-time path
Atlas::agent('chat')
    ->withoutRetry()
    ->message($userMessage)
    ->asText();
```

Configure defaults in `config/atlas.php`:

```php
'retry' => [
    'timeout'    => (int) env('ATLAS_TIMEOUT', 60),
    'rate_limit' => (int) env('ATLAS_RETRY_RATE_LIMIT', 3),
    'errors'     => (int) env('ATLAS_RETRY_ERRORS', 2),
],
```

## Long-Running Jobs

For operations that take 30 minutes to an hour (complex agents, large media generation), use `withQueueTimeout()`:

```php
Atlas::text('openai', 'o3')
    ->message('Analyze this entire dataset...')
    ->queue()
    ->withQueueTimeout(3600)   // 1 hour
    ->withQueueTries(1)        // Don't retry expensive operations
    ->asText();
```

::: warning Horizon / Worker Alignment
The job timeout must be **less than** your queue worker's `--timeout` flag. If using Laravel Horizon, set the `timeout` config for the relevant queue higher than your longest expected job:

```php
// config/horizon.php
'environments' => [
    'production' => [
        'atlas-heavy' => [
            'timeout' => 7200, // 2 hours — higher than any job timeout
        ],
    ],
],
```
:::

## Broadcasting

Combine queue dispatch with broadcasting to stream results to WebSocket clients in real-time:

```php
use Illuminate\Broadcasting\Channel;

Atlas::text('openai', 'gpt-4o')
    ->message('Write a long essay')
    ->queue()
    ->broadcastOn(new Channel('chat.' . $user->id))
    ->asStream();
```

The job processes in the background while clients receive `StreamChunkReceived`, `StreamCompleted`, and execution lifecycle events via WebSocket. See [Streaming Guide](/guides/streaming) for frontend integration.

## Execution Tracking

When [persistence](/advanced/persistence) is enabled, queued executions are tracked through a clear lifecycle:

```
Queued → Processing → Completed
                    → Failed
```

| Status | When | What happened |
|--------|------|---------------|
| **Queued** | `->queue()->asText()` called | Execution record created, job dispatched |
| **Processing** | Worker picks up job | `started_at` set, `ExecutionProcessing` event fires |
| **Completed** | Execution succeeds | `completed_at`, `duration_ms`, token counts recorded |
| **Failed** | All retries exhausted | `error` message and `completed_at` recorded |

The execution ID is available immediately on `PendingExecution::executionId`, so your UI can show progress before the job starts.

### Execution Events

All execution events implement `ShouldBroadcastNow` for real-time WebSocket delivery:

| Event | When |
|-------|------|
| `ExecutionQueued` | Job dispatched to queue |
| `ExecutionProcessing` | Worker starts processing |
| `ExecutionCompleted` | Job finishes successfully |
| `ExecutionFailed` | Job fails after all retries |

See [Events](/advanced/events) for full details and listening examples.

## Retry Behavior

Atlas uses Laravel's built-in retry mechanism with configurable attempts and backoff.

### What happens on retry

When a job fails and retries:
1. The execution stays in `Processing` status (not reset to `Queued`)
2. `started_at` is updated to the retry start time
3. `ExecutionProcessing` event fires again
4. The request is rebuilt from the serialized payload and re-executed
5. A new API call is made to the AI provider

### Deterministic failure detection

`MaxStepsExceededException` (agent exceeded its step limit) is caught and fails the job immediately without retrying — retrying would produce the same infinite loop and burn API credits.

### At-least-once delivery

Like all Laravel queue jobs, Atlas uses at-least-once delivery. This means:

- **Retries make new API calls** — the AI provider is called again, and you are billed again
- **Responses may differ** — AI responses are non-deterministic, so retries may produce different output
- **No built-in idempotency keys** — Atlas does not deduplicate requests at the provider level

For expensive operations where retries are wasteful, set `withQueueTries(1)`:

```php
Atlas::image('openai', 'dall-e-3')
    ->instructions('Generate a hero image')
    ->queue()
    ->withQueueTries(1)   // Don't retry — image generation is expensive
    ->asImage();
```

### Conversation message safety

`ProcessQueuedMessage` (used for queued conversation messages) has additional safety:

- **`ShouldBeUnique`** — prevents concurrent processing per conversation
- **`respond()`** — prevents duplicate user messages on retry
- **Status recovery** — failed messages are reset to `Queued` for retry, then marked `Failed` when all retries are exhausted

## Next Steps

- [Streaming](/guides/streaming) — Real-time streaming with broadcasting
- [Events](/advanced/events) — Listen to execution lifecycle events
- [Persistence](/advanced/persistence) — Execution tracking and conversation storage
- [Testing](/advanced/testing) — Test queued executions with fakes
