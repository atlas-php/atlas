# Streaming

Stream AI responses in real-time via Server-Sent Events (SSE) or Laravel Broadcasting (WebSockets).

## Overview

Atlas provides two delivery mechanisms for streaming:

| Method | How it works | Best for |
|--------|-------------|----------|
| **SSE** | HTTP response streams chunks directly to the requesting client | Single client, simple setup |
| **Broadcasting** | Chunks are broadcast to a WebSocket channel via Laravel Broadcasting | Multiple clients, background jobs |

Both can be used simultaneously — the requesting client receives SSE while other clients receive broadcast events.

## SSE Delivery

Return a stream directly from a Laravel route. `StreamResponse` implements Laravel's `Responsable` interface — it automatically sends proper SSE headers.

```php
use Atlasphp\Atlas\Facades\Atlas;

Route::post('/chat', function (Request $request) {
    return Atlas::text('openai', 'gpt-4o')
        ->instructions('You are a helpful assistant.')
        ->message($request->input('message'))
        ->asStream();
});
```

The response includes these headers automatically:

```
Content-Type: text/event-stream
Cache-Control: no-cache
Connection: keep-alive
X-Accel-Buffering: no
```

### SSE Event Format

Each chunk is sent as a named SSE event with JSON data:

```
event: chunk
data: {"type":"chunk","text":"Hello"}

event: chunk
data: {"type":"chunk","text":" world"}

event: thinking
data: {"type":"thinking","text":"Let me consider..."}

event: tool_call
data: {"type":"tool_call","toolCalls":[{"id":"tc-1","name":"search","arguments":{"q":"test"}}]}

event: done
data: {"type":"done","text":"Hello world","usage":{"input_tokens":10,"output_tokens":5}}
```

### Consuming SSE with JavaScript

```javascript
const eventSource = new EventSource('/chat', {
    // POST requests require fetch + ReadableStream instead
});

// For POST requests, use fetch with streaming:
async function streamChat(message) {
    const response = await fetch('/chat', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'text/event-stream',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ message }),
    });

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    while (true) {
        const { done, value } = await reader.read();
        if (done) break;

        buffer += decoder.decode(value, { stream: true });
        const lines = buffer.split('\n');
        buffer = lines.pop(); // Keep incomplete line in buffer

        for (const line of lines) {
            if (line.startsWith('data: ')) {
                const data = JSON.parse(line.slice(6));

                switch (data.type) {
                    case 'chunk':
                        appendText(data.text);
                        break;
                    case 'thinking':
                        showThinking(data.text);
                        break;
                    case 'tool_call':
                        showToolCalls(data.toolCalls);
                        break;
                    case 'done':
                        onComplete(data.text, data.usage);
                        break;
                }
            }
        }
    }
}
```

## Broadcasting

Broadcast chunks to a WebSocket channel so multiple clients receive real-time updates. Works with [Laravel Reverb](https://reverb.laravel.com), Pusher, Ably, or any configured broadcasting driver.

### Setup

```php
use Illuminate\Broadcasting\Channel;

$stream = Atlas::text('openai', 'gpt-4o')
    ->message('Explain quantum physics')
    ->asStream();

$stream->broadcastOn(new Channel('chat.1'));

// Iteration triggers broadcasting
foreach ($stream as $chunk) {
    // Each chunk is automatically broadcast
}
```

### SSE + Broadcasting Simultaneously

When returned from a route with `broadcastOn()`, both delivery mechanisms fire during the same iteration:

```php
Route::post('/chat', function (Request $request) {
    return Atlas::text('openai', 'gpt-4o')
        ->message($request->input('message'))
        ->asStream()
        ->broadcastOn(new Channel('chat.' . $request->user()->id));
});
```

The requesting client receives SSE directly. Other clients subscribed to the broadcast channel receive events via WebSocket.

### Broadcast Events

All stream broadcast events implement `ShouldBroadcastNow` for immediate delivery.

| Event | Properties | When |
|-------|-----------|------|
| `StreamStarted` | `channel` | Stream iteration begins |
| `StreamChunkReceived` | `channel`, `text` | Each text chunk |
| `StreamThinkingReceived` | `channel`, `text` | Each thinking/reasoning chunk |
| `StreamToolCallReceived` | `channel`, `toolCalls` | Tool call chunks |
| `StreamCompleted` | `channel`, `text`, `usage`, `finishReason`, `error` | Stream finishes |

### Channel Authorization

For private channels, register authorization in `routes/channels.php`:

```php
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
```

Then use `PrivateChannel` instead of `Channel`:

```php
use Illuminate\Broadcasting\PrivateChannel;

$stream->broadcastOn(new PrivateChannel('chat.' . $user->id));
```

### Consuming Broadcasts with Laravel Echo

```javascript
import Echo from 'laravel-echo';

// Initialize Echo (Reverb example)
const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
});

// Listen for stream events
echo.private(`chat.${userId}`)
    .listen('.StreamStarted', () => {
        showTypingIndicator();
    })
    .listen('.StreamChunkReceived', (e) => {
        appendText(e.text);
    })
    .listen('.StreamThinkingReceived', (e) => {
        showThinking(e.text);
    })
    .listen('.StreamToolCallReceived', (e) => {
        showToolCalls(e.toolCalls);
    })
    .listen('.StreamCompleted', (e) => {
        hideTypingIndicator();
        if (e.error) {
            showError(e.error);
        } else {
            onComplete(e.text, e.usage);
        }
    });
```

## Callbacks

### Per-Chunk Callback

```php
$stream->onChunk(function (StreamChunk $chunk) {
    if ($chunk->text !== null) {
        Log::debug('Chunk received', ['text' => $chunk->text]);
    }
});
```

### Post-Stream Callbacks

Register one or more callbacks that fire after the stream completes. They receive the `StreamResponse` with all accumulated data.

```php
$stream
    ->then(fn ($s) => Log::info('Stream done', ['tokens' => $s->getUsage()?->totalTokens()]))
    ->then(fn ($s) => cache()->put('last_response', $s->getText()));
```

::: warning Callbacks and Errors
`then()` callbacks do **not** fire if the stream throws an exception. If you need to handle partial results on error, wrap iteration in a try/catch and access the `StreamResponse` accessors directly.
:::

## Error Handling

If an exception occurs during streaming, `StreamCompleted` broadcasts with the `error` field populated so frontend clients don't stay in a "typing..." state:

```php
// Backend: error is broadcast automatically
$stream->broadcastOn(new Channel('chat.1'));

foreach ($stream as $chunk) {
    // If the provider errors mid-stream, StreamCompleted fires with error
}
```

```javascript
// Frontend: handle error in StreamCompleted
echo.private('chat.1')
    .listen('.StreamCompleted', (e) => {
        if (e.error) {
            showError(e.error);
        }
    });
```

## Streaming with Tools

When tools are present, the stream yields tool call chunks followed by text:

```php
use Atlasphp\Atlas\Enums\ChunkType;

$stream = Atlas::text('openai', 'gpt-4o')
    ->withTools([WeatherTool::class])
    ->message('What is the weather in NYC?')
    ->asStream();

foreach ($stream as $chunk) {
    match ($chunk->type) {
        ChunkType::Thinking => handleThinking($chunk->reasoning),
        ChunkType::ToolCall => handleToolCalls($chunk->toolCalls),
        ChunkType::Text     => echo $chunk->text,
        ChunkType::Done     => handleCompletion($chunk->usage, $chunk->finishReason),
    };
}
```

## Stream Accessors

After iteration completes, access accumulated data on the `StreamResponse`:

```php
$stream->getText();         // Full accumulated text
$stream->getReasoning();    // Thinking/reasoning content
$stream->getUsage();        // Usage object (inputTokens, outputTokens)
$stream->getFinishReason(); // FinishReason enum
$stream->getToolCalls();    // Array of ToolCall objects
```

::: warning Single Iteration
`StreamResponse` can only be iterated once. Calling `toResponse()` after manual iteration produces an empty response. If you need both manual iteration and SSE delivery, use broadcasting instead.
:::

## Testing

Use `StreamResponseFake` to create fake streams in tests:

```php
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Testing\StreamResponseFake;

// Basic text stream
$stream = StreamResponseFake::make()
    ->withText('Hello world')
    ->withChunkSize(5)
    ->toResponse();

// Stream with thinking and tool calls
$stream = StreamResponseFake::make()
    ->withThinking('Let me reason...')
    ->withToolCalls([new ToolCall('tc-1', 'search', ['q' => 'test'])])
    ->withText('Here are the results')
    ->toResponse();

// Chunks emit in order: thinking -> tool calls -> text -> done
foreach ($stream as $chunk) {
    // test assertions
}
```

## Queue Integration

Dispatch streaming to a background queue with broadcasting:

```php
use Illuminate\Broadcasting\Channel;

Atlas::text('openai', 'gpt-4o')
    ->message('Write a long essay')
    ->queue()
    ->broadcastOn(new Channel('chat.' . $user->id))
    ->asText();
```

The queued job processes the request in the background. Clients receive broadcast events in real-time via WebSocket. See the [Queue & Background Jobs](/guides/queue) guide for full details on configuration, long-running jobs, retry behavior, and execution tracking.
