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
        echo $event->text;
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
        echo $event->text;
    }
}
```

## Common Event Types

Atlas yields Prism streaming events. The most common:

```php
use Prism\Prism\Streaming\Events\StreamStartEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Streaming\Events\StreamEndEvent;

foreach ($stream as $event) {
    match (true) {
        $event instanceof StreamStartEvent => handleStart($event),
        $event instanceof TextDeltaEvent => echo $event->text,
        $event instanceof StreamEndEvent => handleEnd($event),
        default => null,
    };
}
```

<div class="full-width-table">

| Event | Description |
|-------|-------------|
| `StreamStartEvent` | Stream initialized |
| `TextDeltaEvent` | Text chunk received |
| `StreamEndEvent` | Stream completed |

</div>

See [Prism Streaming Event Types](https://prismphp.com/core-concepts/streaming-output.html#event-types) for the complete list including tool calls, thinking events, and more.

## Building Complete Text

::: tip
For most cases, use the [`text()` convenience method](#collecting-text) instead of manual accumulation.
:::

Accumulate text chunks as they arrive:

```php
$fullText = '';

foreach ($stream as $event) {
    if ($event instanceof TextDeltaEvent) {
        $fullText .= $event->text;

        // Real-time output
        echo $event->text;
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

The response automatically sets SSE headers (`Content-Type: text/event-stream`, `Cache-Control: no-cache`, `Connection: keep-alive`, `X-Accel-Buffering: no`) and streams text delta events to the client.

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

This formats events according to the Vercel AI SDK Data Stream Protocol, compatible with the `useChat` and `useCompletion` hooks.

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

## Broadcasting to WebSockets

Atlas includes a built-in `AgentStreamChunk` broadcast event for real-time WebSocket delivery:

```php
use Atlasphp\Atlas\Atlas;

Atlas::agent('support-agent')
    ->withVariables(['user' => $user->name])
    ->broadcast($input, $requestId);
```

Each stream chunk is broadcast on a private channel following the convention:

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

eventSource.onmessage = (event) => {
    const data = JSON.parse(event.data);
    document.getElementById('response').textContent += data.text;
};

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

## Error Handling

```php
use Prism\Prism\Streaming\Events\TextDeltaEvent;

try {
    foreach ($stream as $event) {
        if ($event instanceof TextDeltaEvent) {
            echo $event->text;
        }
    }
} catch (\Exception $e) {
    Log::error('Stream failed', ['error' => $e->getMessage()]);
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

        // Stream response with post-stream save
        $stream = Atlas::agent('support-agent')
            ->withMessages($messages)
            ->withMetadata(['conversation_id' => $conversation->id])
            ->stream($request->input('message'));

        $stream->then(function (AgentStreamResponse $s) use ($conversation) {
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $s->text(),
            ]);
        });

        return $stream;
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

$stream->toResponse($request);       // Laravel Responsable — SSE format (automatic)
$stream->asVercelStream();           // Switch to Vercel AI SDK Data Stream Protocol
$stream->text(): string;             // Collect full text after stream consumption
$stream->then(Closure $callback);    // Register post-stream callback
$stream->events(): array;            // Get collected stream events
$stream->isConsumed(): bool;         // Check if stream is fully consumed

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
use Prism\Prism\Streaming\Events\StreamStartEvent;   // Stream initialized
use Prism\Prism\Streaming\Events\TextDeltaEvent;     // Text chunk received
use Prism\Prism\Streaming\Events\StreamEndEvent;     // Stream completed
use Prism\Prism\Streaming\Events\ToolCallStartEvent; // Tool call starting
use Prism\Prism\Streaming\Events\ToolCallDeltaEvent; // Tool call argument chunk
use Prism\Prism\Streaming\Events\ToolResultEvent;    // Tool execution result
use Prism\Prism\Streaming\Events\ThinkingEvent;      // Model thinking (Claude)

// AgentStreamChunk broadcast event
// Channel: atlas.agent.{agentKey}.{requestId}
// Event name: atlas.stream.chunk
// Properties: $type, $delta, $metadata
```

## Next Steps

- [Chat](/capabilities/chat) — Non-streaming chat API
- [Prism Streaming](https://prismphp.com/core-concepts/streaming-output.html) — Complete streaming reference
- [Tools](/core-concepts/tools) — Tool execution during streams
