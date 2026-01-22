# Atlas Facade Specification

> **Module:** `Atlasphp\Atlas\Providers\Facades`
> **Status:** Implemented (Phase 3)

---

## Overview

The Atlas Facade provides the primary consumer API for the Atlas package. It exposes `AtlasManager` methods through Laravel's facade pattern, enabling static-style access to chat, embedding, image, and speech operations.

**Design Principle:** The facade is a thin wrapper over `AtlasManager`. All business logic resides in the manager and underlying services.

---

## Components

### Atlas Facade

```php
namespace Atlasphp\Atlas\Providers\Facades;

use Illuminate\Support\Facades\Facade;

class Atlas extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AtlasManager::class;
    }
}
```

### AtlasManager

The manager orchestrates all Atlas capabilities:

```php
namespace Atlasphp\Atlas\Providers\Services;

class AtlasManager
{
    public function __construct(
        protected AgentResolver $agentResolver,
        protected AgentExecutorContract $agentExecutor,
        protected EmbeddingService $embeddingService,
        protected ImageService $imageService,
        protected SpeechService $speechService,
    ) {}
}
```

### MessageContextBuilder

Immutable builder for conversation context:

```php
namespace Atlasphp\Atlas\Providers\Support;

final readonly class MessageContextBuilder
{
    public function __construct(
        private AtlasManager $manager,
        private array $messages = [],
        private array $variables = [],
        private array $metadata = [],
    ) {}
}
```

---

## Chat API

### Basic Chat

```php
Atlas::chat(
    string|AgentContract $agent,
    string $input,
    ?array $messages = null,
    ?Schema $schema = null,
): AgentResponse
```

**Parameters:**
- `$agent` - Agent key, class name, or instance
- `$input` - User message
- `$messages` - Optional conversation history
- `$schema` - Optional schema for structured output

**Examples:**

```php
// By registry key
$response = Atlas::chat('support-agent', 'Hello');

// By class name
$response = Atlas::chat(SupportAgent::class, 'Hello');

// By instance
$response = Atlas::chat(new SupportAgent(), 'Hello');

// With history
$response = Atlas::chat('support-agent', 'Continue', messages: $history);

// Structured output
$response = Atlas::chat('support-agent', 'Extract', schema: $schema);
echo $response->structured['field'];
```

### Multi-Turn Conversations

```php
Atlas::forMessages(array $messages): MessageContextBuilder
```

Returns an immutable builder for context configuration:

```php
$response = Atlas::forMessages($messages)
    ->withVariables(['user_name' => 'John'])
    ->withMetadata(['user_id' => 123])
    ->chat('support-agent', 'Continue');
```

### MessageContextBuilder Methods

```php
// Add variables for system prompt interpolation
public function withVariables(array $variables): self

// Add metadata for pipeline middleware
public function withMetadata(array $metadata): self

// Execute chat with current context
public function chat(
    string|AgentContract $agent,
    string $input,
    ?Schema $schema = null,
): AgentResponse

// Accessors
public function getMessages(): array
public function getVariables(): array
public function getMetadata(): array
```

---

## Embedding API

```php
// Single text
Atlas::embed(string $text, array $options = []): array<int, float>

// Multiple texts
Atlas::embedBatch(array $texts, array $options = []): array<int, array<int, float>>

// Get dimensions
Atlas::embeddingDimensions(): int
```

**Options:**
- `dimensions` - Output embedding dimensions (for models that support variable dimensions)
- `encoding_format` - Encoding format ('float' or 'base64')

**Example:**

```php
$embedding = Atlas::embed('Hello, world!');
// [0.123, 0.456, ...]

// With custom dimensions
$embedding = Atlas::embed('Hello, world!', ['dimensions' => 256]);
// [0.123, 0.456, ...] (256 floats)

$embeddings = Atlas::embedBatch(['Text 1', 'Text 2']);
// [[0.123, ...], [0.456, ...]]

// Batch with options
$embeddings = Atlas::embedBatch(['Text 1', 'Text 2'], ['dimensions' => 512]);

$dimensions = Atlas::embeddingDimensions();
// 1536
```

---

## Image API

```php
Atlas::image(?string $provider = null, ?string $model = null): ImageService
```

Returns a fluent `ImageService` for image generation:

```php
// Using defaults
$result = Atlas::image()->generate('A sunset over mountains');

// With specific provider and model
$result = Atlas::image('openai', 'dall-e-3')
    ->size('1024x1024')
    ->quality('hd')
    ->generate('A sunset over mountains');

// With provider-specific options
$result = Atlas::image('openai', 'dall-e-3')
    ->size('1024x1024')
    ->quality('hd')
    ->withProviderOptions(['style' => 'vivid'])  // OpenAI: 'vivid' or 'natural'
    ->generate('A photorealistic portrait');

// Result structure
[
    'url' => 'https://...',
    'base64' => null,
    'revised_prompt' => '...',
]
```

**ImageService Methods:**
- `using(string $provider): self` - Set provider
- `model(string $model): self` - Set model
- `size(string $size): self` - Set image size
- `quality(string $quality): self` - Set quality
- `withProviderOptions(array $options): self` - Set provider-specific options
- `generate(string $prompt, array $options = []): array` - Generate image

---

## Speech API

```php
Atlas::speech(?string $provider = null, ?string $model = null): SpeechService
```

Returns a fluent `SpeechService` for text-to-speech and transcription:

```php
// Text to speech
$result = Atlas::speech()
    ->voice('nova')
    ->format('mp3')
    ->speak('Hello, world!');
// ['audio' => '...', 'format' => 'mp3']

// With speed control
$result = Atlas::speech()
    ->voice('nova')
    ->speed(1.25)  // 0.25 to 4.0 for OpenAI
    ->speak('Faster speech.');

// With provider-specific options
$result = Atlas::speech()
    ->voice('nova')
    ->withProviderOptions(['language' => 'en'])
    ->speak('Hello!');

// With specific provider and model
$result = Atlas::speech('openai', 'tts-1-hd')
    ->speak('Hello!');

// Transcription
$result = Atlas::speech()
    ->transcriptionModel('whisper-1')
    ->transcribe('/path/to/audio.mp3');
// ['text' => '...', 'language' => 'en', 'duration' => 5.2]

// Transcription with options
$result = Atlas::speech()
    ->transcriptionModel('whisper-1')
    ->withProviderOptions(['language' => 'en', 'prompt' => 'Technical context'])
    ->transcribe('/path/to/audio.mp3');
```

**SpeechService Methods:**
- `using(string $provider): self` - Set provider
- `model(string $model): self` - Set TTS model
- `transcriptionModel(string $model): self` - Set transcription model
- `voice(string $voice): self` - Set voice for TTS
- `speed(float $speed): self` - Set speech speed (0.25-4.0 for OpenAI)
- `format(string $format): self` - Set audio format
- `withProviderOptions(array $options): self` - Set provider-specific options
- `speak(string $text, array $options = []): array` - Convert text to speech
- `transcribe(Audio|string $audio, array $options = []): array` - Transcribe audio

---

## AgentResponse

All chat operations return an `AgentResponse`:

```php
// Properties
$response->text;        // ?string - Text response
$response->structured;  // mixed - Structured data (when using schema)
$response->toolCalls;   // array - Tool calls made
$response->usage;       // array - Token usage
$response->metadata;    // array - Additional metadata

// Methods
$response->hasText(): bool
$response->hasStructured(): bool
$response->hasToolCalls(): bool
$response->hasUsage(): bool
$response->totalTokens(): int
$response->promptTokens(): int
$response->completionTokens(): int
$response->get(string $key, mixed $default = null): mixed
$response->withMetadata(array $metadata): self
$response->withUsage(array $usage): self
```

---

## Service Provider Registration

`AtlasManager` is registered as a singleton:

```php
$this->app->singleton(AtlasManager::class, function (Container $app): AtlasManager {
    return new AtlasManager(
        $app->make(AgentResolver::class),
        $app->make(AgentExecutorContract::class),
        $app->make(EmbeddingService::class),
        $app->make(ImageService::class),
        $app->make(SpeechService::class),
    );
});
```

---

## Usage Examples

### Simple Chat

```php
$response = Atlas::chat('assistant', 'What is 2 + 2?');
echo $response->text; // "4"
```

### Conversation with Context

```php
$messages = [
    ['role' => 'user', 'content' => 'My name is Alice'],
    ['role' => 'assistant', 'content' => 'Hello Alice!'],
];

$response = Atlas::forMessages($messages)
    ->withVariables(['timezone' => 'America/New_York'])
    ->withMetadata(['session_id' => 'abc123'])
    ->chat('assistant', 'What is my name?');

echo $response->text; // "Your name is Alice."
```

### Structured Output

```php
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

$schema = new ObjectSchema(
    name: 'person',
    description: 'Person information',
    properties: [
        new StringSchema('name', 'Full name'),
        new StringSchema('email', 'Email address'),
    ],
    requiredFields: ['name', 'email'],
);

$response = Atlas::chat(
    'extractor',
    'Extract: John Smith, john@example.com',
    schema: $schema,
);

$person = $response->structured;
// ['name' => 'John Smith', 'email' => 'john@example.com']
```

### Multimodal Operations

```php
// Embedding with custom dimensions
$vector = Atlas::embed('Search query', ['dimensions' => 256]);

// Image generation with provider options
$image = Atlas::image('openai', 'dall-e-3')
    ->size('1024x1024')
    ->quality('hd')
    ->withProviderOptions(['style' => 'vivid'])
    ->generate('A futuristic city');

// Text to speech with speed
$audio = Atlas::speech('openai', 'tts-1')
    ->voice('alloy')
    ->speed(1.0)
    ->speak('Welcome to Atlas!');

// Transcription with options
$text = Atlas::speech()
    ->withProviderOptions(['language' => 'en'])
    ->transcribe('/path/to/recording.mp3');
```
