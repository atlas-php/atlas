# Multimodal Attachments

Add images, documents, audio, and video to your agent conversations for vision analysis, document processing, and audio understanding.

## Overview

Atlas supports multimodal input through a fluent attachment API. Attach media to chat requests and the AI can analyze, describe, and respond to the content.

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

$response = Atlas::agent('vision-agent')
    ->withImage('https://example.com/photo.jpg')
    ->chat('What do you see in this image?');

echo $response->text;
// "The image shows a sunset over a mountain range with..."
```

## Provider Support

Not all providers support all media types. Here's the compatibility matrix:

| Provider | Images | Documents | Audio | Video |
|----------|--------|-----------|-------|-------|
| OpenAI (GPT-4o) | ✅ | ❌ | ❌ | ❌ |
| Anthropic (Claude) | ✅ | ✅ (PDF) | ❌ | ❌ |
| Gemini | ✅ | ✅ | ✅ | ✅ |

## Media Types

### Images

```php
use Atlasphp\Atlas\Providers\Enums\MediaSource;

// From URL (default)
$response = Atlas::agent('vision')
    ->withImage('https://example.com/photo.jpg')
    ->chat('Describe this image');

// From local file path
$response = Atlas::agent('vision')
    ->withImage('/path/to/image.png', MediaSource::LocalPath)
    ->chat('What colors are in this image?');

// From base64 data
$response = Atlas::agent('vision')
    ->withImage($base64Data, MediaSource::Base64, 'image/png')
    ->chat('Analyze this screenshot');

// Multiple images in one request
$response = Atlas::agent('vision')
    ->withImage([
        'https://example.com/before.jpg',
        'https://example.com/after.jpg',
    ])
    ->chat('Compare these two images');
```

### Documents

```php
// From URL
$response = Atlas::agent('analyzer')
    ->withDocument('https://example.com/report.pdf')
    ->chat('Summarize this document');

// With title
$response = Atlas::agent('analyzer')
    ->withDocument(
        'https://example.com/report.pdf',
        MediaSource::Url,
        null,
        'Q4 Financial Report'
    )
    ->chat('What are the key findings?');

// From local path
$response = Atlas::agent('analyzer')
    ->withDocument('/path/to/document.pdf', MediaSource::LocalPath, 'application/pdf')
    ->chat('Extract the main points');
```

### Audio

```php
// From URL
$response = Atlas::agent('transcriber')
    ->withAudio('https://example.com/speech.mp3')
    ->chat('What is being said in this audio?');

// From local file
$response = Atlas::agent('transcriber')
    ->withAudio('/path/to/audio.mp3', MediaSource::LocalPath, 'audio/mpeg')
    ->chat('Transcribe this recording');
```

### Video

```php
// From URL
$response = Atlas::agent('analyzer')
    ->withVideo('https://example.com/clip.mp4')
    ->chat('Describe what happens in this video');

// From local file
$response = Atlas::agent('analyzer')
    ->withVideo('/path/to/video.mp4', MediaSource::LocalPath, 'video/mp4')
    ->chat('What is the main subject?');
```

## Source Types

Atlas supports five media source types:

| Source | Description | Example |
|--------|-------------|---------|
| `Url` | Remote URL (default) | `https://example.com/image.jpg` |
| `Base64` | Base64-encoded data | `iVBORw0KGgo...` |
| `LocalPath` | Absolute file path | `/var/www/storage/image.png` |
| `StoragePath` | Laravel Storage path | `images/photo.png` |
| `FileId` | Provider file reference | `file-abc123` |

### Using MediaSource Enum

```php
use Atlasphp\Atlas\Providers\Enums\MediaSource;

// URL (default - enum optional)
->withImage('https://example.com/image.jpg')
->withImage('https://example.com/image.jpg', MediaSource::Url)

// Base64 with MIME type
->withImage($base64, MediaSource::Base64, 'image/png')

// Local file path
->withImage('/absolute/path/to/file.png', MediaSource::LocalPath)

// Laravel Storage path with disk
->withImage('images/photo.png', MediaSource::StoragePath, 'image/png', 's3')

// Provider file ID
->withImage('file-abc123', MediaSource::FileId)
```

## Storage Disk Support

When using `StoragePath`, you can specify which Laravel Storage disk to use:

```php
// Default disk
$response = Atlas::agent('vision')
    ->withImage('images/photo.png', MediaSource::StoragePath)
    ->chat('Describe this image');

// Specific disk (e.g., S3)
$response = Atlas::agent('vision')
    ->withImage('images/photo.png', MediaSource::StoragePath, 'image/png', 's3')
    ->chat('Describe this image');

// Documents with disk
$response = Atlas::agent('analyzer')
    ->withDocument(
        'documents/report.pdf',
        MediaSource::StoragePath,
        'application/pdf',
        'Q4 Report',
        'private'  // disk name
    )
    ->chat('Summarize this document');

// Audio with disk
$response = Atlas::agent('transcriber')
    ->withAudio('audio/recording.mp3', MediaSource::StoragePath, 'audio/mpeg', 'local')
    ->chat('Transcribe this');
```

## Message History with Attachments

Attachments can be included in conversation history for multi-turn interactions:

```php
// Load conversation from database
$messages = [
    [
        'role' => 'user',
        'content' => 'Look at this product image',
        'attachments' => [
            [
                'type' => 'image',
                'source' => 'url',
                'data' => 'https://example.com/product.jpg',
            ],
        ],
    ],
    [
        'role' => 'assistant',
        'content' => 'I can see a blue ceramic mug with a minimalist design.',
    ],
];

// Continue conversation referencing the previous image
$response = Atlas::agent('vision')
    ->withMessages($messages)
    ->chat('What material do you think it is made of?');
```

### Attachment Array Format

Attachments use a serializable format for database storage:

```php
[
    'type' => 'image',           // image, document, audio, video
    'source' => 'url',           // url, base64, local_path, storage_path, file_id
    'data' => 'https://...',     // The URL, path, base64 data, or file ID
    'mime_type' => 'image/jpeg', // Optional MIME type
    'title' => 'My Document',    // Optional (documents only)
    'disk' => 'local',           // Optional (storage_path only)
]
```

### Persisting Conversations

```php
// Save message with attachment to database
ConversationMessage::create([
    'conversation_id' => $conversation->id,
    'role' => 'user',
    'content' => 'Analyze this image',
    'attachments' => [
        [
            'type' => 'image',
            'source' => 'storage_path',
            'data' => 'uploads/image.png',
            'disk' => 's3',
        ],
    ],
]);

// Load and continue conversation
$messages = ConversationMessage::where('conversation_id', $id)
    ->orderBy('created_at')
    ->get()
    ->map(fn($m) => [
        'role' => $m->role,
        'content' => $m->content,
        'attachments' => $m->attachments ?? [],
    ])
    ->toArray();

$response = Atlas::agent('vision')
    ->withMessages($messages)
    ->chat('What else can you tell me about it?');
```

## Combining Multiple Attachments

Chain different media types in a single request:

```php
$response = Atlas::agent('analyzer')
    ->withImage('https://example.com/chart.png')
    ->withDocument('https://example.com/data.pdf')
    ->chat('Explain the chart using the data from the document');
```

Or use arrays for multiple items of the same type:

```php
$response = Atlas::agent('vision')
    ->withImage([
        'https://example.com/img1.jpg',
        'https://example.com/img2.jpg',
        'https://example.com/img3.jpg',
    ])
    ->chat('Which image shows the best lighting?');
```

## Pipeline Access for Auditing

Attachments are visible in pipeline middleware for logging, auditing, or validation:

```php
use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;

// Register middleware to audit attachments
$registry->register('agent.before_execute', new class implements PipelineContract {
    public function handle(array $data, Closure $next): mixed
    {
        $context = $data['context'];

        // Log current input attachments
        if ($context->hasCurrentAttachments()) {
            foreach ($context->currentAttachments as $attachment) {
                AuditLog::create([
                    'type' => 'attachment_sent',
                    'media_type' => $attachment['type'],
                    'source' => $attachment['source'],
                    'user_id' => $context->getMeta('user_id'),
                    'timestamp' => now(),
                ]);
            }
        }

        // Log attachments in message history
        foreach ($context->messages as $message) {
            foreach ($message['attachments'] ?? [] as $attachment) {
                // Process historical attachments
            }
        }

        return $next($data);
    }
});
```

### ExecutionContext Attachment Properties

The `ExecutionContext` provides access to attachments:

```php
// In pipeline middleware
$context = $data['context'];

// Current input attachments (from withImage, withDocument, etc.)
$context->currentAttachments;          // Array of attachment arrays
$context->hasCurrentAttachments();     // Boolean check

// Historical attachments in messages
foreach ($context->messages as $message) {
    $attachments = $message['attachments'] ?? [];
}
```

## API Reference

### Attachment Methods

| Method | Description |
|--------|-------------|
| `withImage($data, $source, $mimeType, $disk)` | Attach image(s) |
| `withDocument($data, $source, $mimeType, $title, $disk)` | Attach document(s) |
| `withAudio($data, $source, $mimeType, $disk)` | Attach audio file(s) |
| `withVideo($data, $source, $mimeType, $disk)` | Attach video file(s) |

All methods accept:
- `$data`: Single string or array of strings
- `$source`: `MediaSource` enum (default: `Url`)
- `$mimeType`: Optional MIME type string
- `$disk`: Optional Laravel storage disk name (for `StoragePath` source)

### MediaSource Enum

```php
use Atlasphp\Atlas\Providers\Enums\MediaSource;

MediaSource::Url          // Remote URL
MediaSource::Base64       // Base64-encoded data
MediaSource::LocalPath    // Absolute file path
MediaSource::StoragePath  // Laravel Storage path
MediaSource::FileId       // Provider file reference
```

### MediaType Enum

```php
use Atlasphp\Atlas\Providers\Enums\MediaType;

MediaType::Image
MediaType::Document
MediaType::Audio
MediaType::Video
```

## Complete Example

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;
use Atlasphp\Atlas\Providers\Enums\MediaSource;

class ImageAnalysisController extends Controller
{
    public function analyze(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
            'question' => 'required|string|max:500',
        ]);

        // Store uploaded image
        $path = $request->file('image')->store('analysis', 'public');

        // Analyze with vision agent
        $response = Atlas::agent('vision')
            ->withImage($path, MediaSource::StoragePath, null, 'public')
            ->withMetadata([
                'user_id' => $request->user()->id,
                'request_id' => Str::uuid(),
            ])
            ->chat($request->input('question'));

        // Store analysis result
        Analysis::create([
            'user_id' => $request->user()->id,
            'image_path' => $path,
            'question' => $request->input('question'),
            'response' => $response->text,
            'tokens_used' => $response->totalTokens(),
        ]);

        return response()->json([
            'analysis' => $response->text,
            'tokens' => $response->totalTokens(),
        ]);
    }
}
```

## Best Practices

### 1. Check Provider Support

Always verify your provider supports the media type:

```php
// Gemini supports all types
$response = Atlas::agent('gemini-vision')
    ->withAudio($audioPath, MediaSource::LocalPath)
    ->chat('Transcribe this');

// OpenAI only supports images
$response = Atlas::agent('openai-vision')
    ->withImage($imagePath, MediaSource::LocalPath)
    ->chat('Describe this');
```

### 2. Include MIME Types for Binary Data

Always specify MIME type for base64 and local files:

```php
->withImage($base64, MediaSource::Base64, 'image/png')
->withDocument($path, MediaSource::LocalPath, 'application/pdf')
```

### 3. Use Storage Paths for Persistent Files

For files in Laravel Storage, use `StoragePath` with the appropriate disk:

```php
->withImage('user-uploads/photo.jpg', MediaSource::StoragePath, null, 's3')
```

### 4. Handle Large Files

For large files, consider:
- Using URLs instead of base64 (smaller request size)
- Storing files and referencing by path
- Implementing size limits in validation

## Next Steps

- [Chat](/capabilities/chat) — Basic chat functionality
- [Multi-Turn Conversations](/guides/multi-turn-conversations) — Managing conversation history
- [Creating Agents](/guides/creating-agents) — Define vision-capable agents
