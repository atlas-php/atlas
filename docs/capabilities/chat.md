# Chat

Execute conversations with AI agents using the Atlas chat API. Atlas doesn't store conversation history—your application manages persistence and passes history on each request.

::: tip Prism Reference
Atlas automatically builds messages into Prism's format. For detailed text generation documentation including message chains, see [Prism Text Generation](https://prismphp.com/core-concepts/text-generation.html).
:::

## Basic Chat

```php
use Atlasphp\Atlas\Atlas;

$response = Atlas::agent('support-agent')->chat('Hello, I need help with my order');

echo $response->text;
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

Messages follow the standard chat format:

```php
$messages = [
    ['role' => 'user', 'content' => 'Hello!'],
    ['role' => 'assistant', 'content' => 'Hi there! How can I help?'],
    ['role' => 'user', 'content' => 'I have a question about my order'],
];
```

<div class="full-width-table">

| Role | Description |
|------|-------------|
| `user` | Messages from the user |
| `assistant` | Responses from the AI |

</div>

## Attachments

Add images, documents, audio, and video to chat requests for vision analysis and document processing.

::: tip Prism Reference
For detailed input modalities documentation, see Prism's [Images](https://prismphp.com/input-modalities/images.html), [Documents](https://prismphp.com/input-modalities/documents.html), [Audio](https://prismphp.com/input-modalities/audio.html), and [Video](https://prismphp.com/input-modalities/video.html) guides.
:::

### Basic Attachments

```php
use Atlasphp\Atlas\Atlas;

// Image from URL (default)
$response = Atlas::agent('vision-agent')
    ->withImage('https://example.com/photo.jpg')
    ->chat('What do you see in this image?');

// Document
$response = Atlas::agent('analyzer')
    ->withDocument('https://example.com/report.pdf')
    ->chat('Summarize this document');

// Audio
$response = Atlas::agent('transcriber')
    ->withAudio('https://example.com/speech.mp3')
    ->chat('What is being said?');

// Video
$response = Atlas::agent('analyzer')
    ->withVideo('https://example.com/clip.mp4')
    ->chat('Describe what happens');
```

### Source Types

Use `MediaSource` to specify how media data should be loaded:

```php
use Atlasphp\Atlas\Agents\Enums\MediaSource;

// URL (default)
->withImage('https://example.com/image.jpg')

// Local file path
->withImage('/path/to/image.png', MediaSource::LocalPath)

// Base64 with MIME type
->withImage($base64Data, MediaSource::Base64, 'image/png')

// Laravel Storage path with disk
->withImage('images/photo.png', MediaSource::StoragePath, 'image/png', 's3')

// Provider file ID
->withImage('file-abc123', MediaSource::FileId)
```

### Multiple Attachments

```php
// Multiple images
$response = Atlas::agent('vision')
    ->withImage([
        'https://example.com/before.jpg',
        'https://example.com/after.jpg',
    ])
    ->chat('Compare these images');

// Different media types
$response = Atlas::agent('analyzer')
    ->withImage('https://example.com/chart.png')
    ->withDocument('https://example.com/data.pdf')
    ->chat('Explain the chart using the document data');
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
| `withMessages(array $messages)` | Conversation history array |
| `withVariables(array $variables)` | Variables for system prompt interpolation |
| `withMetadata(array $metadata)` | Metadata for pipeline middleware and tools |
| `withProvider(string $provider, ?string $model)` | Override agent's provider/model |
| `withSchema(Schema $schema)` | Schema for structured output ([details](/capabilities/structured-output)) |
| `withImage/withDocument/withAudio/withVideo` | Attach media ([details](#attachments)) |

</div>

All Prism methods are available via pass-through (e.g., `withToolChoice()`, `withMaxTokens()`).

## Response Handling

Chat operations return Prism's response object directly:

```php
// Text response
echo $response->text;

// Token usage
echo $response->usage->promptTokens;
echo $response->usage->completionTokens;

// Check for content
if ($response->text !== null) {
    // Handle text response
}
```

See [Prism Response](https://prismphp.com/core-concepts/text-generation.html#the-response-object) for the complete response API.

## Next Steps

- [Streaming](/capabilities/streaming) — Real-time streaming responses
- [Structured](/capabilities/structured-output) — Schema-based responses
