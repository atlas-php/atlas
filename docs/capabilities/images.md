# Images

Generate images using AI providers like OpenAI and Gemini.

::: tip Prism Reference
Atlas image generation wraps Prism's image API. For provider-specific options and advanced usage, see [Prism Image Generation](https://prismphp.com/core-concepts/image-generation.html).
:::

## Supported Providers

Currently **OpenAI** and **Gemini** support image generation through Prism. **xAI** also offers image generation (`grok-imagine-image`) but Prism support is not yet available — check [Prism releases](https://github.com/prism-php/prism/releases) for updates.

## Basic Usage

```php
use Atlasphp\Atlas\Atlas;
use Prism\Prism\Enums\Provider;

$response = Atlas::image()
    ->using(Provider::OpenAI, 'gpt-image-1')
    ->withPrompt('A sunset over mountains')
    ->generate();

$image = $response->firstImage();
```

The `using()` method accepts either the `Prism\Prism\Enums\Provider` enum or a plain string:

```php
->using(Provider::OpenAI, 'gpt-image-1')  // enum
->using('openai', 'gpt-image-1')          // string
```

## Accessing the Image

Different providers and models return images in different formats. Use `rawContent()` to get the image bytes regardless of how the provider returned them:

```php
$image = $response->firstImage();

// Get the raw image bytes — works with any provider/model
$content = $image->rawContent();
```

Under the hood, `rawContent()` handles the conversion automatically:
- If the provider returned **base64**, it decodes it
- If the provider returned a **URL**, it fetches the content via HTTP

You can also access the underlying data directly:

```php
$image->base64;    // Base64 string or null
$image->url;       // URL string or null
$image->mimeType;  // MIME type or null (Gemini sets this, OpenAI does not)
```

### What Each Model Returns

<div class="full-width-table">

| Provider | Model | Returns | Notes |
|----------|-------|---------|-------|
| OpenAI | `dall-e-3` | URL by default | Set `response_format` to `'b64_json'` for base64 |
| OpenAI | `dall-e-2` | URL by default | Set `response_format` to `'b64_json'` for base64 |
| OpenAI | `gpt-image-1` | Always base64 | `response_format` is ignored |
| Gemini | All image models | Always base64 | Also returns `mimeType` |

</div>

## Saving Images

```php
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

$response = Atlas::image()
    ->using(Provider::OpenAI, 'gpt-image-1')
    ->withPrompt('A serene lake at dawn')
    ->generate();

$image = $response->firstImage();
$filename = 'generated/' . Str::uuid() . '.png';
Storage::put($filename, $image->rawContent());
```

## Provider Options

Pass provider-specific options via `withProviderOptions()`.

### OpenAI — DALL-E 3

```php
$response = Atlas::image()
    ->using(Provider::OpenAI, 'dall-e-3')
    ->withPrompt('A photorealistic portrait')
    ->withProviderOptions([
        'size' => '1024x1024',            // '1024x1024', '1792x1024', '1024x1792'
        'quality' => 'hd',                // 'standard' or 'hd'
        'style' => 'vivid',              // 'vivid' or 'natural'
        'response_format' => 'b64_json', // 'url' (default) or 'b64_json'
    ])
    ->generate();
```

### OpenAI — GPT-Image-1

GPT-Image-1 always returns base64 — `response_format` has no effect.

```php
$response = Atlas::image()
    ->using(Provider::OpenAI, 'gpt-image-1')
    ->withPrompt('A minimalist logo')
    ->withProviderOptions([
        'size' => '1024x1024',               // '1024x1024', '1536x1024', '1024x1536'
        'quality' => 'high',                 // 'auto', 'high', 'medium', 'low'
        'background' => 'transparent',       // 'auto', 'transparent', 'opaque'
        'output_format' => 'png',            // 'png', 'jpeg', 'webp'
        'output_compression' => 80,          // 0-100 (jpeg/webp only)
    ])
    ->generate();
```

### Gemini

Gemini always returns base64 with mimeType included.

```php
$response = Atlas::image()
    ->using(Provider::Gemini, 'gemini-2.0-flash-preview-image-generation')
    ->withPrompt('A watercolor landscape')
    ->generate();

$image = $response->firstImage();
$image->mimeType; // e.g., 'image/png'
```

## Revised Prompts

OpenAI may modify your prompt for safety or quality. The revised prompt is available on the response:

```php
$image = $response->firstImage();

if ($image->hasRevisedPrompt()) {
    echo $image->revisedPrompt; // What the model actually used
}
```

## Multiple Images

Some providers support generating multiple images in a single request via the `n` option:

```php
$response = Atlas::image()
    ->using(Provider::OpenAI, 'dall-e-3')
    ->withPrompt('A watercolor painting of flowers')
    ->withProviderOptions(['n' => 2])
    ->generate();

// Access all images
foreach ($response->images as $image) {
    Storage::put('image-' . uniqid() . '.png', $image->rawContent());
}

// Helpers
$response->imageCount();   // 2
$response->hasImages();    // true
$response->firstImage();   // First image (convenience for single-image use)
```

## Example: Controller

```php
use Atlasphp\Atlas\Atlas;
use Prism\Prism\Enums\Provider;

class ImageController extends Controller
{
    public function generate(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string|max:1000',
        ]);

        $response = Atlas::image()
            ->using(Provider::OpenAI, 'gpt-image-1')
            ->withPrompt($request->input('prompt'))
            ->withProviderOptions(['size' => '1024x1024'])
            ->generate();

        $image = $response->firstImage();
        $filename = 'generated/' . Str::uuid() . '.png';
        Storage::put($filename, $image->rawContent());

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

## Next Steps

- [Prism Image Generation](https://prismphp.com/core-concepts/image-generation.html) — Complete image reference
- [Audio](/capabilities/audio) — Text-to-speech and transcription
- [Pipelines](/core-concepts/pipelines) — Add observability to image generation
