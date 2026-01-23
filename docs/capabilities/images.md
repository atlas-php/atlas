# Image Generation

Generate images using DALL-E and other AI image providers.

## Basic Generation

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

$result = Atlas::image()->generate('A sunset over mountains');

echo $result['url'];           // URL to generated image
echo $result['revised_prompt']; // AI-revised prompt (if applicable)
```

## With Provider and Model

```php
$result = Atlas::image('openai', 'dall-e-3')
    ->generate('A futuristic cityscape');
```

## Fluent Configuration

```php
$result = Atlas::image()
    ->using('openai')
    ->model('dall-e-3')
    ->size('1024x1024')
    ->quality('hd')
    ->generate('A photorealistic portrait of a robot');
```

## Provider-Specific Options

```php
$result = Atlas::image('openai', 'dall-e-3')
    ->size('1024x1024')
    ->quality('hd')
    ->withProviderOptions(['style' => 'vivid'])  // OpenAI: 'vivid' or 'natural'
    ->generate('A vibrant abstract painting');
```

## Response Format

```php
$result = Atlas::image()->generate('A mountain landscape');

// Result structure
[
    'url' => 'https://...',      // Image URL
    'base64' => null,            // Base64 data (if requested)
    'revised_prompt' => '...',   // Provider's revised prompt
]
```

## Configuration

Configure defaults in `config/atlas.php`:

```php
'image' => [
    'provider' => 'openai',
    'model' => 'dall-e-3',
],
```

## Available Options

### Size

```php
->size('1024x1024')  // Square
->size('1792x1024')  // Landscape
->size('1024x1792')  // Portrait
```

**DALL-E 3 sizes:** `1024x1024`, `1792x1024`, `1024x1792`
**DALL-E 2 sizes:** `256x256`, `512x512`, `1024x1024`

### Quality

```php
->quality('standard')  // Default, faster
->quality('hd')        // Higher detail, slower
```

### Style (OpenAI)

```php
->withProviderOptions(['style' => 'vivid'])   // Bold colors, dramatic
->withProviderOptions(['style' => 'natural']) // Subtle, realistic
```

## PendingImageRequest Methods

| Method | Description |
|--------|-------------|
| `using(string $provider)` | Set provider |
| `model(string $model)` | Set model |
| `size(string $size)` | Set image dimensions |
| `quality(string $quality)` | Set quality level |
| `withProviderOptions(array $options)` | Set provider-specific options |
| `withMetadata(array $metadata)` | Set metadata for pipelines |
| `withRetry($times, $delay, $when, $throw)` | Configure retry |
| `generate(string $prompt, array $options = [])` | Generate image |

## Saving Images

```php
$result = Atlas::image()
    ->size('1024x1024')
    ->generate('A serene lake at dawn');

// Save from URL
$imageContent = file_get_contents($result['url']);
Storage::put('images/lake.png', $imageContent);

// Or using Laravel's HTTP client
Http::sink(storage_path('images/lake.png'))->get($result['url']);
```

## Use Cases

### Product Mockups

```php
$result = Atlas::image('openai', 'dall-e-3')
    ->size('1024x1024')
    ->quality('hd')
    ->withProviderOptions(['style' => 'natural'])
    ->generate('A minimalist white coffee mug on a wooden desk, professional product photography');
```

### Creative Assets

```php
$result = Atlas::image('openai', 'dall-e-3')
    ->size('1792x1024')
    ->quality('hd')
    ->withProviderOptions(['style' => 'vivid'])
    ->generate('Abstract digital art representing innovation and technology, blue and purple gradients');
```

### Illustrations

```php
$result = Atlas::image('openai', 'dall-e-3')
    ->size('1024x1024')
    ->generate('A friendly cartoon robot waving, simple flat design style');
```

## Complete Example

```php
class ImageGeneratorController extends Controller
{
    public function generate(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string|max:1000',
            'size' => 'in:1024x1024,1792x1024,1024x1792',
            'quality' => 'in:standard,hd',
        ]);

        $result = Atlas::image('openai', 'dall-e-3')
            ->size($request->input('size', '1024x1024'))
            ->quality($request->input('quality', 'standard'))
            ->generate($request->input('prompt'));

        // Save to storage
        $filename = 'generated/' . Str::uuid() . '.png';
        $content = file_get_contents($result['url']);
        Storage::put($filename, $content);

        return response()->json([
            'url' => Storage::url($filename),
            'revised_prompt' => $result['revised_prompt'],
        ]);
    }
}
```

## Retry & Resilience

Enable automatic retries for image generation:

```php
// Simple retry: 3 attempts, 1 second delay
$result = Atlas::image()
    ->withRetry(3, 1000)
    ->generate('A sunset over mountains');

// Exponential backoff
$result = Atlas::image()
    ->withRetry(3, fn($attempt) => (2 ** $attempt) * 100)
    ->size('1024x1024')
    ->generate('A sunset');

// Only retry on rate limits
$result = Atlas::image('openai', 'dall-e-3')
    ->withRetry(3, 1000, fn($e) => $e->getCode() === 429)
    ->generate('A landscape');

// With metadata for pipeline observability
$result = Atlas::image()
    ->withMetadata(['user_id' => 123])
    ->withRetry(3, 1000)
    ->generate('A landscape');
```

## Error Handling

```php
try {
    $result = Atlas::image()->generate('...');
} catch (ProviderException $e) {
    // Handle provider errors (rate limits, invalid prompts, etc.)
    Log::error('Image generation failed', [
        'error' => $e->getMessage(),
    ]);
}
```

## Best Practices

### 1. Be Specific in Prompts

```php
// Good - specific and detailed
'A photorealistic image of a golden retriever puppy sitting in a sunlit garden,
shallow depth of field, professional pet photography'

// Less effective - vague
'A dog picture'
```

### 2. Include Style Guidance

```php
// Specify the desired style
'Digital illustration in the style of Studio Ghibli,
a small cottage in a forest clearing, soft lighting'
```

### 3. Handle Costs

Image generation can be expensive. Consider:
- Rate limiting requests
- Caching generated images
- Using lower quality for previews

## Next Steps

- [Configuration](/getting-started/configuration) — Configure image providers
- [Speech](/capabilities/speech) — Text-to-speech and transcription
