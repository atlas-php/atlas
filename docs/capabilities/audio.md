# Audio

Text-to-speech (TTS) and speech-to-text (STT) capabilities for voice-enabled applications.

::: tip Prism Reference
Atlas audio wraps Prism's audio API. For detailed documentation including all provider options, see [Prism Audio](https://prismphp.com/core-concepts/audio.html).
:::

## Text to Speech

Convert text to spoken audio:

```php
use Atlasphp\Atlas\Atlas;

$response = Atlas::audio()
    ->using('openai', 'tts-1')
    ->withVoice('nova')
    ->withInput('Hello, welcome to our service!')
    ->asAudio();

// Save audio file
Storage::put('welcome.mp3', $response->audio->rawContent());
```

## Speech to Text

Transcribe audio to text:

```php
use Atlasphp\Atlas\Atlas;
use Prism\Prism\ValueObjects\Media\Audio;

$response = Atlas::audio()
    ->using('openai', 'whisper-1')
    ->withInput(Audio::fromLocalPath('/path/to/audio.mp3'))
    ->asText();

echo $response->text;  // Transcribed text
```

## Text-to-Speech Examples

### With Voice Selection

```php
$response = Atlas::audio()
    ->using('openai', 'tts-1')
    ->withVoice('onyx')  // Deep, authoritative voice
    ->withInput('Important announcement.')
    ->asAudio();
```

**OpenAI voices:** `alloy`, `echo`, `fable`, `onyx`, `nova`, `shimmer`

### HD Quality

```php
$response = Atlas::audio()
    ->using('openai', 'tts-1-hd')
    ->withVoice('alloy')
    ->withInput('High-definition audio quality.')
    ->asAudio();
```

## Speech-to-Text Examples

### Basic Transcription

```php
use Prism\Prism\ValueObjects\Media\Audio;

$response = Atlas::audio()
    ->using('openai', 'whisper-1')
    ->withInput(Audio::fromLocalPath($request->file('audio')->path()))
    ->asText();

return response()->json([
    'text' => $response->text,
]);
```

### With Language Hint

```php
use Prism\Prism\ValueObjects\Media\Audio;

$response = Atlas::audio()
    ->using('openai', 'whisper-1')
    ->withInput(Audio::fromLocalPath('/path/to/audio.mp3'))
    ->withProviderOptions(['language' => 'en'])
    ->asText();
```

## Examples

### Example: Voice Notification Service

```php
class NotificationService
{
    public function sendVoiceNotification(User $user, string $message): void
    {
        $response = Atlas::audio()
            ->using('openai', 'tts-1')
            ->withVoice('nova')
            ->withInput($message)
            ->asAudio();

        $filename = 'notifications/' . Str::uuid() . '.mp3';
        Storage::put($filename, $response->audio->rawContent());

        $this->voiceService->call($user->phone, Storage::url($filename));
    }
}
```

### Example: Meeting Transcription

```php
use Prism\Prism\ValueObjects\Media\Audio;

class MeetingService
{
    public function processRecording(string $audioPath): array
    {
        // Transcribe
        $transcription = Atlas::audio()
            ->using('openai', 'whisper-1')
            ->withInput(Audio::fromLocalPath($audioPath))
            ->asText();

        // Summarize with AI
        $summary = Atlas::agent('summarizer')
            ->chat("Summarize this meeting transcript:\n\n{$transcription->text}");

        return [
            'transcript' => $transcription->text,
            'summary' => $summary->text,
        ];
    }
}
```

## Voice Characteristics

<div class="full-width-table">

| Voice | Description |
|-------|-------------|
| `alloy` | Neutral, balanced |
| `echo` | Clear, confident |
| `fable` | Warm, expressive |
| `onyx` | Deep, authoritative |
| `nova` | Friendly, natural |
| `shimmer` | Clear, energetic |

</div>

## Pipeline Hooks

Audio operations support pipeline middleware for observability:

<div class="full-width-table">

| Pipeline | Trigger |
|----------|---------|
| `audio.before_audio` | Before text-to-speech |
| `audio.after_audio` | After text-to-speech |
| `audio.before_text` | Before speech-to-text |
| `audio.after_text` | After speech-to-text |

</div>

```php
use Atlasphp\Atlas\Contracts\PipelineContract;

class LogAudioGeneration implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        $result = $next($data);

        Log::info('Audio generated', [
            'user_id' => $data['metadata']['user_id'] ?? null,
        ]);

        return $result;
    }
}

$registry->register('audio.after_audio', LogAudioGeneration::class);
```

## API Reference

```php
// Text-to-Speech fluent API
Atlas::audio()
    ->using(string $provider, string $model)              // Set provider and model
    ->withVoice(string $voice)                            // Voice selection
    ->withInput(string $text)                             // Text to convert
    ->withProviderOptions(array $options)                 // Provider-specific options
    ->withMetadata(array $metadata)                       // Pipeline metadata
    ->asAudio(): AudioResponse;

// Speech-to-Text fluent API
use Prism\Prism\ValueObjects\Media\Audio;

Atlas::audio()
    ->using(string $provider, string $model)              // Set provider and model
    ->withInput(Audio::fromLocalPath(string $path))            // Audio file to transcribe
    ->withProviderOptions(array $options)                 // Provider-specific options
    ->withMetadata(array $metadata)                       // Pipeline metadata
    ->asText(): TextResponse;

// Text-to-Speech response (AudioResponse)
$response->audio->rawContent();  // Raw audio bytes (save directly to file)

// Speech-to-Text response (TextResponse)
$response->text;                 // Transcribed text

// OpenAI TTS models
->using('openai', 'tts-1')      // Standard quality
->using('openai', 'tts-1-hd')   // High definition

// OpenAI STT models
->using('openai', 'whisper-1')  // Whisper transcription

// OpenAI voices
->withVoice('alloy')    // Neutral, balanced
->withVoice('echo')     // Clear, confident
->withVoice('fable')    // Warm, expressive
->withVoice('onyx')     // Deep, authoritative
->withVoice('nova')     // Friendly, natural
->withVoice('shimmer')  // Clear, energetic

// Common provider options (via withProviderOptions)
// OpenAI TTS:
->withProviderOptions([
    'speed' => 1.0,              // 0.25 to 4.0
    'response_format' => 'mp3',  // 'mp3', 'opus', 'aac', 'flac'
])

// OpenAI STT:
->withProviderOptions([
    'language' => 'en',          // ISO-639-1 language code
    'temperature' => 0,          // 0 to 1
])
```

## Next Steps

- [Prism Audio](https://prismphp.com/core-concepts/audio.html) — Complete audio reference
- [Chat](/capabilities/chat) — Combine with chat for voice assistants
- [Pipelines](/core-concepts/pipelines) — Add observability to audio operations
