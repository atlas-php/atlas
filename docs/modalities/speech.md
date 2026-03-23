# Speech

Convert text to speech (TTS) and transcribe audio to text (STT).

## Text-to-Speech

```php
use Atlasphp\Atlas\Facades\Atlas;

$response = Atlas::speech('openai', 'tts-1')
    ->instructions('Hello, welcome to Atlas!')
    ->withVoice('alloy')
    ->asAudio();

// $response->data contains the audio binary
$response->store('public');  // Store to disk
```

### Voice Options

```php
$response = Atlas::speech('openai', 'tts-1-hd')
    ->instructions('This is high quality speech.')
    ->withVoice('nova')
    ->withSpeed(1.2)
    ->withFormat('mp3')
    ->asAudio();
```

### With ElevenLabs

```php
$response = Atlas::speech('elevenlabs', 'eleven_multilingual_v2')
    ->instructions('Welcome to the future of AI.')
    ->withVoice('Rachel')
    ->withLanguage('en')
    ->asAudio();
```

### Speed & Language

```php
// Adjust playback speed (0.25 to 4.0)
$response = Atlas::speech('openai', 'tts-1')
    ->instructions('Slow and clear narration.')
    ->withVoice('onyx')
    ->withSpeed(0.8)
    ->asAudio();

// Specify language for multilingual models
$response = Atlas::speech('elevenlabs', 'eleven_multilingual_v2')
    ->instructions('Bonjour, bienvenue sur Atlas.')
    ->withVoice('Rachel')
    ->withLanguage('fr')
    ->asAudio();
```

## Speech-to-Text (Transcription)

```php
use Atlasphp\Atlas\Input\Audio;

$response = Atlas::speech('openai', 'whisper-1')
    ->withMedia([Audio::fromPath('/path/to/recording.mp3')])
    ->asText();

echo $response->text;  // "Hello, this is the transcribed text..."
```

### Audio Input Sources

```php
Audio::fromUrl('https://example.com/audio.mp3')
Audio::fromPath('/path/to/file.wav')
Audio::fromStorage('recordings/meeting.mp3')
Audio::fromUpload($request->file('audio'))
```

## Storing Audio

```php
$response = Atlas::speech('openai', 'tts-1')
    ->instructions('Hello world')
    ->withVoice('alloy')
    ->asAudio();

// Store to disk manually
$path = $response->store('public');
$path = $response->storeAs('audio/greeting.mp3', 'public');
```

::: tip Automatic Storage
When [persistence](/advanced/persistence) is enabled, generated audio is automatically stored to disk and tracked as an `Asset` record — no manual `store()` call needed. Access the asset via `$response->asset`. See [Media & Assets](/guides/media-storage) for details.
:::

## Persisted Asset

When [persistence](/advanced/persistence) is enabled, generated audio is automatically stored to disk:

```php
$response = Atlas::speech('openai', 'tts-1')
    ->instructions('Welcome to Atlas')
    ->withVoice('alloy')
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
Atlas::speech('openai', 'tts-1')
    ->instructions('Generate a long audiobook chapter')
    ->withVoice('nova')
    ->queue()
    ->asAudio()
    ->then(function ($response) {
        $path = $response->store('public');
        notify($user, "Audio ready: {$path}");
    });
```

```php
// Transcription in background
Atlas::speech('openai', 'whisper-1')
    ->withMedia([Audio::fromStorage('recordings/meeting.mp3')])
    ->queue()
    ->asText()
    ->then(fn ($response) => Transcript::create(['text' => $response->text]));
```

## Supported Providers

| Provider | TTS | STT | Features |
|----------|-----|-----|----------|
| OpenAI | tts-1, tts-1-hd | whisper-1 | Voices, speed, format |
| ElevenLabs | eleven_multilingual_v2 | Yes | Voices, cloning, languages |
| xAI | grok-2-audio | — | TTS |

## Builder Reference

| Method | Description |
|--------|-------------|
| `instructions(string)` | Text to convert to speech |
| `withMedia(array)` | Audio files for transcription |
| `withVoice(string)` | Voice name or ID |
| `withVoiceClone(array)` | Voice cloning configuration |
| `withSpeed(float)` | Playback speed multiplier |
| `withLanguage(string)` | Language code |
| `withFormat(string)` | Output format (mp3, wav, ogg, etc.) |
| `withVariables(array)` | Variables for instruction interpolation |
| `withProviderOptions(array)` | Provider-specific options |
| `withMiddleware(array)` | Per-request provider middleware |
| `withMeta(array)` | Metadata for middleware/events |
| `queue()` | Dispatch to queue |
| `asAudio()` | Terminal: returns AudioResponse |
| `asText()` | Terminal: returns transcription |

## API Reference for SpeechRequest

| Property | Type | Description |
|----------|------|-------------|
| `data` | `string` | Raw audio binary data |
| `format` | `?string` | Audio format (mp3, wav, etc.) |
| `text` | `?string` | Transcribed text (STT only) |
| `meta` | `array` | Additional metadata |
| `asset` | `?Asset` | Linked asset (when persistence enabled) |
