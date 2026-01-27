# Chat

Execute conversations with AI agents using the Atlas chat API. Atlas doesn't store conversation history. Your application manages persistence and passes history on each request.

::: tip Prism Reference
Atlas automatically builds messages into Prism's format. For detailed text generation documentation including message chains, see [Prism Text Generation](https://prismphp.com/core-concepts/text-generation.html).
:::

## Basic Chat

```php
use Atlasphp\Atlas\Atlas;

$response = Atlas::agent('support-agent')->chat('Hello, I need help with my order');

echo $response->text();
// "Hi! I'd be happy to help with your order. Could you provide your order number?"
```

## Chat with History

Pass conversation history for context:

```php
$response = Atlas::agent('support-agent')
    ->withMessages($messages)
    ->chat('Where is my package?');
```

## Message Format

Messages can be provided in two formats: array format for persistence/serialization, or Prism message objects for full Prism compatibility.

### Array Format

Ideal for storing in databases and JSON serialization:

```php
$messages = [
    ['role' => 'user', 'content' => 'Hello!'],
    ['role' => 'assistant', 'content' => 'Hi there! How can I help?'],
    ['role' => 'user', 'content' => 'I have a question about my order'],
];
```

### Prism Message Objects

For direct Prism compatibility and full API access:

```php
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;

$messages = [
    new SystemMessage('You are a helpful assistant.'),
    new UserMessage('Hello!'),
    new AssistantMessage('Hi there! How can I help?'),
];

$response = Atlas::agent('support-agent')
    ->withMessages($messages)
    ->chat('I have a question');
```

<div class="full-width-table">

| Role/Class | Description |
|------------|-------------|
| `user` / `UserMessage` | Messages from the user |
| `assistant` / `AssistantMessage` | Responses from the AI |
| `system` / `SystemMessage` | System instructions |

</div>

## Attachments

Add images, documents, audio, and video to chat requests for vision analysis and document processing. Atlas uses Prism media objects directly for a consistent API.

::: tip Prism Reference
For detailed input modalities documentation, see Prism's [Images](https://prismphp.com/input-modalities/images.html), [Documents](https://prismphp.com/input-modalities/documents.html), [Audio](https://prismphp.com/input-modalities/audio.html), and [Video](https://prismphp.com/input-modalities/video.html) guides.
:::

### Basic Attachments

Pass Prism media objects as the second argument to `chat()`:

```php
use Atlasphp\Atlas\Atlas;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\Media\Audio;
use Prism\Prism\ValueObjects\Media\Video;

// Image from URL
$response = Atlas::agent('vision-agent')
    ->chat('What do you see in this image?', [
        Image::fromUrl('https://example.com/photo.jpg'),
    ]);

// Document
$response = Atlas::agent('analyzer')
    ->chat('Summarize this document', [
        Document::fromUrl('https://example.com/report.pdf'),
    ]);

// Audio
$response = Atlas::agent('transcriber')
    ->chat('What is being said?', [
        Audio::fromUrl('https://example.com/speech.mp3'),
    ]);

// Video
$response = Atlas::agent('analyzer')
    ->chat('Describe what happens', [
        Video::fromUrl('https://example.com/clip.mp4'),
    ]);
```

### Source Types

Prism media objects support multiple source types:

```php
use Prism\Prism\ValueObjects\Media\Image;

// From URL
Image::fromUrl('https://example.com/image.jpg')

// From local file path
Image::fromLocalPath('/path/to/image.png')

// From base64 data
Image::fromBase64($base64Data, 'image/png')

// From Laravel Storage
Image::fromStoragePath('images/photo.png', 'image/png', 's3')

// From provider file ID
Image::fromFileId('file-abc123')
```

The same methods are available on `Document`, `Audio`, and `Video` classes.

### Multiple Attachments

```php
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Media\Document;

// Multiple images
$response = Atlas::agent('vision')
    ->chat('Compare these images', [
        Image::fromUrl('https://example.com/before.jpg'),
        Image::fromUrl('https://example.com/after.jpg'),
    ]);

// Different media types
$response = Atlas::agent('analyzer')
    ->chat('Explain the chart using the document data', [
        Image::fromUrl('https://example.com/chart.png'),
        Document::fromUrl('https://example.com/data.pdf'),
    ]);
```

### Builder Style

For cases where you need to configure attachments separately, use `withMedia()`:

```php
use Prism\Prism\ValueObjects\Media\Image;

$response = Atlas::agent('vision')
    ->withMedia([
        Image::fromUrl('https://example.com/photo.jpg'),
    ])
    ->chat('Describe this image');

// Both styles can be combined (attachments are merged)
$response = Atlas::agent('vision')
    ->withMedia([Image::fromUrl('https://example.com/photo1.jpg')])
    ->chat('Compare with this', [
        Image::fromUrl('https://example.com/photo2.jpg'),
    ]);
```

::: warning Provider Support
Not all providers support all media types. Check your provider's documentation for supported modalities.
:::

## Multi-Turn Conversations

Use the fluent builder to configure all context:

```php
$response = Atlas::agent('support-agent')
    ->withMessages($messages)
    ->withVariables([
        'user_name' => 'Alice',
        'account_tier' => 'premium',
    ])
    ->withMetadata([
        'user_id' => 123,
        'session_id' => 'abc-456',
    ])
    ->chat('Check my recent orders');
```

### Configuration Methods

<div class="full-width-table">

| Method | Description |
|--------|-------------|
| `withMessages(array $messages)` | Conversation history (array format or Prism message objects) |
| `withVariables(array $variables)` | Variables for system prompt interpolation |
| `withMetadata(array $metadata)` | Metadata for pipeline middleware and tools |
| `withProvider(string $provider, ?string $model)` | Override agent's provider/model |
| `withSchema(Schema $schema)` | Schema for structured output ([details](/capabilities/structured-output)) |
| `withMedia(array $media)` | Attach Prism media objects via builder pattern |
| `withTools(array $tools)` | Add Atlas tools at runtime (accumulates) |
| `withMcpTools(array $tools)` | Add MCP tools at runtime ([details](/capabilities/mcp)) |

</div>

All Prism methods are available via pass-through (e.g., `withToolChoice()`, `withMaxTokens()`).

## Prism Passthrough Methods

Atlas provides full access to all Prism methods through passthrough. Any method available on Prism's generator can be called directly on the Atlas agent builder:

```php
use Prism\Prism\Enums\ToolChoice;

$response = Atlas::agent('support-agent')
    ->withToolChoice(ToolChoice::Any)      // Force tool usage
    ->withClientRetry(3, 100)              // Retry with backoff
    ->usingTopP(0.9)                       // Top-p sampling
    ->withMaxTokens(2000)                  // Limit response length
    ->chat('Help me find my order');
```

### Common Passthrough Methods

<div class="full-width-table">

| Method | Description |
|--------|-------------|
| `withToolChoice(ToolChoice $choice)` | Control how tools are selected (`Auto`, `Any`, `None`) |
| `withClientRetry(int $times, int $sleepMs)` | Automatic retries with backoff |
| `usingTopP(float $topP)` | Top-p (nucleus) sampling |
| `usingTopK(int $topK)` | Top-k sampling |
| `withMaxTokens(int $tokens)` | Maximum response tokens |
| `withClientOptions(array $options)` | HTTP client configuration |
| `withProviderOptions(array $options)` | Provider-specific options |

</div>

See [Prism Text Generation](https://prismphp.com/core-concepts/text-generation.html) for the complete API.

## Response Handling

Chat operations return an `AgentResponse` that wraps Prism's response with agent context:

```php
// Text response (backward compatible property access)
echo $response->text();

// Token usage
echo $response->usage->promptTokens;
echo $response->usage->completionTokens;

// Agent context access
echo $response->agentKey();       // The agent key
echo $response->systemPrompt;     // The system prompt used
$response->metadata();            // Pipeline metadata
$response->variables();           // Variables used

// Full Prism response access
$prismResponse = $response->response;
```

For structured output, check `$response->isStructured()` and use `$response->structured()` to get the data.

See [Prism Response](https://prismphp.com/core-concepts/text-generation.html#the-response-object) for the underlying Prism response API.

## API Reference

```php
// Agent chat fluent API
Atlas::agent(string|AgentContract $agent)
    // Context configuration
    ->withMessages(array $messages)                       // Conversation history
    ->withVariables(array $variables)                     // System prompt variables
    ->withMetadata(array $metadata)                       // Pipeline/tool metadata

    // Provider overrides
    ->withProvider(string $provider, ?string $model)      // Override provider/model
    ->withModel(string $model)                            // Override model only

    // Attachments
    ->withMedia(Image|Document|Audio|Video|array $media)  // Attach media (builder style)

    // Tools
    ->withTools(array $tools)                             // Add Atlas tools at runtime
    ->withMcpTools(array $tools)                          // Add MCP tools at runtime

    // Structured output
    ->withSchema(SchemaBuilder|ObjectSchema $schema)      // Schema for structured response
    ->usingAutoMode()                                     // Auto schema mode (default)
    ->usingNativeMode()                                   // Native JSON schema mode
    ->usingJsonMode()                                     // JSON mode (for optional fields)

    // Prism passthrough methods
    ->withToolChoice(ToolChoice $choice)                  // Tool selection (Auto, Any, None)
    ->withMaxTokens(int $tokens)                          // Max response tokens
    ->withTemperature(float $temp)                        // Sampling temperature
    ->usingTopP(float $topP)                              // Top-p sampling
    ->usingTopK(int $topK)                                // Top-k sampling
    ->withClientRetry(int $times, int $sleepMs)           // Retry with backoff
    ->withClientOptions(array $options)                   // HTTP client options
    ->withProviderOptions(array $options)                 // Provider-specific options

    // Execution
    ->chat(string $input, array $attachments = []): AgentResponse;
    ->stream(string $input, array $attachments = []): AgentStreamResponse;

// AgentResponse properties (backward compatible via __get magic)
$response->text();                  // Generated text
$response->usage->promptTokens;     // Input tokens
$response->usage->completionTokens; // Output tokens
$response->steps;                   // Multi-step agentic loop history
$response->toolCalls;               // Tool calls made
$response->toolResults;             // Tool execution results
$response->finishReason;            // FinishReason enum
$response->meta;                    // Request metadata

// AgentResponse agent context
$response->agentKey();              // Agent key
$response->agentName();             // Agent name
$response->systemPrompt;            // System prompt used
$response->metadata();              // Pipeline metadata
$response->variables();             // Variables used
$response->response;                // Full Prism response (PrismResponse|StructuredResponse)

// Structured output (when using withSchema)
$response->isStructured();          // Check if structured response
$response->structured();            // Get structured data (or null)

// Message formats for withMessages()
// Array format (for persistence):
[
    ['role' => 'user', 'content' => 'Hello'],
    ['role' => 'assistant', 'content' => 'Hi there!'],
]

// Prism message objects (for full API access):
[
    new UserMessage('Hello'),
    new AssistantMessage('Hi there!'),
]

// Prism media objects for attachments
Image::fromUrl(string $url): Image;
Image::fromLocalPath(string $path): Image;
Image::fromBase64(string $data, string $mimeType): Image;
Image::fromStoragePath(string $path, string $mimeType, ?string $disk): Image;
Image::fromFileId(string $fileId): Image;
// Same methods available on Document, Audio, Video
```

## Next Steps

- [Streaming](/capabilities/streaming) — Real-time streaming responses
- [Structured](/capabilities/structured-output) — Schema-based responses
- [MCP](/capabilities/mcp) — External tools from MCP servers
