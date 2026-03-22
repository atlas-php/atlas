# Video

Generate videos from text prompts and analyze existing videos.

## Quick Example

```php
use Atlasphp\Atlas\Facades\Atlas;

$response = Atlas::video('xai', 'grok-2-video')
    ->instructions('A drone flyover of a tropical beach at sunset')
    ->asVideo();

echo $response->url;  // URL to generated video
```

## Video Generation

```php
$response = Atlas::video('xai', 'grok-2-video')
    ->instructions('A time-lapse of clouds moving over a mountain range')
    ->withDuration(10)
    ->withRatio('16:9')
    ->withFormat('mp4')
    ->asVideo();

$response->url;       // Video URL
$response->duration;  // Video duration in seconds
$response->format;    // Video format
```

### With Media Input (Image-to-Video)

```php
use Atlasphp\Atlas\Input\Image;

$response = Atlas::video('xai', 'grok-2-video')
    ->instructions('Animate this image with gentle camera movement')
    ->withMedia([Image::fromPath('/path/to/photo.jpg')])
    ->asVideo();
```

## Video-to-Text (Understanding)

Analyze or describe an existing video:

```php
use Atlasphp\Atlas\Input\Video;

$response = Atlas::video('xai', 'grok-2-video')
    ->instructions('Describe what happens in this video.')
    ->withMedia([Video::fromUrl('https://example.com/clip.mp4')])
    ->asText();

echo $response->text;
```

### Video Input Sources

```php
Video::fromUrl('https://example.com/video.mp4')
Video::fromPath('/path/to/video.mp4')
Video::fromStorage('videos/clip.mp4')
Video::fromUpload($request->file('video'))
```

## Storing Videos

```php
// Store to disk manually
$path = $response->store('public');
$path = $response->storeAs('videos/generated.mp4', 'public');
```

::: tip Automatic Storage
When [persistence](/advanced/persistence) is enabled, generated video is automatically stored to disk and tracked as an `Asset` record — no manual `store()` call needed. Access the asset via `$response->asset`. See [Media & Assets](/guides/media-storage) for details.
:::

## Supported Providers

| Provider | Models | Capabilities |
|----------|--------|-------------|
| OpenAI | sora | Generation |
| xAI | grok-2-video | Generation, image-to-video |

## VideoResponse

| Property | Type | Description |
|----------|------|-------------|
| `url` | `string` | Generated video URL |
| `duration` | `?int` | Duration in seconds |
| `format` | `?string` | Video format |
| `meta` | `array` | Additional metadata |
| `asset` | `?Asset` | Linked asset (when persistence enabled) |

## Persisted Asset

When [persistence](/advanced/persistence) is enabled, generated video is automatically stored:

```php
$response = Atlas::video('xai', 'grok-2-video')
    ->instructions('A drone flyover of a city')
    ->asVideo();

if ($response->asset) {
    $response->asset->path;       // Storage path
    $response->asset->mime_type;  // "video/mp4"
}
```

See [Media & Assets](/guides/media-storage) for the complete storage guide.

## Queue Support

Video generation can take significant time. Queue it to avoid blocking:

```php
Atlas::video('xai', 'grok-2-video')
    ->instructions('A timelapse of a sunset')
    ->withDuration(15)
    ->queue()
    ->asVideo()
    ->then(function ($response) {
        logger()->info('Video ready', ['url' => $response->url]);
    })
    ->catch(function ($e) {
        logger()->error('Video failed', ['error' => $e->getMessage()]);
    });
```

## Builder Reference

| Method | Description |
|--------|-------------|
| `instructions(string)` | Video generation prompt |
| `withMedia(array)` | Input media for video-from-image or video-to-text |
| `withDuration(int)` | Target duration in seconds |
| `withRatio(string)` | Aspect ratio (e.g. '16:9', '1:1') |
| `withFormat(string)` | Output format (mp4, webm) |
| `withProviderOptions(array)` | Provider-specific options |
| `withVariables(array)` | Variables for instruction interpolation |
| `withMeta(array)` | Metadata for middleware/events |
| `withMiddleware(array)` | Per-request provider middleware |
| `queue()` | Dispatch to queue |
