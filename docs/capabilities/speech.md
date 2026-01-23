# Speech

Text-to-speech (TTS) and speech-to-text (STT) capabilities for voice-enabled applications.

## Text to Speech

Convert text to spoken audio:

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

$result = Atlas::speech()->speak('Hello, welcome to our service!');

// Save audio file
file_put_contents('welcome.mp3', base64_decode($result['audio']));
```

## Speech Configuration

### Voice Selection

```php
$result = Atlas::speech('openai', 'tts-1')
    ->voice('nova')
    ->speak('Thank you for calling.');
```

**Available OpenAI voices:** `alloy`, `echo`, `fable`, `onyx`, `nova`, `shimmer`

### Audio Format

```php
$result = Atlas::speech()
    ->voice('nova')
    ->format('mp3')
    ->speak('Hello world!');
```

**Supported formats:** `mp3`, `opus`, `aac`, `flac`

### Speed Control

```php
$result = Atlas::speech('openai', 'tts-1')
    ->voice('nova')
    ->speed(1.25)  // 0.25 to 4.0 for OpenAI
    ->speak('This is faster speech.');
```

### HD Quality

```php
$result = Atlas::speech('openai', 'tts-1-hd')
    ->voice('alloy')
    ->speak('This is high-definition audio quality.');
```

## Fluent Configuration

```php
$result = Atlas::speech()
    ->using('openai')
    ->model('tts-1')
    ->voice('nova')
    ->format('mp3')
    ->speed(1.0)
    ->speak('Complete configuration example.');
```

## Provider Options

```php
$result = Atlas::speech()
    ->voice('nova')
    ->withProviderOptions(['language' => 'en'])
    ->speak('Hello world!');
```

## TTS Response Format

```php
$result = Atlas::speech()->voice('alloy')->speak('Hello');

// Result structure
[
    'audio' => '...',  // Base64 encoded audio
    'format' => 'mp3', // Audio format
]
```

## Speech to Text (Transcription)

Convert audio to text:

```php
$result = Atlas::speech()->transcribe('/path/to/audio.mp3');

echo $result['text'];      // Transcribed text
echo $result['language'];  // Detected language (e.g., 'en')
echo $result['duration'];  // Audio duration in seconds
```

### Transcription Model

```php
$result = Atlas::speech()
    ->transcriptionModel('whisper-1')
    ->transcribe('/path/to/recording.mp3');
```

### Transcription Options

```php
$result = Atlas::speech()
    ->transcriptionModel('whisper-1')
    ->withProviderOptions([
        'language' => 'en',
        'prompt' => 'Technical terminology context',
    ])
    ->transcribe('/path/to/audio.mp3');
```

## STT Response Format

```php
$result = Atlas::speech()->transcribe($audioPath);

// Result structure
[
    'text' => '...',      // Transcribed text
    'language' => 'en',   // Detected language
    'duration' => 5.2,    // Audio duration in seconds
]
```

## Configuration

Configure defaults in `config/atlas.php`:

```php
'speech' => [
    'provider' => 'openai',
    'model' => 'tts-1',
    'transcription_model' => 'whisper-1',
],
```

## PendingSpeechRequest Methods

| Method | Description |
|--------|-------------|
| `using(string $provider)` | Set provider |
| `model(string $model)` | Set TTS model |
| `transcriptionModel(string $model)` | Set transcription model |
| `voice(string $voice)` | Set voice for TTS |
| `speed(float $speed)` | Set speech speed (0.25-4.0) |
| `format(string $format)` | Set audio format |
| `withProviderOptions(array $options)` | Set provider-specific options |
| `withMetadata(array $metadata)` | Set metadata for pipelines |
| `withRetry($times, $delay, $when, $throw)` | Configure retry |
| `speak(string $text, array $options = [])` | Convert text to speech |
| `transcribe(Audio\|string $audio, array $options = [])` | Transcribe audio |

## Use Cases

### Voice Notifications

```php
class NotificationService
{
    public function sendVoiceNotification(User $user, string $message): void
    {
        $audio = Atlas::speech()
            ->voice('nova')
            ->format('mp3')
            ->speak($message);

        $filename = 'notifications/' . Str::uuid() . '.mp3';
        Storage::put($filename, base64_decode($audio['audio']));

        // Send via phone/voice service
        $this->voiceService->call($user->phone, Storage::url($filename));
    }
}
```

### Podcast Generation

```php
class PodcastGenerator
{
    public function generate(Article $article): string
    {
        $script = $this->prepareScript($article);

        $audio = Atlas::speech('openai', 'tts-1-hd')
            ->voice('onyx')
            ->speed(0.9)  // Slightly slower for clarity
            ->speak($script);

        $filename = "podcasts/{$article->slug}.mp3";
        Storage::put($filename, base64_decode($audio['audio']));

        return Storage::url($filename);
    }
}
```

### Voice Transcription

```php
class TranscriptionController extends Controller
{
    public function transcribe(Request $request)
    {
        $request->validate([
            'audio' => 'required|file|mimes:mp3,wav,m4a|max:25000',
        ]);

        $result = Atlas::speech()
            ->transcriptionModel('whisper-1')
            ->transcribe($request->file('audio')->path());

        return response()->json([
            'text' => $result['text'],
            'language' => $result['language'],
            'duration' => $result['duration'],
        ]);
    }
}
```

### Meeting Notes

```php
class MeetingService
{
    public function processRecording(string $audioPath): array
    {
        // Transcribe
        $transcription = Atlas::speech()
            ->transcriptionModel('whisper-1')
            ->transcribe($audioPath);

        // Summarize with AI
        $summary = Atlas::agent('summarizer')
            ->chat("Summarize this meeting transcript:\n\n{$transcription['text']}");

        return [
            'transcript' => $transcription['text'],
            'duration' => $transcription['duration'],
            'summary' => $summary->text,
        ];
    }
}
```

## Voice Characteristics

| Voice | Description |
|-------|-------------|
| `alloy` | Neutral, balanced |
| `echo` | Clear, confident |
| `fable` | Warm, expressive |
| `onyx` | Deep, authoritative |
| `nova` | Friendly, natural |
| `shimmer` | Clear, energetic |

## Best Practices

### 1. Choose Appropriate Voice

Match voice to content:
- **nova** — Customer service, friendly content
- **onyx** — Professional announcements, authoritative content
- **fable** — Storytelling, engaging content

### 2. Optimize for Length

For long content, consider:
- Breaking into segments
- Using lower quality for drafts
- Caching generated audio

### 3. Handle Errors with Retry

Atlas provides built-in retry functionality:

```php
// Simple retry: 3 attempts, 1 second delay
$result = Atlas::speech()
    ->withRetry(3, 1000)
    ->voice('nova')
    ->speak($text);

// Exponential backoff
$result = Atlas::speech()
    ->withRetry(3, fn($attempt) => (2 ** $attempt) * 100)
    ->speak($text);

// Only retry on rate limits
$result = Atlas::speech()
    ->withRetry(3, 1000, fn($e) => $e->getCode() === 429)
    ->transcribe($audioPath);
```

Or handle manually:

```php
try {
    $result = Atlas::speech()->speak($text);
} catch (ProviderException $e) {
    Log::error('Speech generation failed', ['error' => $e->getMessage()]);
    // Fallback logic
}
```

## API Summary

**Text-to-Speech:**
```php
Atlas::speech()->speak($text)
Atlas::speech()->voice('nova')->speak($text)
Atlas::speech('openai', 'tts-1-hd')->speak($text)
```

**Speech-to-Text:**
```php
Atlas::speech()->transcribe($audioPath)
Atlas::speech()->transcriptionModel('whisper-1')->transcribe($audioPath)
```

## Next Steps

- [Configuration](/getting-started/configuration) — Configure speech providers
- [Chat](/capabilities/chat) — Combine with chat for voice assistants
- [Embeddings](/capabilities/embeddings) — Index transcriptions for search
