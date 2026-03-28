# Text

Generate text, stream responses, and extract structured data from AI models.

## Quick Example

```php
use Atlasphp\Atlas\Atlas;

$response = Atlas::text('openai', 'gpt-4o')
    ->instructions('You are a helpful assistant.')
    ->message('What is Laravel?')
    ->asText();

echo $response->text;
```

## Text Generation

```php
$response = Atlas::text('openai', 'gpt-4o')
    ->instructions('You are a technical writer.')
    ->withMaxTokens(500)
    ->withTemperature(0.7)
    ->message('Explain dependency injection.')
    ->asText();

$response->text;          // Generated text
$response->usage;         // Usage object (inputTokens, outputTokens)
$response->finishReason;  // FinishReason enum (Stop, Length, ToolCalls, etc.)
```

### With Conversation History

When using [conversations](/guides/conversations), Atlas automatically loads message history — you don't need to manage it yourself. Just use `->for($user)` or `->forConversation($id)` on an agent and Atlas handles the rest.

For stateless usage without persistence, you can manually pass conversation history:

```php
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Messages\AssistantMessage;

$response = Atlas::text('openai', 'gpt-4o')
    ->instructions('You are a helpful assistant.')
    ->withMessages([
        new UserMessage('What is PHP?'),
        new AssistantMessage('PHP is a server-side scripting language.'),
    ])
    ->message('Tell me more about its history.')
    ->asText();
```

::: tip Automatic History
Most applications should use [conversations](/guides/conversations) instead of `withMessages()`. Atlas loads history, manages limits, and persists new messages automatically. Use `withMessages()` only when you need full manual control over what the model sees.
:::

### With Media Input (Vision)

```php
use Atlasphp\Atlas\Input\Image;

$response = Atlas::text('openai', 'gpt-4o')
    ->instructions('Describe what you see.')
    ->message('What is in this image?', Image::fromUrl('https://example.com/photo.jpg'))
    ->asText();
```

## Usage & Token Tracking

Every response includes a `Usage` object with detailed token consumption. Use it for cost tracking, budgeting, and monitoring.

```php
$response = Atlas::text('openai', 'gpt-4o')
    ->message('Explain quantum computing')
    ->asText();

$usage = $response->usage;

$usage->inputTokens;       // Tokens in the prompt/input
$usage->outputTokens;      // Tokens in the generated response
$usage->totalTokens();     // inputTokens + outputTokens
$usage->reasoningTokens;   // Tokens used for reasoning/thinking (Anthropic, OpenAI o-series)
$usage->cachedTokens;      // Tokens served from provider cache (reduced cost)
$usage->cacheWriteTokens;  // Tokens written to provider cache
```

### Usage Properties

| Property | Type | Description |
|----------|------|-------------|
| `inputTokens` | `int` | Tokens consumed by the prompt, instructions, and conversation history |
| `outputTokens` | `int` | Tokens generated in the response |
| `reasoningTokens` | `?int` | Tokens used for internal reasoning (e.g. Anthropic extended thinking, OpenAI o1) |
| `cachedTokens` | `?int` | Input tokens served from the provider's prompt cache |
| `cacheWriteTokens` | `?int` | Input tokens written to the provider's prompt cache |

### Cost Estimation

```php
$cost = ($usage->inputTokens * 0.0025 / 1000)
      + ($usage->outputTokens * 0.01 / 1000);

// Account for cached tokens (typically cheaper)
$cachedDiscount = ($usage->cachedTokens ?? 0) * 0.00125 / 1000;
$adjustedCost = $cost - $cachedDiscount;
```

### Multi-Step Usage

When tools are involved, usage is accumulated across all steps in the tool loop:

```php
$response = Atlas::text('openai', 'gpt-4o')
    ->withTools([WeatherTool::class])
    ->message('What is the weather?')
    ->asText();

// Total usage across all round trips
$response->usage->inputTokens;   // Sum of all step inputs
$response->usage->outputTokens;  // Sum of all step outputs
$response->usage->totalTokens(); // Grand total

// Per-step breakdown
foreach ($response->steps as $step) {
    echo "Step: {$step->usage->inputTokens} in / {$step->usage->outputTokens} out\n";
}
```

::: tip All Modalities
`Usage` is available on text, structured, and embeddings responses. Image, audio, video, moderation, and reranking responses may not include token usage depending on the provider.
:::

## Streaming

Stream responses in real-time. `asStream()` returns a `StreamResponse` that implements `IteratorAggregate`. For complete details on SSE delivery, broadcasting, frontend integration, and testing, see the [Streaming Guide](/guides/streaming).

### Basic Streaming

```php
$stream = Atlas::text('openai', 'gpt-4o')
    ->instructions('Tell me a story.')
    ->message('Once upon a time...')
    ->asStream();

foreach ($stream as $chunk) {
    echo $chunk->text;
}
```

### SSE Response (Server-Sent Events)

Return a stream directly from a Laravel route:

```php
Route::get('/stream', function () {
    return Atlas::text('openai', 'gpt-4o')
        ->message('Tell me a joke')
        ->asStream();
});
```

`StreamResponse` implements Laravel's `Responsable` — it automatically sends SSE headers.

### Broadcasting

Broadcast chunks to a WebSocket channel:

```php
use Illuminate\Broadcasting\Channel;

$stream = Atlas::text('openai', 'gpt-4o')
    ->message('Explain quantum physics')
    ->asStream();

$stream->broadcastOn(new Channel('chat.1'));

foreach ($stream as $chunk) {
    // Chunks are automatically broadcast as StreamChunkReceived events
}
```

### Callbacks

```php
$stream = Atlas::text('openai', 'gpt-4o')
    ->message('Hello')
    ->asStream();

$stream
    ->onChunk(function ($chunk) {
        // Called for each chunk
    })
    ->then(function ($stream) {
        // Called after stream completes
        $fullText = $stream->getText();
        $usage = $stream->getUsage();
    });

foreach ($stream as $chunk) {
    // consume
}
```

### Stream Accessors

After iteration, access accumulated data:

```php
$stream->getText();         // Full accumulated text
$stream->getUsage();        // Usage (available after last chunk)
$stream->getFinishReason(); // FinishReason
$stream->getToolCalls();    // Tool calls (if any)
$stream->getReasoning();    // Thinking/reasoning content (if model supports it)
```

### Streaming with Tools

When tools are present, `asStream()` runs the tool loop synchronously, then streams the results — yielding tool call chunks followed by text segments:

```php
$stream = Atlas::text('openai', 'gpt-4o')
    ->message('What is the weather in NYC?')
    ->withTools([WeatherTool::class])
    ->asStream();

foreach ($stream as $chunk) {
    match ($chunk->type) {
        ChunkType::ToolCall => handleToolCalls($chunk->toolCalls),
        ChunkType::Text     => echo $chunk->text,
        ChunkType::Done     => handleCompletion($chunk->usage),
        default             => null,
    };
}
```

### Chainable Callbacks

Multiple `then()` callbacks can be registered — they fire in order after the stream completes:

```php
$stream
    ->then(fn ($s) => Log::info('Completed', ['tokens' => $s->getUsage()?->totalTokens()]))
    ->then(fn ($s) => cache()->put('last_response', $s->getText()));
```

## Structured Output

Extract typed data using a JSON Schema definition:

```php
use Atlasphp\Atlas\Schema\Schema;

$schema = Schema::object('analysis', 'Sentiment analysis')
    ->enum('sentiment', 'Overall sentiment', ['positive', 'negative', 'neutral'])
    ->number('confidence', 'Confidence score 0-1')
    ->stringArray('keywords', 'Key topics')
    ->build();

$response = Atlas::text('openai', 'gpt-4o')
    ->instructions('Analyze the sentiment of the given text.')
    ->withSchema($schema)
    ->message('I love this product! Fast shipping and great quality.')
    ->asStructured();

$data = $response->structured;
// ['sentiment' => 'positive', 'confidence' => 0.95, 'keywords' => ['product', 'shipping', 'quality']]
```

See [Schema](/features/schema) for the full field type reference.

## Tool Calling

Use tools without agents for inline tool execution:

```php
$response = Atlas::text('openai', 'gpt-4o')
    ->instructions('Help the user with weather information.')
    ->withTools([WeatherTool::class])
    ->message('What is the weather in Paris?')
    ->asText();

$response->text;   // Final response after tool execution
$response->steps;  // Each round trip in the tool loop
```

## Queue Support

Dispatch any request to a queue by calling `->queue()` before the terminal method. The terminal method (`asText`, `asStream`, etc.) returns a `PendingExecution` instead of a response:

```php
$pending = Atlas::text('openai', 'gpt-4o')
    ->message('Write a long essay about AI')
    ->queue()
    ->asText();

$pending->executionId;  // Available immediately for UI tracking
```

The job dispatches automatically when `$pending` goes out of scope. You can also chain callbacks or dispatch explicitly:

```php
Atlas::text('openai', 'gpt-4o')
    ->message('Write a long essay about AI')
    ->queue()
    ->asText()
    ->then(fn ($response) => logger()->info($response->text))
    ->catch(fn ($e) => logger()->error($e->getMessage()));
```

### Queue Options

```php
// Custom queue name
Atlas::text('openai', 'gpt-4o')
    ->message('Generate report')
    ->queue('atlas-heavy')
    ->asText();

// Shorthand — queue() accepts a queue name
Atlas::text('openai', 'gpt-4o')
    ->message('Generate report')
    ->queue('atlas-heavy')
    ->asText();

// Custom connection
Atlas::text('openai', 'gpt-4o')
    ->message('Generate report')
    ->queue()
    ->onConnection('redis')
    ->asText();

// Delay execution
Atlas::text('openai', 'gpt-4o')
    ->queue()
    ->withQueueDelay(300)
    ->message('Follow up in 5 minutes')
    ->asText();

// Broadcast results to a WebSocket channel
use Illuminate\Broadcasting\Channel;

Atlas::text('openai', 'gpt-4o')
    ->message('Analyze this data')
    ->queue()
    ->broadcastOn(new Channel('execution.' . $user->id))
    ->asText();
```

### Queue Configuration

Default queue settings in `config/atlas.php`:

```php
'queue' => [
    'connection' => env('ATLAS_QUEUE_CONNECTION'),  // null = default
    'queue' => env('ATLAS_QUEUE', 'default'),
    'tries' => (int) env('ATLAS_QUEUE_TRIES', 3),
    'backoff' => (int) env('ATLAS_QUEUE_BACKOFF', 30),
    'timeout' => (int) env('ATLAS_QUEUE_TIMEOUT', 300),
    'after_commit' => (bool) env('ATLAS_QUEUE_AFTER_COMMIT', true),
],
```

For per-request queue overrides (`withQueueTimeout()`, `withQueueTries()`, `withQueueBackoff()`), HTTP retry (`withTimeout()`, `withRetry()`, `withoutRetry()`), and execution tracking, see the [Queue & Background Jobs](/guides/queue) guide.

## Provider Options

Pass provider-specific options:

```php
$response = Atlas::text('openai', 'gpt-4o')
    ->withProviderOptions(['seed' => 12345, 'top_p' => 0.9])
    ->message('Hello')
    ->asText();
```

## TextResponse

| Property | Type | Description |
|----------|------|-------------|
| `text` | `string` | Generated text |
| `usage` | `Usage` | Token counts (inputTokens, outputTokens, reasoningTokens, cachedTokens) |
| `finishReason` | `FinishReason` | Why generation stopped (Stop, Length, ToolCalls, ContentFilter) |
| `toolCalls` | `array` | Tool calls from the response |
| `reasoning` | `?string` | Reasoning/thinking content (if supported) |
| `steps` | `array` | Tool loop history (when tools are used) |
| `meta` | `array` | Additional metadata |
| `providerToolCalls` | `array` | Provider-executed tool invocations (web_search_call, code_interpreter_call, etc.) |
| `annotations` | `array` | Content annotations from the provider (url_citation, file_citation) |

## StreamResponse

| Method | Returns | Description |
|--------|---------|-------------|
| `broadcastOn(Channel)` | `static` | Broadcast chunks to a channel |
| `onChunk(Closure)` | `static` | Callback for each chunk |
| `then(Closure)` | `static` | Callback after stream completes (chainable — multiple allowed) |
| `getText()` | `string` | Accumulated text (after iteration) |
| `getUsage()` | `?Usage` | Token usage (after iteration) |
| `getFinishReason()` | `?FinishReason` | Finish reason (after iteration) |
| `getToolCalls()` | `array` | Tool calls (after iteration) |
| `getReasoning()` | `string` | Thinking/reasoning content (after iteration) |
| `toResponse($request)` | `StreamedResponse` | Convert to SSE HTTP response |

## StructuredResponse

| Property | Type | Description |
|----------|------|-------------|
| `structured` | `array` | Parsed structured data matching the schema |
| `usage` | `Usage` | Token counts |
| `finishReason` | `FinishReason` | Why generation stopped |

## Builder Reference

| Method | Description |
|--------|-------------|
| `instructions(string)` | Set system instructions |
| `message(string, Media)` | Set user message with optional media |
| `withMessages(array)` | Set conversation history |
| `withMaxTokens(int)` | Maximum response tokens |
| `withTemperature(float)` | Sampling temperature |
| `withSchema(Schema)` | Schema for structured output |
| `withTools(array)` | Add tools for auto tool calling |
| `withProviderTools(array)` | Add provider tools (WebSearch, etc.) |
| `withMaxSteps(?int)` | Max tool loop iterations |
| `withConcurrent(bool)` | Enable concurrent tool execution |
| `withProviderOptions(array)` | Provider-specific options |
| `withVariables(array)` | Variables for instruction interpolation |
| `withMeta(array)` | Metadata passed to middleware/events |
| `withMiddleware(array)` | Per-request provider middleware |
| `queue()` | Dispatch to queue instead of inline |
