# Sound Effects

Generate sound effects from text descriptions.

## Quick Example

```php
use Atlasphp\Atlas\Facades\Atlas;

$response = Atlas::sfx('elevenlabs')
    ->instructions('Thunder with heavy rain')
    ->withDuration(5)
    ->asAudio();

$response->store('public');
```

## With Duration

```php
// Short impact sound
$response = Atlas::sfx('elevenlabs')
    ->instructions('Glass shattering on a hard floor')
    ->withDuration(2)
    ->asAudio();

// Longer ambient effect
$response = Atlas::sfx('elevenlabs')
    ->instructions('Forest ambience with birds and a gentle stream')
    ->withDuration(30)
    ->asAudio();
```

## Looping (Provider Options)

Create seamless looping sound effects for games or ambient backgrounds:

```php
$response = Atlas::sfx('elevenlabs')
    ->instructions('Footsteps on gravel, slow pace')
    ->withDuration(3)
    ->withProviderOptions([
        'loop' => true,              // Seamless looping
        'prompt_influence' => 0.8,   // How closely to follow the prompt
    ])
    ->asAudio();
```

## Storing Sound Effects

```php
$response = Atlas::sfx('elevenlabs')
    ->instructions('Laser beam firing')
    ->withDuration(1)
    ->asAudio();

$path = $response->store('public');
$path = $response->storeAs('sfx/laser-beam.mp3', 'public');
```

::: tip Automatic Storage
When [persistence](/advanced/persistence) is enabled, generated sound effects are automatically stored to disk and tracked as an `Asset` record — no manual `store()` call needed. Access the asset via `$response->asset`. See [Media & Assets](/guides/media-storage) for details.
:::

## Persisted Asset

When [persistence](/advanced/persistence) is enabled, generated sound effects are automatically stored to disk:

```php
$response = Atlas::sfx('elevenlabs')
    ->instructions('Door creaking open')
    ->withDuration(3)
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
Atlas::sfx('elevenlabs')
    ->instructions('Explosion with debris falling')
    ->withDuration(5)
    ->queue()
    ->asAudio()
    ->then(function ($response) {
        $path = $response->store('public');
        notify($user, "Sound effect ready: {$path}");
    });
```

## Supported Providers

| Provider | Models | Features |
|----------|--------|----------|
| ElevenLabs | eleven_text_to_sound_v2 | Duration, looping, prompt influence |

## Builder Reference

| Method | Description |
|--------|-------------|
| `instructions(string)` | Text description of the sound effect |
| `withDuration(int)` | Duration in seconds |
| `withFormat(string)` | Output format (mp3, wav, etc.) |
| `withVariables(array)` | Variables for instruction interpolation |
| `withProviderOptions(array)` | Provider-specific options (loop, prompt_influence, etc.) |
| `withMiddleware(array)` | Per-request provider middleware |
| `withMeta(array)` | Metadata for middleware/events |
| `queue()` | Dispatch to queue |
| `asAudio()` | Terminal: returns AudioResponse |
