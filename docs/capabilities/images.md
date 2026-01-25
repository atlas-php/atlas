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

$url = $response->images[0]->url;
```

## With Options

```php
$response = Atlas::image()
    ->using('openai', 'dall-e-3')
    ->withPrompt('A photorealistic portrait of a robot')
    ->withSize(1024, 1024)
    ->withProviderMeta('openai', ['quality' => 'hd', 'style' => 'vivid'])
    ->generate();
```

## Saving Images

```php
$response = Atlas::image()
    ->using('openai', 'dall-e-3')
    ->withPrompt('A serene lake at dawn')
    ->generate();

// Save from URL
$imageContent = file_get_contents($response->images[0]->url);
Storage::put('images/lake.png', $imageContent);

// Or using Laravel's HTTP client
Http::sink(storage_path('images/lake.png'))->get($response->images[0]->url);
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
            ->withSize(1024, 1024)
            ->generate();

        // Save to storage
        $filename = 'generated/' . Str::uuid() . '.png';
        $content = file_get_contents($response->images[0]->url);
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
    ->withSize(int $width, int $height)                   // Image dimensions
    ->withProviderMeta(string $provider, array $options)  // Provider-specific options
    ->withMetadata(array $metadata)                       // Pipeline metadata
    ->generate(): ImageResponse;

// Response properties (ImageResponse)
$response->images;              // array of Image objects
$response->images[0]->url;      // URL to generated image
$response->images[0]->base64;   // Base64 encoded image (if requested)
$response->images[0]->revisedPrompt;  // Revised prompt (if modified by provider)

// Common provider options (via withProviderMeta)
// OpenAI DALL-E 3:
->withProviderMeta('openai', [
    'quality' => 'standard',     // 'standard' or 'hd'
    'style' => 'vivid',          // 'vivid' or 'natural'
    'response_format' => 'url',  // 'url' or 'b64_json'
])

// Common sizes
->withSize(1024, 1024)   // Square (DALL-E 3)
->withSize(1792, 1024)   // Landscape (DALL-E 3)
->withSize(1024, 1792)   // Portrait (DALL-E 3)
```

## Next Steps

- [Prism Image Generation](https://prismphp.com/core-concepts/image-generation.html) — Complete image reference
- [Speech](/capabilities/speech) — Text-to-speech and transcription
- [Pipelines](/core-concepts/pipelines) — Add observability to image generation
