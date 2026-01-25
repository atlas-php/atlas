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

## SSE Response (HTTP Endpoints)

For Server-Sent Events in HTTP controllers:

```php
use Atlasphp\Atlas\Atlas;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function stream(Request $request)
    {
        return new StreamedResponse(function () use ($request) {
            $stream = Atlas::agent('support-agent')
                ->withMessages($request->messages ?? [])
                ->stream($request->input('message'));

            foreach ($stream as $event) {
                if ($event instanceof TextDeltaEvent) {
                    echo "data: " . json_encode(['text' => $event->text]) . "\n\n";
                    ob_flush();
                    flush();
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }
}
```

## Broadcasting to WebSockets

Combine with Laravel broadcasting for real-time chat:

```php
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Streaming\Events\StreamEndEvent;

$fullText = '';

foreach ($stream as $event) {
    if ($event instanceof TextDeltaEvent) {
        $fullText .= $event->text;

        broadcast(new ChatChunk(
            conversationId: $conversationId,
            text: $event->text,
        ))->toOthers();
    }

    if ($event instanceof StreamEndEvent) {
        broadcast(new ChatComplete(
            conversationId: $conversationId,
            fullText: $fullText,
        ))->toOthers();
    }
}
```

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

## Complete Example

```php
use Atlasphp\Atlas\Atlas;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Streaming\Events\StreamEndEvent;

class ChatService
{
    public function streamResponse(Conversation $conversation, string $userMessage): string
    {
        // Save user message
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $userMessage,
        ]);

        // Get history
        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        // Stream response
        $stream = Atlas::agent('support-agent')
            ->withMessages($messages)
            ->withMetadata(['conversation_id' => $conversation->id])
            ->stream($userMessage);

        $fullText = '';

        foreach ($stream as $event) {
            if ($event instanceof TextDeltaEvent) {
                $fullText .= $event->text;

                // Broadcast chunk
                broadcast(new ChatChunk($conversation->id, $event->text))->toOthers();
            }
        }

        // Save assistant response
        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $fullText,
        ]);

        return $fullText;
    }
}
```

## Notes

- Streaming returns Prism events directly—Atlas doesn't wrap them
- All Prism streaming features (tool calls, thinking, etc.) work with Atlas agents
- The `stream()` method accepts the same configuration as `chat()` (messages, variables, metadata)

## Next Steps

- [Chat](/capabilities/chat) — Non-streaming chat API
- [Prism Streaming](https://prismphp.com/core-concepts/streaming-output.html) — Complete streaming reference
- [Tools](/core-concepts/tools) — Tool execution during streams
