# Streaming

Stream AI responses in real-time for responsive user experiences. Atlas provides a stateless, consumer-controlled streaming API that gives you full control over how stream events are processed, recorded, and displayed.

## Overview

Streaming allows you to receive AI responses as they're generated, rather than waiting for the complete response. This enables:

- **Real-time UI updates** - Show text as it's generated, character by character
- **Lower perceived latency** - Users see responses immediately
- **Progress indication** - Show tool execution status in real-time
- **Full control** - Process, record, or broadcast each event as needed

### Return Types

```php
// Non-streaming (default) - returns complete response
$response = Atlas::agent('agent')->chat('Hello');  // Returns AgentResponse

// Streaming - returns iterable stream
$stream = Atlas::agent('agent')->chat('Hello', stream: true);  // Returns StreamResponse
```

## Quick Start

Enable streaming by passing `stream: true` to the `chat()` method:

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;
use Atlasphp\Atlas\Streaming\Events\TextDeltaEvent;

$stream = Atlas::agent('support-agent')->chat('Hello!', stream: true);

foreach ($stream as $event) {
    if ($event instanceof TextDeltaEvent) {
        echo $event->text;  // Output each chunk as it arrives
    }
}
```

## Processing Text Deltas

Text arrives in chunks via `TextDeltaEvent`. Here's how to process them:

### Simple Output

```php
foreach ($stream as $event) {
    if ($event instanceof TextDeltaEvent) {
        echo $event->text;
        flush();  // Ensure immediate output
    }
}
```

### Building Complete Response

The `StreamResponse` automatically accumulates text. Access it after iteration:

```php
$stream = Atlas::agent('agent')->chat( 'Tell me a story', stream: true);

// Option 1: Iterate and access after
foreach ($stream as $event) {
    if ($event instanceof TextDeltaEvent) {
        echo $event->text;  // Real-time output
    }
}
$fullText = $stream->text();  // Complete accumulated text

// Option 2: Collect without manual iteration
$stream = Atlas::agent('agent')->chat( 'Tell me a story', stream: true)->collect();
$fullText = $stream->text();  // Complete text available immediately
```

### Real-time Processing with Context

```php
$buffer = '';
$chunkCount = 0;

foreach ($stream as $event) {
    if ($event instanceof TextDeltaEvent) {
        $buffer .= $event->text;
        $chunkCount++;

        // Process in real-time
        $this->updateUI($event->text);

        // Log each chunk
        Log::debug("Chunk #{$chunkCount}", ['text' => $event->text]);
    }
}
```

## Recording Stream Data

Atlas gives you complete control over how to record streaming data. Here are common patterns:

### Save Complete Response After Streaming

```php
$stream = Atlas::agent('agent')->chat( $userMessage, stream: true);

foreach ($stream as $event) {
    if ($event instanceof TextDeltaEvent) {
        echo $event->text;
    }
}

// After streaming completes, save to database
Message::create([
    'conversation_id' => $conversationId,
    'role' => 'assistant',
    'content' => $stream->text(),
    'prompt_tokens' => $stream->promptTokens(),
    'completion_tokens' => $stream->completionTokens(),
    'total_tokens' => $stream->totalTokens(),
    'finish_reason' => $stream->finishReason(),
]);
```

### Record Each Event for Analytics

```php
foreach ($stream as $event) {
    // Record every event
    StreamEvent::create([
        'conversation_id' => $conversationId,
        'event_type' => $event->type(),
        'event_id' => $event->id,
        'timestamp' => $event->timestamp,
        'data' => $event->toArray(),
    ]);

    if ($event instanceof TextDeltaEvent) {
        echo $event->text;
    }
}
```

### Track Tool Execution

```php
foreach ($stream as $event) {
    if ($event instanceof ToolCallStartEvent) {
        ToolExecution::create([
            'conversation_id' => $conversationId,
            'tool_id' => $event->toolId,
            'tool_name' => $event->toolName,
            'arguments' => $event->arguments,
            'started_at' => now(),
        ]);
    }

    if ($event instanceof ToolCallEndEvent) {
        ToolExecution::where('tool_id', $event->toolId)->update([
            'result' => $event->result,
            'success' => $event->success,
            'completed_at' => now(),
        ]);
    }
}

// Or access all tool calls after streaming
$toolCalls = $stream->toolCalls();
foreach ($toolCalls as $call) {
    // $call = ['id' => '...', 'name' => '...', 'arguments' => [...], 'result' => '...']
}
```

## Getting Usage Statistics

Usage statistics (token counts) are available after the stream completes:

```php
$stream = Atlas::agent('agent')->chat( 'Hello', stream: true);

foreach ($stream as $event) {
    // Process events...
}

// Access usage after iteration
$usage = $stream->usage();
// Returns: ['prompt_tokens' => 50, 'completion_tokens' => 25, 'total_tokens' => 75]

// Or use convenience methods
$promptTokens = $stream->promptTokens();       // 50
$completionTokens = $stream->completionTokens(); // 25
$totalTokens = $stream->totalTokens();         // 75
$finishReason = $stream->finishReason();       // 'stop'
```

### Track Usage for Billing

```php
$stream = Atlas::agent('agent')->chat( $input, stream: true);

foreach ($stream as $event) {
    if ($event instanceof TextDeltaEvent) {
        echo $event->text;
    }
}

// Update user's token usage
$user->increment('tokens_used', $stream->totalTokens());

// Or detailed tracking
UsageLog::create([
    'user_id' => $user->id,
    'agent' => 'agent',
    'prompt_tokens' => $stream->promptTokens(),
    'completion_tokens' => $stream->completionTokens(),
    'total_tokens' => $stream->totalTokens(),
    'cost' => $this->calculateCost($stream->usage()),
]);
```

## With Conversation History

Pass conversation history using the `messages:` argument:

```php
$messages = [
    ['role' => 'user', 'content' => 'Hello!'],
    ['role' => 'assistant', 'content' => 'Hi there! How can I help?'],
];

$stream = Atlas::agent('support-agent')
    ->withMessages($messages)
    ->withVariables(['user_name' => $user->name])
    ->withMetadata(['conversation_id' => $conversationId])
    ->chat('What can you do?', stream: true);

foreach ($stream as $event) {
    if ($event instanceof TextDeltaEvent) {
        echo $event->text;
    }
}
```

## SSE Response (HTTP Endpoints)

For HTTP endpoints, convert the stream directly to a Server-Sent Events response:

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

class ChatController extends Controller
{
    public function stream(Request $request)
    {
        $stream = Atlas::agent('support-agent')
            ->withMessages($request->messages)
            ->chat($request->input, stream: true);

        return $stream->toResponse();
    }
}
```

### With Completion Callback

Save data after streaming completes:

```php
return $stream->toResponse(
    onComplete: function ($stream) use ($conversationId, $user) {
        // Save the complete response
        Message::create([
            'conversation_id' => $conversationId,
            'role' => 'assistant',
            'content' => $stream->text(),
        ]);

        // Track usage
        $user->increment('tokens_used', $stream->totalTokens());
    }
);
```

## Event Types

Atlas provides typed events for all stream activities:

### Core Events

| Event | Type String | Description |
|-------|-------------|-------------|
| `StreamStartEvent` | `stream.start` | Stream initialization with provider and model info |
| `TextDeltaEvent` | `text.delta` | Text chunk received (primary event for responses) |
| `ToolCallStartEvent` | `tool.call.start` | Tool invocation begins with arguments |
| `ToolCallEndEvent` | `tool.call.end` | Tool execution completes with result |
| `StreamEndEvent` | `stream.end` | Stream completes with usage statistics |
| `ErrorEvent` | `error` | Error occurred during streaming |

### Thinking Events (Extended Thinking / Reasoning)

| Event | Type String | Description |
|-------|-------------|-------------|
| `ThinkingStartEvent` | `thinking.start` | Model begins extended thinking/reasoning |
| `ThinkingDeltaEvent` | `thinking.delta` | Thinking content chunk received |
| `ThinkingCompleteEvent` | `thinking.complete` | Thinking phase completes |

### Advanced Events

| Event | Type String | Description |
|-------|-------------|-------------|
| `CitationEvent` | `citation` | Citation reference from grounded responses |
| `ArtifactEvent` | `artifact` | Artifact generated (code, files, etc.) |
| `StepStartEvent` | `step.start` | Multi-step execution begins a new step |
| `StepFinishEvent` | `step.finish` | Multi-step execution completes a step |

### Handling All Event Types

```php
use Atlasphp\Atlas\Streaming\Events\{
    StreamStartEvent,
    TextDeltaEvent,
    ToolCallStartEvent,
    ToolCallEndEvent,
    StreamEndEvent,
    ErrorEvent,
    ThinkingStartEvent,
    ThinkingDeltaEvent,
    ThinkingCompleteEvent,
    CitationEvent,
    ArtifactEvent,
    StepStartEvent,
    StepFinishEvent,
};

foreach ($stream as $event) {
    match (true) {
        $event instanceof StreamStartEvent => $this->onStreamStart($event),
        $event instanceof TextDeltaEvent => $this->onTextDelta($event),
        $event instanceof ToolCallStartEvent => $this->onToolStart($event),
        $event instanceof ToolCallEndEvent => $this->onToolEnd($event),
        $event instanceof ThinkingStartEvent => $this->onThinkingStart($event),
        $event instanceof ThinkingDeltaEvent => $this->onThinkingDelta($event),
        $event instanceof ThinkingCompleteEvent => $this->onThinkingComplete($event),
        $event instanceof CitationEvent => $this->onCitation($event),
        $event instanceof ArtifactEvent => $this->onArtifact($event),
        $event instanceof StepStartEvent => $this->onStepStart($event),
        $event instanceof StepFinishEvent => $this->onStepFinish($event),
        $event instanceof StreamEndEvent => $this->onStreamEnd($event),
        $event instanceof ErrorEvent => $this->onError($event),
        default => null,
    };
}
```

### Handling Extended Thinking

When using models with extended thinking (reasoning), capture the thinking process:

```php
use Atlasphp\Atlas\Streaming\Events\{
    ThinkingStartEvent,
    ThinkingDeltaEvent,
    ThinkingCompleteEvent,
    TextDeltaEvent,
};

$thinkingContent = '';
$responseContent = '';

foreach ($stream as $event) {
    match (true) {
        $event instanceof ThinkingStartEvent => null,  // Thinking begins
        $event instanceof ThinkingDeltaEvent => $thinkingContent .= $event->delta,
        $event instanceof ThinkingCompleteEvent => Log::info('Thinking complete', [
            'reasoning_id' => $event->reasoningId,
            'summary' => $event->summary,
        ]),
        $event instanceof TextDeltaEvent => $responseContent .= $event->text,
        default => null,
    };
}

// $thinkingContent contains the model's reasoning process
// $responseContent contains the final response
```

### Handling Citations

For grounded responses with source citations:

```php
use Atlasphp\Atlas\Streaming\Events\CitationEvent;

$citations = [];

foreach ($stream as $event) {
    if ($event instanceof CitationEvent) {
        $citations[] = [
            'source_type' => $event->citation['source_type'],
            'source' => $event->citation['source'],
            'text' => $event->citation['source_text'],
            'title' => $event->citation['source_title'],
        ];
    }
}

// Display citations with response
foreach ($citations as $citation) {
    echo "[{$citation['title']}]: {$citation['text']}\n";
}
```

## StreamResponse Methods Reference

After iterating through the stream (or calling `collect()`), these methods are available:

| Method | Return Type | Description |
|--------|-------------|-------------|
| `text()` | `string` | Complete accumulated text from all deltas |
| `usage()` | `array` | Token usage: `['prompt_tokens' => X, 'completion_tokens' => Y, 'total_tokens' => Z]` |
| `promptTokens()` | `int` | Number of prompt tokens used |
| `completionTokens()` | `int` | Number of completion tokens used |
| `totalTokens()` | `int` | Total tokens used |
| `finishReason()` | `?string` | Why the stream ended: `'stop'`, `'length'`, `'tool_calls'`, etc. |
| `toolCalls()` | `array` | All tool calls: `[['id' => '...', 'name' => '...', 'arguments' => [...], 'result' => '...']]` |
| `events()` | `array` | All collected events |
| `hasErrors()` | `bool` | Whether any errors occurred |
| `errors()` | `array` | All error events |
| `collect()` | `self` | Iterate without manual loop, returns self for chaining |
| `toResponse()` | `StreamedResponse` | Convert to SSE HTTP response |

## Broadcasting to WebSockets

Combine streaming with Laravel's broadcasting for real-time chat:

```php
foreach ($stream as $event) {
    if ($event instanceof TextDeltaEvent) {
        // Broadcast each chunk to connected clients
        broadcast(new ChatChunk(
            conversationId: $conversationId,
            text: $event->text,
        ))->toOthers();
    }

    if ($event instanceof ToolCallStartEvent) {
        broadcast(new ToolExecuting(
            conversationId: $conversationId,
            toolName: $event->toolName,
        ))->toOthers();
    }

    if ($event instanceof StreamEndEvent) {
        broadcast(new ChatComplete(
            conversationId: $conversationId,
            fullText: $stream->text(),
            usage: $stream->usage(),
        ))->toOthers();
    }
}
```

## Error Handling

Errors during streaming are emitted as `ErrorEvent` instances and thrown as exceptions:

```php
use Atlasphp\Atlas\Agents\Exceptions\AgentException;

try {
    foreach ($stream as $event) {
        if ($event instanceof ErrorEvent) {
            Log::warning('Stream error', [
                'type' => $event->errorType,
                'message' => $event->message,
                'recoverable' => $event->recoverable,
            ]);
        }

        if ($event instanceof TextDeltaEvent) {
            echo $event->text;
        }
    }
} catch (AgentException $e) {
    // Handle fatal errors
    Log::error('Stream failed', ['error' => $e->getMessage()]);
}

// Check for errors after streaming
if ($stream->hasErrors()) {
    foreach ($stream->errors() as $error) {
        // Handle each error
    }
}
```

## Event Properties Reference

### StreamStartEvent

```php
$event->id;         // Unique event ID (string)
$event->timestamp;  // Unix timestamp (int)
$event->model;      // Model being used, e.g., 'gpt-4o' (string)
$event->provider;   // Provider name, e.g., 'openai' (string)
$event->type();     // Returns 'stream.start'
$event->toArray();  // Convert to array
```

### TextDeltaEvent

```php
$event->id;         // Unique event ID (string)
$event->timestamp;  // Unix timestamp (int)
$event->text;       // Text chunk to append (string)
$event->type();     // Returns 'text.delta'
$event->toArray();  // Convert to array
```

### ToolCallStartEvent

```php
$event->id;         // Unique event ID (string)
$event->timestamp;  // Unix timestamp (int)
$event->toolId;     // Tool call identifier (string)
$event->toolName;   // Name of the tool, e.g., 'calculator' (string)
$event->arguments;  // Arguments passed, e.g., ['a' => 5, 'b' => 3] (array)
$event->type();     // Returns 'tool.call.start'
$event->toArray();  // Convert to array
```

### ToolCallEndEvent

```php
$event->id;         // Unique event ID (string)
$event->timestamp;  // Unix timestamp (int)
$event->toolId;     // Tool call identifier (string)
$event->toolName;   // Name of the tool (string)
$event->result;     // Tool execution result (string)
$event->success;    // Whether the tool call succeeded (bool)
$event->type();     // Returns 'tool.call.end'
$event->toArray();  // Convert to array
```

### StreamEndEvent

```php
$event->id;                 // Unique event ID (string)
$event->timestamp;          // Unix timestamp (int)
$event->finishReason;       // Why stream ended: 'stop', 'length', etc. (?string)
$event->usage;              // Token usage array (array)
$event->totalTokens();      // Total tokens used (int)
$event->promptTokens();     // Prompt tokens used (int)
$event->completionTokens(); // Completion tokens used (int)
$event->type();             // Returns 'stream.end'
$event->toArray();          // Convert to array
```

### ErrorEvent

```php
$event->id;          // Unique event ID (string)
$event->timestamp;   // Unix timestamp (int)
$event->errorType;   // Type of error (string)
$event->message;     // Error message (string)
$event->recoverable; // Whether the stream can continue (bool)
$event->type();      // Returns 'error'
$event->toArray();   // Convert to array
```

### ThinkingStartEvent

```php
$event->id;          // Unique event ID (string)
$event->timestamp;   // Unix timestamp (int)
$event->reasoningId; // Identifier for this thinking session (string)
$event->type();      // Returns 'thinking.start'
$event->toArray();   // Convert to array
```

### ThinkingDeltaEvent

```php
$event->id;          // Unique event ID (string)
$event->timestamp;   // Unix timestamp (int)
$event->delta;       // Thinking content chunk (string)
$event->reasoningId; // Identifier for this thinking session (string)
$event->summary;     // Summary metadata (array)
$event->type();      // Returns 'thinking.delta'
$event->toArray();   // Convert to array
```

### ThinkingCompleteEvent

```php
$event->id;          // Unique event ID (string)
$event->timestamp;   // Unix timestamp (int)
$event->reasoningId; // Identifier for this thinking session (string)
$event->summary;     // Summary of thinking (array)
$event->type();      // Returns 'thinking.complete'
$event->toArray();   // Convert to array
```

### CitationEvent

```php
$event->id;          // Unique event ID (string)
$event->timestamp;   // Unix timestamp (int)
$event->citation;    // Citation details as array (array)
$event->messageId;   // Associated message ID (string)
$event->blockIndex;  // Block index in response (int)
$event->metadata;    // Additional metadata (array)
$event->type();      // Returns 'citation'
$event->toArray();   // Convert to array
```

### ArtifactEvent

```php
$event->id;          // Unique event ID (string)
$event->timestamp;   // Unix timestamp (int)
$event->artifact;    // Artifact details as array (array)
$event->toolCallId;  // Associated tool call ID (string)
$event->toolName;    // Tool that generated the artifact (string)
$event->messageId;   // Associated message ID (string)
$event->type();      // Returns 'artifact'
$event->toArray();   // Convert to array
```

### StepStartEvent

```php
$event->id;          // Unique event ID (string)
$event->timestamp;   // Unix timestamp (int)
$event->type();      // Returns 'step.start'
$event->toArray();   // Convert to array
```

### StepFinishEvent

```php
$event->id;          // Unique event ID (string)
$event->timestamp;   // Unix timestamp (int)
$event->type();      // Returns 'step.finish'
$event->toArray();   // Convert to array
```

## Pipeline Hooks

Streaming uses shared pipelines with non-streaming execution, plus streaming-specific hooks:

| Hook | When Called | Data Available |
|------|-------------|----------------|
| `agent.before_execute` | Before execution begins | `agent`, `input`, `context` |
| `stream.on_event` | For each stream event | `event`, `agent`, `context` |
| `stream.after_complete` | After streaming completes | `agent`, `input`, `context`, `system_prompt` |
| `agent.on_error` | When an error occurs | `agent`, `input`, `context`, `exception` |

### Example: Stream Analytics Pipeline

```php
use Atlasphp\Atlas\Foundation\Contracts\PipelineContract;
use Atlasphp\Atlas\Streaming\Events\TextDeltaEvent;
use Closure;

class StreamAnalytics implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        $event = $data['event'];
        $agent = $data['agent'];

        if ($event instanceof TextDeltaEvent) {
            Metrics::increment('stream.text_deltas', [
                'agent' => $agent->key(),
            ]);
        }

        return $next($data);
    }
}

// Register in a service provider
$registry->register('stream.on_event', StreamAnalytics::class);
```

## Frontend Integration

### JavaScript EventSource

```javascript
const eventSource = new EventSource('/api/chat/stream');
let fullText = '';

eventSource.addEventListener('stream.start', (e) => {
    const data = JSON.parse(e.data);
    console.log(`Started streaming from ${data.provider}/${data.model}`);
});

eventSource.addEventListener('text.delta', (e) => {
    const data = JSON.parse(e.data);
    fullText += data.text;
    document.getElementById('response').textContent = fullText;
});

eventSource.addEventListener('tool.call.start', (e) => {
    const data = JSON.parse(e.data);
    showToolIndicator(`Executing ${data.tool_name}...`);
});

eventSource.addEventListener('tool.call.end', (e) => {
    const data = JSON.parse(e.data);
    hideToolIndicator();
});

eventSource.addEventListener('stream.end', (e) => {
    const data = JSON.parse(e.data);
    console.log('Stream complete', {
        finishReason: data.finish_reason,
        usage: data.usage,
    });
    eventSource.close();
});

eventSource.addEventListener('error', (e) => {
    console.error('Stream error', e);
    eventSource.close();
});
```

### SSE Format

When using `toResponse()`, events are formatted as Server-Sent Events:

```
event: stream.start
data: {"id":"evt_123","type":"stream.start","timestamp":1234567890,"model":"gpt-4o","provider":"openai"}

event: text.delta
data: {"id":"evt_124","type":"text.delta","timestamp":1234567890,"text":"Hello"}

event: text.delta
data: {"id":"evt_125","type":"text.delta","timestamp":1234567890,"text":" there!"}

event: stream.end
data: {"id":"evt_126","type":"stream.end","timestamp":1234567891,"finish_reason":"stop","usage":{"prompt_tokens":10,"completion_tokens":5,"total_tokens":15}}
```

## Complete Example: Chat with Persistence

Here's a complete example showing streaming with database persistence and real-time broadcasting:

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;
use Atlasphp\Atlas\Streaming\Events\{TextDeltaEvent, StreamEndEvent, ToolCallStartEvent};

class ChatService
{
    public function streamResponse(Conversation $conversation, string $userMessage): StreamResponse
    {
        // Save user message
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $userMessage,
        ]);

        // Get conversation history
        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        // Start streaming
        $stream = Atlas::agent('support-agent')
            ->withMessages($messages)
            ->withMetadata(['conversation_id' => $conversation->id])
            ->chat($userMessage, stream: true);

        // Process and broadcast events
        foreach ($stream as $event) {
            if ($event instanceof TextDeltaEvent) {
                broadcast(new ChatChunk($conversation->id, $event->text))->toOthers();
            }

            if ($event instanceof ToolCallStartEvent) {
                broadcast(new ToolExecuting($conversation->id, $event->toolName))->toOthers();
            }
        }

        // Save assistant response
        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $stream->text(),
            'metadata' => [
                'usage' => $stream->usage(),
                'tool_calls' => $stream->toolCalls(),
                'finish_reason' => $stream->finishReason(),
            ],
        ]);

        // Update conversation token count
        $conversation->increment('total_tokens', $stream->totalTokens());

        // Broadcast completion
        broadcast(new ChatComplete($conversation->id, $stream->usage()))->toOthers();

        return $stream;
    }
}
```

## Notes

- Streaming does not support structured output schemas
- Tool calls are executed during the stream; results appear as `ToolCallEndEvent`
- The `StreamResponse` is stateless - consumers control all persistence
- Use `collect()` only when you need aggregate data without manual iteration
- All three major providers (OpenAI, Anthropic, Gemini) are fully supported
