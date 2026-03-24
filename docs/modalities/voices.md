# Voices

List available voices from audio providers. Useful for building voice selectors and previewing options before generating speech.

## List Voices

```php
use Atlasphp\Atlas\Facades\Atlas;

$voices = Atlas::provider('openai')->voices();

foreach ($voices as $voice) {
    echo "{$voice->id}: {$voice->name}\n";
}
```

## Using Voices

Pass a voice ID to any audio generation request:

```php
$response = Atlas::audio('openai', 'tts-1')
    ->instructions('Hello, welcome to Atlas!')
    ->withVoice('nova')
    ->asAudio();
```

### Building a Voice Selector

```php
// In a controller
public function voices(string $provider)
{
    $voices = Atlas::provider($provider)->voices();

    return response()->json($voices);
}

// In a request
$response = Atlas::audio($request->provider, $request->model)
    ->instructions($request->text)
    ->withVoice($request->voice)
    ->asAudio();
```

## ElevenLabs Voices

ElevenLabs provides a large library of voices including community-created options:

```php
$voices = Atlas::provider('elevenlabs')->voices();

foreach ($voices as $voice) {
    echo "{$voice->id}: {$voice->name}\n";
    // "21m00Tcm4TlvDq8ikWAM: Rachel"
    // "AZnzlk1XvdvUeBnXmlld: Domi"
    // ...
}
```

Use the voice ID from the listing:

```php
$response = Atlas::audio('elevenlabs', 'eleven_multilingual_v2')
    ->instructions('Welcome to Atlas.')
    ->withVoice('21m00Tcm4TlvDq8ikWAM')
    ->asAudio();
```

## Caching

Voice listings are cached by default to minimize API calls:

```env
ATLAS_CACHE_VOICES_TTL=3600   # 1 hour (default)
```

Set TTL to `0` to disable caching.

## Supported Providers

| Provider | Voices | Source |
|----------|--------|--------|
| OpenAI | alloy, ash, ballad, coral, echo, fable, onyx, nova, sage, shimmer | Hardcoded |
| xAI | Fetched from API (`/v1/tts/voices`) | Live API |
| ElevenLabs | Large voice library + community voices | Live API |

## API Reference

| Method | Returns | Description |
|--------|---------|-------------|
| `voices()` | `VoiceList` | List available voices from the provider |
