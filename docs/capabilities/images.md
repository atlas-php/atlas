# Images

Generate images and extract text descriptions from existing images.

## Quick Example

```php
use Atlasphp\Atlas\Facades\Atlas;

$response = Atlas::image('openai', 'dall-e-3')
    ->instructions('A serene mountain landscape at sunset')
    ->asImage();

echo $response->url;  // URL to generated image
```

## Image Generation

```php
$response = Atlas::image('openai', 'dall-e-3')
    ->instructions('A futuristic city skyline')
    ->withSize('1024x1024')
    ->withQuality('hd')
    ->withFormat('png')
    ->asImage();

$response->url;            // Image URL (string or array for multiple)
$response->revisedPrompt;  // The prompt as revised by the provider
$response->base64;         // Base64 data (if requested)
$response->format;         // Image format
```

### Multiple Images

```php
$response = Atlas::image('openai', 'dall-e-3')
    ->instructions('A watercolor painting of flowers')
    ->withCount(3)
    ->asImage();

// $response->url is an array when count > 1
```

### With Variables

```php
$response = Atlas::image('openai', 'dall-e-3')
    ->instructions('A {STYLE} painting of {SUBJECT}')
    ->withVariables(['STYLE' => 'impressionist', 'SUBJECT' => 'a garden'])
    ->asImage();
```

## Image-to-Text

Describe or analyze an existing image:

```php
use Atlasphp\Atlas\Input\Image;

$response = Atlas::image('openai', 'gpt-4o')
    ->instructions('Describe this image in detail.')
    ->withMedia([Image::fromUrl('https://example.com/photo.jpg')])
    ->asText();

echo $response->text;  // "This image shows..."
```

### From Different Sources

```php
// From URL
Image::fromUrl('https://example.com/image.png')

// From local file
Image::fromPath('/path/to/image.jpg')

// From Laravel storage
Image::fromStorage('images/photo.jpg', 'public')

// From base64
Image::fromBase64($data, 'image/png')

// From upload
Image::fromUpload($request->file('image'))
```

## Storing Images

```php
$response = Atlas::image('openai', 'dall-e-3')
    ->instructions('A cute robot')
    ->asImage();

// Store to disk
$path = $response->store('public');        // Returns storage path
$path = $response->storeAs('images/robot.png', 'public');
```

## Supported Providers

| Provider | Models | Capabilities |
|----------|--------|-------------|
| OpenAI | dall-e-3, dall-e-2, gpt-image-1 | Generation, sizes, quality, format |
| Google | gemini-2.0-flash | Generation |
| xAI | grok-2-image | Generation |

## ImageResponse

| Property | Type | Description |
|----------|------|-------------|
| `url` | `string\|array` | Generated image URL(s) |
| `revisedPrompt` | `?string` | Provider-revised prompt |
| `base64` | `?string` | Base64 image data |
| `format` | `?string` | Image format |
| `meta` | `array` | Additional metadata |
| `asset` | `?Asset` | Linked asset (when persistence enabled) |

## Builder Reference

| Method | Description |
|--------|-------------|
| `instructions(string)` | Image generation prompt |
| `withMedia(array)` | Input images for image-to-text |
| `withSize(string)` | Image dimensions (e.g. '1024x1024') |
| `withQuality(string)` | Quality level (e.g. 'hd', 'standard') |
| `withFormat(string)` | Output format (e.g. 'png', 'webp') |
| `withCount(int)` | Number of images to generate |
| `withProviderOptions(array)` | Provider-specific options |
| `withVariables(array)` | Variables for instruction interpolation |
| `withMeta(array)` | Metadata for middleware/events |
| `withMiddleware(array)` | Per-request provider middleware |
| `queue()` | Dispatch to queue |
