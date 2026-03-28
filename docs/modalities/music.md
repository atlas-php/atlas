# Music

Generate music from text prompts or structured composition plans.

## Quick Example

```php
use Atlasphp\Atlas\Atlas;

$response = Atlas::music('elevenlabs')
    ->instructions('An upbeat jazz piano track with a walking bass line')
    ->withDuration(30)
    ->asAudio();

$response->store('public');
```

## With Duration

```php
// Short jingle
$response = Atlas::music('elevenlabs')
    ->instructions('A cheerful 8-bit chiptune jingle')
    ->withDuration(10)
    ->asAudio();

// Longer background track
$response = Atlas::music('elevenlabs')
    ->instructions('Ambient lo-fi beats for studying')
    ->withDuration(120)
    ->asAudio();
```

## With Format

```php
$response = Atlas::music('elevenlabs')
    ->instructions('Classical guitar melody')
    ->withDuration(30)
    ->withFormat('wav')
    ->asAudio();
```

## Composition Plans (Provider Options)

For more control, pass a structured composition plan via provider options:

```php
$response = Atlas::music('elevenlabs')
    ->withProviderOptions([
        'composition_plan' => [
            ['text' => 'Gentle piano intro', 'duration_ms' => 5000],
            ['text' => 'Build with drums and bass', 'duration_ms' => 15000],
            ['text' => 'Fade out', 'duration_ms' => 5000],
        ],
        'strict_section_timing' => true,
    ])
    ->asAudio();
```

## Storing Music

```php
$response = Atlas::music('elevenlabs')
    ->instructions('Smooth jazz background')
    ->withDuration(60)
    ->asAudio();

$path = $response->store('public');
$path = $response->storeAs('music/jazz-background.mp3', 'public');
```

::: tip Automatic Storage
When [persistence](/advanced/persistence) is enabled, generated music is automatically stored to disk and tracked as an `Asset` record — no manual `store()` call needed. Access the asset via `$response->asset`. See [Media & Assets](/guides/media-storage) for details.
:::

## Persisted Asset

When [persistence](/advanced/persistence) is enabled, generated music is automatically stored to disk:

```php
$response = Atlas::music('elevenlabs')
    ->instructions('Lo-fi hip hop beat')
    ->withDuration(30)
    ->asAudio();

if ($response->asset) {
    $response->asset->path;       // Storage path
    $response->asset->mime_type;  // "audio/mpeg"
    $response->asset->disk;       // Filesystem disk
}
```

See [Media & Assets](/guides/media-storage) for the complete storage guide.

## Queue Support

```php
Atlas::music('elevenlabs')
    ->instructions('Epic orchestral soundtrack')
    ->withDuration(120)
    ->queue()
    ->asAudio()
    ->then(function ($response) {
        $path = $response->store('public');
        notify($user, "Music ready: {$path}");
    });
```

## Supported Providers

| Provider | Models | Features |
|----------|--------|----------|
| ElevenLabs | Default | Text prompts, composition plans, duration control |

## Builder Reference

| Method | Description |
|--------|-------------|
| `instructions(string)` | Text prompt describing the music |
| `withDuration(int)` | Duration in seconds |
| `withFormat(string)` | Output format (mp3, wav, etc.) |
| `withVariables(array)` | Variables for instruction interpolation |
| `withProviderOptions(array)` | Provider-specific options (composition plans, etc.) |
| `withMiddleware(array)` | Per-request provider middleware |
| `withMeta(array)` | Metadata for middleware/events |
| `queue()` | Dispatch to queue |
| `asAudio()` | Terminal: returns AudioResponse |
