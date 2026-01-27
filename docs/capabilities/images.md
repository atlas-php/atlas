# Images

Generate images using DALL-E and other AI image providers.

::: tip Prism Reference
Atlas image generation wraps Prism's image API. For detailed documentation including all provider options, see [Prism Image Generation](https://prismphp.com/core-concepts/image-generation.html).
:::

## Basic Usage

```php
use Atlasphp\Atlas\Atlas;

$response = Atlas::image()
    ->using('openai', 'dall-e-3')
    ->withPrompt('A sunset over mountains')
    ->generate();

$image = $response->firstImage();
$url = $image->url;
```

## With Options

```php
$response = Atlas::image()
    ->using('openai', 'dall-e-3')
    ->withPrompt('A photorealistic portrait of a robot')
    ->withProviderOptions([
        'size' => '1024x1024',
        'quality' => 'hd',
        'style' => 'vivid',
    ])
    ->generate();
```

## Saving Images

```php
$response = Atlas::image()
    ->using('openai', 'dall-e-3')
    ->withPrompt('A serene lake at dawn')
    ->generate();

$image = $response->firstImage();

// Save from URL
$imageContent = file_get_contents($image->url);
Storage::put('images/lake.png', $imageContent);

// Or using Laravel's HTTP client
Http::sink(storage_path('images/lake.png'))->get($image->url);
```

## Example: Complete Image Generation

```php
use Atlasphp\Atlas\Atlas;

class ImageController extends Controller
{
    public function generate(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string|max:1000',
        ]);

        $response = Atlas::image()
            ->using('openai', 'dall-e-3')
            ->withPrompt($request->input('prompt'))
            ->withProviderOptions(['size' => '1024x1024'])
            ->generate();

        // Save to storage
        $image = $response->firstImage();
        $filename = 'generated/' . Str::uuid() . '.png';
        $content = file_get_contents($image->url);
        Storage::put($filename, $content);

        return response()->json([
            'url' => Storage::url($filename),
        ]);
    }
}
```

## Pipeline Hooks

Image generation supports pipeline middleware for observability:

<div class="full-width-table">

| Pipeline | Trigger |
|----------|---------|
| `image.before_generate` | Before generating image |
| `image.after_generate` | After generating image |

</div>

```php
use Atlasphp\Atlas\Contracts\PipelineContract;

class LogImageGeneration implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        $result = $next($data);

        Log::info('Image generated', [
            'user_id' => $data['metadata']['user_id'] ?? null,
        ]);

        return $result;
    }
}

$registry->register('image.after_generate', LogImageGeneration::class);
```

## API Reference

```php
// Image generation fluent API
Atlas::image()
    ->using(string $provider, string $model)              // Set provider and model
    ->withPrompt(string $prompt)                          // Image description
    ->withProviderOptions(array $options)                 // Provider-specific options
    ->withMetadata(array $metadata)                       // Pipeline metadata
    ->generate(): ImageResponse;

// Response properties (ImageResponse)
$response->images;                    // array of GeneratedImage objects
$response->firstImage();              // First image (or null)
$response->firstImage()->url;         // URL to generated image
$response->firstImage()->base64;      // Base64 encoded image (if requested)
$response->firstImage()->revisedPrompt;  // Revised prompt (if modified by provider)

// Common provider options (via withProviderOptions)
// OpenAI DALL-E 3:
->withProviderOptions([
    'size' => '1024x1024',       // '1024x1024', '1792x1024', '1024x1792'
    'quality' => 'standard',     // 'standard' or 'hd'
    'style' => 'vivid',          // 'vivid' or 'natural'
    'response_format' => 'url',  // 'url' or 'b64_json'
])

// Common sizes (passed via providerOptions)
'size' => '1024x1024'   // Square (DALL-E 3)
'size' => '1792x1024'   // Landscape (DALL-E 3)
'size' => '1024x1792'   // Portrait (DALL-E 3)
```

## Next Steps

- [Prism Image Generation](https://prismphp.com/core-concepts/image-generation.html) — Complete image reference
- [Audio](/capabilities/audio) — Text-to-speech and transcription
- [Pipelines](/core-concepts/pipelines) — Add observability to image generation
