# Audio

Convert text to speech (TTS), transcribe audio to text (STT), generate sound effects, and create music.

## Text-to-Speech

```php
use Atlasphp\Atlas\Facades\Atlas;

$response = Atlas::audio('openai', 'tts-1')
    ->instructions('Hello, welcome to Atlas!')
    ->withVoice('alloy')
    ->asAudio();

// $response->data contains the audio binary
$response->store('public');  // Store to disk
```

### Voice Options

```php
$response = Atlas::audio('openai', 'tts-1-hd')
    ->instructions('This is high quality speech.')
    ->withVoice('nova')
    ->withSpeed(1.2)
    ->withFormat('mp3')
    ->asAudio();
```

### With ElevenLabs

```php
$response = Atlas::audio('elevenlabs', 'eleven_multilingual_v2')
    ->instructions('Welcome to the future of AI.')
    ->withVoice('Rachel')
    ->withLanguage('en')
    ->asAudio();
```

## Speech-to-Text (Transcription)

```php
use Atlasphp\Atlas\Input\Audio;

$response = Atlas::audio('openai', 'whisper-1')
    ->withMedia([Audio::fromPath('/path/to/recording.mp3')])
    ->asText();

echo $response->text;  // "Hello, this is the transcribed text..."
```

### From Different Sources

```php
Audio::fromUrl('https://example.com/audio.mp3')
Audio::fromPath('/path/to/file.wav')
Audio::fromStorage('recordings/meeting.mp3')
Audio::fromUpload($request->file('audio'))
```

## Storing Audio

```php
$response = Atlas::audio('openai', 'tts-1')
    ->instructions('Hello world')
    ->withVoice('alloy')
    ->asAudio();

$path = $response->store('public');
$path = $response->storeAs('audio/greeting.mp3', 'public');
```

## Sound Effects

Generate sound effects from text descriptions using ElevenLabs:

```php
$response = Atlas::audio('elevenlabs')
    ->instructions('A thunderstorm with heavy rain and distant rumbling')
    ->withDuration(5)
    ->withMeta(['_audio_mode' => 'sfx'])
    ->asAudio();

$response->store('public');
```

### SFX Options

```php
$response = Atlas::audio('elevenlabs')
    ->instructions('Footsteps on gravel, slow pace')
    ->withDuration(3)
    ->withProviderOptions([
        'loop' => true,              // Seamless looping
        'prompt_influence' => 0.8,   // How closely to follow the prompt
    ])
    ->withMeta(['_audio_mode' => 'sfx'])
    ->asAudio();
```

## Music Generation

Generate music from text prompts or composition plans using ElevenLabs:

```php
$response = Atlas::audio('elevenlabs')
    ->instructions('An upbeat jazz piano track with a walking bass line')
    ->withDuration(30)
    ->withMeta(['_audio_mode' => 'music'])
    ->asAudio();

$response->store('public');
```

### With Composition Plan

For more control, pass a structured composition plan:

```php
$response = Atlas::audio('elevenlabs')
    ->withProviderOptions([
        'composition_plan' => [
            ['text' => 'Gentle piano intro', 'duration_ms' => 5000],
            ['text' => 'Build with drums and bass', 'duration_ms' => 15000],
            ['text' => 'Fade out', 'duration_ms' => 5000],
        ],
        'strict_section_timing' => true,
    ])
    ->withMeta(['_audio_mode' => 'music'])
    ->asAudio();
```

::: tip Audio Mode
Sound effects and music are dispatched through the same `Atlas::audio()` builder. Set `'_audio_mode'` in meta to `'sfx'` or `'music'` to route to the appropriate handler. The default mode is `'tts'` (text-to-speech).
:::

## Supported Providers

| Provider | TTS | STT | SFX | Music | Features |
|----------|-----|-----|-----|-------|----------|
| OpenAI | tts-1, tts-1-hd | whisper-1 | — | — | Voices, speed, format |
| ElevenLabs | eleven_multilingual_v2 | — | eleven_text_to_sound_v2 | music endpoint | Voices, cloning, languages, SFX, music |
| xAI | grok-2-audio | — | — | — | TTS |

## AudioResponse

| Property | Type | Description |
|----------|------|-------------|
| `data` | `string` | Raw audio binary data |
| `format` | `?string` | Audio format (mp3, wav, etc.) |
| `meta` | `array` | Additional metadata |
| `asset` | `?Asset` | Linked asset (when persistence enabled) |

## Builder Reference

| Method | Description |
|--------|-------------|
| `instructions(string)` | Text to convert to speech |
| `withMedia(array)` | Audio files for transcription |
| `withVoice(string)` | Voice name or ID |
| `withVoiceClone(array)` | Voice cloning configuration |
| `withSpeed(float)` | Playback speed multiplier |
| `withLanguage(string)` | Language code |
| `withDuration(int)` | Duration in seconds |
| `withFormat(string)` | Output format (mp3, wav, ogg, etc.) |
| `withProviderOptions(array)` | Provider-specific options |
| `withVariables(array)` | Variables for instruction interpolation |
| `withMeta(array)` | Metadata for middleware/events |
| `withMiddleware(array)` | Per-request provider middleware |
| `queue()` | Dispatch to queue |
