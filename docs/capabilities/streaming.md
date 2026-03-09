# Streaming

Stream AI responses in real-time for responsive user experiences.

::: tip Prism Reference
Atlas streaming returns Prism's stream events directly. For comprehensive streaming documentation including all event types, see [Prism Streaming](https://prismphp.com/core-concepts/streaming-output.html).
:::

## Basic Streaming

Use the `stream()` method to get real-time responses:

```php
use Atlasphp\Atlas\Atlas;
use Prism\Prism\Streaming\Events\TextDeltaEvent;

$stream = Atlas::agent('support-agent')->stream('Hello!');

foreach ($stream as $event) {
    if ($event instanceof TextDeltaEvent) {
        echo $event->delta;
        flush();
    }
}
```

## Stream vs Chat

```php
// Non-streaming - waits for complete response
$response = Atlas::agent('agent')->chat('Hello');
echo $response->text;

// Streaming - yields events as they arrive
$stream = Atlas::agent('agent')->stream('Hello');
foreach ($stream as $event) {
    // Process each event in real-time
}
```

## With Conversation History

```php
$messages = [
    ['role' => 'user', 'content' => 'Hello!'],
    ['role' => 'assistant', 'content' => 'Hi there!'],
];

$stream = Atlas::agent('support-agent')
    ->withMessages($messages)
    ->withVariables(['user_name' => $user->name])
    ->withMetadata(['user_id' => $user->id])
    ->stream('What can you help me with?');

foreach ($stream as $event) {
    if ($event instanceof TextDeltaEvent) {
        echo $event->delta;
    }
}
```

## Common Event Types

Atlas yields all Prism streaming events automatically. The most common:

```php
use Prism\Prism\Streaming\Events\StreamStartEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\ThinkingEvent;
use Prism\Prism\Streaming\Events\ToolCallEvent;
use Prism\Prism\Streaming\Events\ErrorEvent;

foreach ($stream as $event) {
    match (true) {
        $event instanceof TextDeltaEvent => echo $event->delta,
        $event instanceof ThinkingEvent => handleThinking($event),
        $event instanceof ToolCallEvent => handleToolCall($event),
        $event instanceof ErrorEvent => handleError($event),
        $event instanceof StreamEndEvent => handleEnd($event),
        default => null,
    };
}
```

<div class="full-width-table">

| Event | Description |
|-------|-------------|
| `StreamStartEvent` | Stream initialized with model/provider info |
| `TextDeltaEvent` | Text chunk received (`$event->delta`) |
| `ThinkingStartEvent` | Reasoning/thinking began |
| `ThinkingEvent` | Thinking chunk received (`$event->delta`) |
| `ThinkingCompleteEvent` | Reasoning/thinking finished |
| `ToolCallEvent` | Tool call with arguments |
| `ToolResultEvent` | Tool execution result |
| `ErrorEvent` | Error occurred (may be recoverable) |
| `StreamEndEvent` | Stream completed with usage stats |

</div>

See [Prism Streaming Event Types](https://prismphp.com/core-concepts/streaming-output.html#event-types) for the complete list including citations, artifacts, steps, and more.

## Building Complete Text

::: tip
For most cases, use the [`text()` convenience method](#collecting-text) instead of manual accumulation.
:::

Accumulate text chunks as they arrive:

```php
$fullText = '';

foreach ($stream as $event) {
    if ($event instanceof TextDeltaEvent) {
        $fullText .= $event->delta;

        // Real-time output
        echo $event->delta;
        flush();
    }
}

// Save complete response
Message::create([
    'role' => 'assistant',
    'content' => $fullText,
]);
```

## HTTP Streaming (SSE)

`AgentStreamResponse` implements Laravel's `Responsable` interface — return it directly from controllers for Server-Sent Events:

```php
use Atlasphp\Atlas\Atlas;

class ChatController extends Controller
{
    public function stream(Request $request)
    {
        return Atlas::agent('support-agent')
            ->withMessages($request->messages ?? [])
            ->stream($request->input('message'));
    }
}
```

The response automatically sets SSE headers (`Content-Type: text/event-stream`, `Cache-Control: no-cache`, `Connection: keep-alive`, `X-Accel-Buffering: no`) and streams all event types using Prism's `eventKey()` as the SSE event name (e.g., `stream_start`, `text_delta`, `thinking_delta`, `tool_call`, `stream_end`).

## Vercel AI SDK Protocol

For frontends using the [Vercel AI SDK](https://sdk.vercel.ai), switch to the Data Stream Protocol:

```php
class ChatController extends Controller
{
    public function stream(Request $request)
    {
        return Atlas::agent('support-agent')
            ->stream($request->input('message'))
            ->asVercelStream();
    }
}
```

This formats events according to the Vercel AI SDK Data Stream Protocol, compatible with the `useChat` and `useCompletion` hooks. Supported Vercel format codes:

| Code | Event | Description |
|------|-------|-------------|
| `0:` | Text delta | Text content chunk |
| `g:` | Thinking | Reasoning start/delta/complete |
| `9:` | Tool call | Tool invocation with arguments |
| `a:` | Tool result | Tool execution result |
| `3:` | Error | Error message |
| `d:` | Finish | Stream end with usage stats |

The response includes the `x-vercel-ai-ui-message-stream: v1` header.

## Convenience Methods

### Collecting Text

Use `text()` to collect the full response text after consuming the stream:

```php
$stream = Atlas::agent('support-agent')->stream('Hello!');

foreach ($stream as $event) {
    // Process events in real-time...
}

// Get the complete text after the stream is consumed
$fullText = $stream->text();
```

### Post-Stream Callbacks

Use `then()` to register a callback that runs after the stream is fully consumed:

```php
use Atlasphp\Atlas\Agents\Support\AgentStreamResponse;

$stream = Atlas::agent('support-agent')->stream($input);

$stream->then(function (AgentStreamResponse $stream) use ($conversation) {
    $conversation->messages()->create([
        'role' => 'assistant',
        'content' => $stream->text(),
    ]);
});

return $stream; // Callback fires after response is sent
```

### Per-Event Callbacks

Use `each()` to fire a callback on every stream event:

```php
use Prism\Prism\Streaming\Events\StreamEvent;

$stream = Atlas::agent('support-agent')->stream($input)
    ->each(function (StreamEvent $event, AgentStreamResponse $response) {
        Log::info('Stream event', ['type' => $event->eventKey()]);
    });

return $stream;
```

The `each()` callback receives the event and the stream response. It fires during initial iteration only — not on [replay](#stream-replay).

### Error Handling

Use `onError()` to handle `ErrorEvent` instances during streaming:

```php
use Prism\Prism\Streaming\Events\ErrorEvent;

$stream = Atlas::agent('support-agent')->stream($input)
    ->onError(function (ErrorEvent $error, AgentStreamResponse $response) {
        Log::error('Stream error', [
            'type' => $error->errorType,
            'message' => $error->message,
            'recoverable' => $error->recoverable,
        ]);
    });
```

The callback fires only for `ErrorEvent` events. Prism controls whether the stream continues after an error based on the `recoverable` flag.

### Stream Replay

Consumed streams can be re-iterated from cached events:

```php
$stream = Atlas::agent('support-agent')->stream($input);

// First iteration — consumes the stream
foreach ($stream as $event) {
    // Process events
}

// Replay — yields from cached events
foreach ($stream as $event) {
    // Same events, no API call
}
```

Callbacks (`each()`, `then()`, `onError()`) do **not** fire on replay.

## Broadcasting to WebSockets

### Queued Broadcasting

Atlas includes a built-in `BroadcastAgent` job for broadcasting via queue:

```php
use Atlasphp\Atlas\Atlas;

Atlas::agent('support-agent')
    ->withVariables(['user' => $user->name])
    ->broadcast($input, $requestId);
```

This dispatches a queue job that streams the agent response and broadcasts each chunk as an `AgentStreamChunk` event.

### Inline Broadcasting

Broadcast stream events directly during iteration — no queue job required:

```php
// Queued broadcast during iteration (via Laravel events)
return Atlas::agent('support-agent')
    ->stream($input)
    ->broadcast($requestId);

// Synchronous broadcast during iteration (no queue required)
return Atlas::agent('support-agent')
    ->stream($input)
    ->broadcastNow($requestId);
```

Inline broadcasting is useful when you want to stream SSE to HTTP **and** broadcast to WebSocket simultaneously from a controller:

```php
class ChatController extends Controller
{
    public function stream(Request $request)
    {
        $requestId = $request->input('request_id', bin2hex(random_bytes(16)));

        return Atlas::agent('support-agent')
            ->stream($request->input('message'))
            ->broadcastNow($requestId);
    }
}
```

The `$requestId` is auto-generated if not provided. Access it via `$stream->broadcastRequestId()`.

### Channel Convention

Each stream chunk is broadcast on a private channel:

```
atlas.agent.{agentKey}.{requestId}
```

The broadcast event name is `atlas.stream.chunk` and includes `type`, `delta`, and `metadata` properties.

### Frontend Listener

Listen for chunks with Laravel Echo:

```javascript
Echo.private(`atlas.agent.support-agent.${requestId}`)
    .listen('.atlas.stream.chunk', (event) => {
        if (event.delta) {
            document.getElementById('response').textContent += event.delta;
        }
    });
```

## Queued Execution

Run agents in the background using Laravel queues:

```php
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Agents\Support\AgentResponse;

Atlas::agent('support-agent')
    ->queue($input)
    ->onQueue('ai')
    ->onConnection('redis')
    ->then(fn (AgentResponse $response) => $conversation->messages()->create([
        'role' => 'assistant',
        'content' => $response->text(),
    ]))
    ->catch(fn (Throwable $e) => Log::error('Agent failed', [
        'error' => $e->getMessage(),
    ]));
```

The `queue()` method returns a `QueuedAgentResponse` with a fluent API:

- `onQueue(string $queue)` — Set the queue name
- `onConnection(string $connection)` — Set the queue connection
- `delay(DateTimeInterface|DateInterval|int $delay)` — Delay execution
- `then(Closure $callback)` — Success callback receiving `AgentResponse`
- `catch(Closure $callback)` — Failure callback receiving `Throwable`

The job is dispatched automatically. For queued broadcasting that streams to WebSockets, use `broadcast()` instead.

## Frontend Integration

### JavaScript EventSource

```javascript
const eventSource = new EventSource('/api/chat/stream');

eventSource.addEventListener('text_delta', (event) => {
    const data = JSON.parse(event.data);
    document.getElementById('response').textContent += data.delta;
});

eventSource.addEventListener('stream_end', () => {
    eventSource.close();
});

eventSource.onerror = () => {
    eventSource.close();
};
```

### Fetch with ReadableStream

```javascript
const response = await fetch('/api/chat/stream', {
    method: 'POST',
    body: JSON.stringify({ message: 'Hello' }),
});

const reader = response.body.getReader();
const decoder = new TextDecoder();

while (true) {
    const { done, value } = await reader.read();
    if (done) break;

    const text = decoder.decode(value);
    document.getElementById('response').textContent += text;
}
```

## Analytics and Logging

Use the `agent.stream.after` pipeline to log or analyze completed streams:

```php
$registry->register('agent.stream.after', function (mixed $data, Closure $next) {
    Log::info('Stream completed', [
        'agent' => $data['agent']->key(),
        'input' => $data['input'],
    ]);

    return $next($data);
});
```

See [Pipelines](/core-concepts/pipelines) for more pipeline hooks.

## Example: Complete Streaming Controller

```php
use Atlasphp\Atlas\Atlas;

class ChatController extends Controller
{
    public function stream(Request $request, Conversation $conversation)
    {
        // Save user message
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $request->input('message'),
        ]);

        // Get history
        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        // Stream response with post-stream save and error handling
        return Atlas::agent('support-agent')
            ->withMessages($messages)
            ->withMetadata(['conversation_id' => $conversation->id])
            ->stream($request->input('message'))
            ->each(fn (StreamEvent $event) => Log::debug('stream', ['type' => $event->eventKey()]))
            ->onError(fn (ErrorEvent $error) => Log::error('stream error', ['message' => $error->message]))
            ->then(function (AgentStreamResponse $s) use ($conversation) {
                $conversation->messages()->create([
                    'role' => 'assistant',
                    'content' => $s->text(),
                ]);
            });
    }
}
```

## API Reference

```php
// Agent streaming fluent API (same configuration as chat)
Atlas::agent(string|AgentContract $agent)
    // Context configuration
    ->withMessages(array $messages)                       // Conversation history
    ->withVariables(array $variables)                     // System prompt variables
    ->withMetadata(array $metadata)                       // Pipeline/tool metadata

    // Provider overrides
    ->withProvider(string $provider, ?string $model)      // Override provider/model
    ->withModel(string $model)                            // Override model only

    // Attachments
    ->withMedia(Image|Document|Audio|Video|array $media)  // Attach media

    // Prism passthrough methods
    ->withToolChoice(ToolChoice $choice)                  // Tool selection
    ->withMaxTokens(int $tokens)                          // Max response tokens
    ->withTemperature(float $temp)                        // Sampling temperature
    ->usingTopP(float $topP)                              // Top-p sampling
    ->withClientRetry(int $times, int $sleepMs)           // Retry with backoff
    ->withProviderOptions(array $options)                 // Provider-specific options

    // Execution
    ->stream(string $input, array $attachments = []): AgentStreamResponse
    ->queue(string $input): QueuedAgentResponse           // Background execution
    ->broadcast(string $input, ?string $requestId): PendingDispatch;  // WebSocket streaming

// AgentStreamResponse (implements IteratorAggregate, Responsable)
$stream = Atlas::agent('agent')->stream('Hello');

$stream->toResponse($request);                // Laravel Responsable — SSE format (automatic)
$stream->asVercelStream();                    // Switch to Vercel AI SDK Data Stream Protocol
$stream->text(): string;                      // Collect full text after stream consumption
$stream->then(Closure $callback);             // Register post-stream callback
$stream->each(Closure $callback);             // Register per-event callback
$stream->onError(Closure $callback);          // Register error event callback
$stream->broadcast(?string $requestId);       // Enable queued inline broadcasting
$stream->broadcastNow(?string $requestId);    // Enable synchronous inline broadcasting
$stream->broadcastRequestId(): ?string;       // Get the broadcast request ID
$stream->events(): array;                     // Get collected stream events
$stream->isConsumed(): bool;                  // Check if stream is fully consumed

// QueuedAgentResponse
$queued = Atlas::agent('agent')->queue('Hello');

$queued->onQueue(string $queue);                   // Set queue name
$queued->onConnection(string $connection);         // Set queue connection
$queued->delay(DateTimeInterface|DateInterval|int); // Delay execution
$queued->then(Closure $callback);                  // Success callback (AgentResponse)
$queued->catch(Closure $callback);                 // Failure callback (Throwable)
$queued->dispatch();                               // Explicit dispatch (auto on destruct)

// Direct text streaming (without agents)
Atlas::text()
    ->using(string $provider, string $model)
    ->withSystemPrompt(string $prompt)
    ->withPrompt(string $prompt)
    ->withMessages(array $messages)
    ->withMetadata(array $metadata)
    ->asStream(): Generator<StreamEvent>;

// Common Prism stream events
use Prism\Prism\Streaming\Events\StreamStartEvent;      // Stream initialized
use Prism\Prism\Streaming\Events\TextDeltaEvent;        // Text chunk ($event->delta)
use Prism\Prism\Streaming\Events\StreamEndEvent;        // Stream completed
use Prism\Prism\Streaming\Events\ThinkingStartEvent;    // Reasoning started
use Prism\Prism\Streaming\Events\ThinkingEvent;         // Thinking chunk ($event->delta)
use Prism\Prism\Streaming\Events\ThinkingCompleteEvent; // Reasoning finished
use Prism\Prism\Streaming\Events\ToolCallEvent;         // Tool call with arguments
use Prism\Prism\Streaming\Events\ToolCallDeltaEvent;    // Tool call argument chunk
use Prism\Prism\Streaming\Events\ToolResultEvent;       // Tool execution result
use Prism\Prism\Streaming\Events\ErrorEvent;            // Error (may be recoverable)

// AgentStreamChunk broadcast event
// Channel: atlas.agent.{agentKey}.{requestId}
// Event name: atlas.stream.chunk
// Properties: $type, $delta, $metadata
```

## Next Steps

- [Chat](/capabilities/chat) — Non-streaming chat API
- [Prism Streaming](https://prismphp.com/core-concepts/streaming-output.html) — Complete streaming reference
- [Tools](/core-concepts/tools) — Tool execution during streams
