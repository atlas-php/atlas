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

## Complete Example

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

## Next Steps

- [Prism Image Generation](https://prismphp.com/core-concepts/image-generation.html) — Complete image reference
- [Speech](/capabilities/speech) — Text-to-speech and transcription
- [Pipelines](/core-concepts/pipelines) — Add observability to image generation
