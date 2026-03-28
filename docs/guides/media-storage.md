# Media & Assets

Atlas generates media files — images, audio, and video — that you can store, serve, and attach to conversations.

## Accessing Generated Media

Every media response (image, audio, video) provides methods to access and store the content.

### Images

```php
$response = Atlas::image('openai', 'dall-e-3')
    ->instructions('A mountain sunset')
    ->asImage();

$response->url;            // URL to the generated image
$response->revisedPrompt;  // Provider-revised prompt (if applicable)
$response->base64;         // Base64 data (if requested)
$response->format;         // Image format (png, webp, etc.)
```

### Audio

```php
$response = Atlas::audio('openai', 'tts-1')
    ->instructions('Hello, welcome to Atlas!')
    ->withVoice('alloy')
    ->asAudio();

$response->data;    // Base64-encoded audio binary
$response->format;  // Audio format (mp3, wav, etc.)
```

### Video

```php
$response = Atlas::video('xai', 'grok-2-video')
    ->instructions('A timelapse of clouds')
    ->asVideo();

$response->url;       // URL to the generated video
$response->duration;  // Duration in seconds
$response->format;    // Video format (mp4, webm)
```

## Storing Media to Disk

All media responses include storage methods via the `StoresMedia` trait. Files are stored using Laravel's Storage facade.

### Basic Storage

```php
// Store with auto-generated filename
$path = $response->store();
// "atlas/a1b2c3d4-e5f6-7890-abcd-ef1234567890.png"

// Store on a specific disk
$path = $response->store('s3');
```

### Custom Path

```php
// Store at a specific path
$path = $response->storeAs('images/hero.png');

// Store at a specific path on a specific disk
$path = $response->storeAs('images/hero.png', 's3');
```

### Public Storage

```php
// Store with public visibility
$path = $response->storePublicly();
$path = $response->storePublicly('s3');

// Store publicly at a specific path
$path = $response->storePubliclyAs('images/public/hero.png');
$path = $response->storePubliclyAs('images/public/hero.png', 's3');
```

### Raw Content

```php
// Get raw binary content
$binary = $response->contents();

// Get base64-encoded content
$base64 = $response->toBase64();
```

## Storage Configuration

Configure default storage settings in `config/atlas.php`:

```php
'storage' => [
    'disk' => env('ATLAS_STORAGE_DISK'),       // null = default filesystem disk
    'prefix' => 'atlas',                        // Path prefix for auto-generated filenames
    'visibility' => 'private',                  // Default visibility (private or public)
],
```

Auto-generated filenames follow the pattern: `{prefix}/{uuid}.{extension}`

## Auto-Storage with Persistence

When [persistence](/advanced/persistence) is enabled, Atlas **automatically stores** media files from direct modality calls (image, audio, video). No manual `store()` call needed.

### How It Works

1. You call `Atlas::image()->asImage()` or similar
2. `TrackProviderCall` middleware detects a file-producing response
3. The file is stored on your configured disk
4. An `Asset` record is created in the database with content hash, MIME type, size, and path
5. The `$response->asset` property is populated with the Asset model

```php
$response = Atlas::image('openai', 'dall-e-3')
    ->instructions('A cute robot')
    ->asImage();

// When persistence is enabled:
$response->asset->id;           // Asset record ID
$response->asset->path;         // Storage path
$response->asset->disk;         // Storage disk
$response->asset->mime_type;    // "image/png"
$response->asset->size_bytes;   // File size
```

### Disabling Auto-Storage

```env
ATLAS_AUTO_STORE_ASSETS=false
```

When disabled, you can still store manually with `$response->store()`.

## Tool-Generated Assets

When a **tool** generates files during an agent execution (CSV reports, PDFs, custom files), use `ToolAssets` to store them as tracked assets:

```php
use Atlasphp\Atlas\Persistence\ToolAssets;

class GenerateReportTool extends Tool
{
    public function name(): string { return 'generate_report'; }
    public function description(): string { return 'Generate a CSV sales report'; }

    public function handle(array $args, array $context): mixed
    {
        $csv = $this->buildCsvReport($args['month']);

        $asset = ToolAssets::store($csv, [
            'type' => 'document',
            'mime_type' => 'text/csv',
            'description' => "Sales report for {$args['month']}",
        ]);

        return "Report generated: {$asset->path}";
    }
}
```

### ToolAssets API

| Method | Returns | Description |
|--------|---------|-------------|
| `ToolAssets::store($content, $data)` | `Asset` | Store raw content as a tracked asset |
| `ToolAssets::lastStored()` | `?Asset` | Get the last asset from an Atlas media call inside a tool |

The `store()` method automatically:
- Stores the file on the configured disk
- Creates an Asset record with content hash
- Links the asset to the current execution and tool call
- Adds tool metadata (tool name, tool call ID)

### Accessing Atlas Media Inside Tools

When a tool calls an Atlas modality (e.g. `Atlas::image()` inside a tool), the generated asset is automatically tracked. Use `ToolAssets::lastStored()` to access it:

```php
class CreateImageTool extends Tool
{
    public function handle(array $args, array $context): mixed
    {
        $response = Atlas::image('openai', 'dall-e-3')
            ->instructions($args['prompt'])
            ->asImage();

        // The image was auto-stored by TrackProviderCall
        $asset = ToolAssets::lastStored();

        return "Image created: {$asset->path}";
    }
}
```

## Message Attachments

When persistence is enabled and an agent tool generates assets, those assets are **automatically attached** to the assistant message in the conversation.

### How It Works

1. Agent executes with tools
2. A tool generates an image/file (via Atlas media call or `ToolAssets::store()`)
3. `PersistConversation` middleware stores the assistant response as a message
4. Tool-generated assets are linked to the message via `MessageAsset` records

### Querying Attachments

```php
$message = Message::find($messageId);

// Get all attachments
foreach ($message->assets as $attachment) {
    $asset = $attachment->asset;

    echo $asset->type;       // "image", "audio", "document", etc.
    echo $asset->mime_type;  // "image/png"
    echo $asset->path;       // Storage path
    echo $asset->disk;       // Storage disk

    // Get a URL for the asset
    $url = Storage::disk($asset->disk)->url($asset->path);
}
```

### Attachment Metadata

Each attachment carries metadata about which tool produced it:

```php
$attachment->metadata;
// [
//     'tool_call_id' => 'call_abc123',
//     'tool_name' => 'generate_image',
// ]
```

## Storage Methods Reference

Available on `ImageResponse`, `AudioResponse`, and `VideoResponse`:

| Method | Returns | Description |
|--------|---------|-------------|
| `store(?string $disk)` | `string` | Store with auto-generated filename, returns path |
| `storeAs(string $path, ?string $disk)` | `string` | Store at specific path, returns path |
| `storePublicly(?string $disk)` | `string` | Store with public visibility |
| `storePubliclyAs(string $path, ?string $disk)` | `string` | Store publicly at specific path |
| `contents()` | `string` | Get raw binary content |
| `toBase64()` | `string` | Get base64-encoded content |

Also available on input classes (`Image`, `Audio`, `Video`, `Document`) for storing uploaded or referenced media.
