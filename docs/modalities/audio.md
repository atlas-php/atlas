# Audio

Atlas provides three focused audio modalities — speech, music, and sound effects — each with a dedicated API.

## Speech (TTS & STT)

Convert text to speech or transcribe audio to text:

```php
use Atlasphp\Atlas\Atlas;

$response = Atlas::speech('openai', 'tts-1')
    ->instructions('Hello, welcome to Atlas!')
    ->withVoice('alloy')
    ->asAudio();
```

→ [Full documentation](/modalities/speech)

## Music

Generate music from text prompts:

```php
$response = Atlas::music('elevenlabs')
    ->instructions('An upbeat jazz piano track')
    ->withDuration(30)
    ->asAudio();
```

→ [Full documentation](/modalities/music)

## Sound Effects

Generate sound effects from text descriptions:

```php
$response = Atlas::sfx('elevenlabs')
    ->instructions('Thunder with heavy rain')
    ->withDuration(5)
    ->asAudio();
```

→ [Full documentation](/modalities/sound-effects)

## Low-Level API

`Atlas::audio()` is the underlying entry point that all three modalities build on. Use it directly for advanced use cases or future audio capabilities:

```php
$response = Atlas::audio('openai', 'tts-1')
    ->instructions('Direct audio API usage')
    ->withVoice('alloy')
    ->asAudio();
```

## Supported Providers

| Provider | TTS | STT | SFX | Music | Features |
|----------|-----|-----|-----|-------|----------|
| OpenAI | tts-1, tts-1-hd | whisper-1 | — | — | Voices, speed, format |
| ElevenLabs | eleven_multilingual_v2 | Yes | eleven_text_to_sound_v2 | Yes | Voices, cloning, languages, SFX, music |
| xAI | grok-2-audio | — | — | — | TTS |

## Storing Audio

```php
$response->store('public');
$response->storeAs('audio/greeting.mp3', 'public');
```

::: tip Automatic Storage
When [persistence](/advanced/persistence) is enabled, generated audio is automatically stored to disk and tracked as an `Asset` record — no manual `store()` call needed. See each sub-page or [Media & Assets](/guides/media-storage) for details.
:::

## Persisted Asset

When [persistence](/advanced/persistence) is enabled, generated audio is automatically stored to disk:

```php
if ($response->asset) {
    $response->asset->path;       // Storage path
    $response->asset->mime_type;  // "audio/mpeg"
    $response->asset->disk;       // Filesystem disk
}
```

See [Media & Assets](/guides/media-storage) for the complete storage guide.

## Queue Support

All audio modalities support queue dispatch:

```php
Atlas::speech('openai', 'tts-1')
    ->instructions('Generate a long audiobook chapter')
    ->withVoice('nova')
    ->queue()
    ->asAudio()
    ->then(fn ($response) => $response->store('public'));
```

See [Speech](/modalities/speech), [Music](/modalities/music), and [Sound Effects](/modalities/sound-effects) for modality-specific queue examples.

## AudioResponse

| Property | Type | Description |
|----------|------|-------------|
| `data` | `string` | Raw audio binary data |
| `format` | `?string` | Audio format (mp3, wav, etc.) |
| `meta` | `array` | Additional metadata |
| `asset` | `?Asset` | Linked asset (when persistence enabled) |
