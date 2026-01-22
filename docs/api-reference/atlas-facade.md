# Atlas Facade

The Atlas Facade provides the primary API for interacting with Atlas. It exposes `AtlasManager` methods through Laravel's facade pattern.

## Overview

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

// Chat with an agent
$response = Atlas::chat('support-agent', 'Hello');

// Multi-turn conversation
$response = Atlas::forMessages($messages)
    ->withVariables(['user_name' => 'John'])
    ->chat('support-agent', 'Continue');

// Embeddings
$embedding = Atlas::embed('Hello world');

// Image generation
$result = Atlas::image()->generate('A sunset');

// Speech
$audio = Atlas::speech()->speak('Hello');
```

## Chat Methods

### chat()

Execute a chat with an agent.

```php
Atlas::chat(
    string|AgentContract $agent,
    string $input,
    ?array $messages = null,
    ?Schema $schema = null,
): AgentResponse
```

**Parameters:**
- `$agent` — Agent key, class name, or instance
- `$input` — User message
- `$messages` — Optional conversation history
- `$schema` — Optional schema for structured output

**Returns:** `AgentResponse`

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
```

### forMessages()

Create a context builder for multi-turn conversations.

```php
Atlas::forMessages(array $messages): MessageContextBuilder
```

**Parameters:**
- `$messages` — Conversation history array

**Returns:** `MessageContextBuilder`

**Example:**

```php
$response = Atlas::forMessages($messages)
    ->withVariables(['user_name' => 'John'])
    ->withMetadata(['user_id' => 123])
    ->chat('support-agent', 'Continue');
```

## MessageContextBuilder

Immutable builder for conversation context.

### withVariables()

Add variables for system prompt interpolation.

```php
public function withVariables(array $variables): self
```

### withMetadata()

Add metadata for pipeline middleware and tools.

```php
public function withMetadata(array $metadata): self
```

### chat()

Execute chat with current context.

```php
public function chat(
    string|AgentContract $agent,
    string $input,
    ?Schema $schema = null,
): AgentResponse
```

### Accessors

```php
public function getMessages(): array
public function getVariables(): array
public function getMetadata(): array
```

## Embedding Methods

### embed()

Generate embedding for a single text.

```php
Atlas::embed(string $text, array $options = []): array<int, float>
```

**Parameters:**
- `$text` — Text to embed
- `$options` — Optional settings (`dimensions`, `encoding_format`)

**Returns:** Array of floats (embedding vector)

**Example:**

```php
$embedding = Atlas::embed('Hello, world!');
// [0.123, 0.456, ...]

$embedding = Atlas::embed('Hello', ['dimensions' => 256]);
// [0.123, ...] (256 floats)
```

### embedBatch()

Generate embeddings for multiple texts.

```php
Atlas::embedBatch(array $texts, array $options = []): array<int, array<int, float>>
```

**Parameters:**
- `$texts` — Array of texts to embed
- `$options` — Optional settings

**Returns:** Array of embedding vectors

**Example:**

```php
$embeddings = Atlas::embedBatch(['Text 1', 'Text 2']);
// [[0.123, ...], [0.456, ...]]
```

### embeddingDimensions()

Get configured embedding dimensions.

```php
Atlas::embeddingDimensions(): int
```

**Returns:** Number of dimensions (e.g., 1536)

## Image Methods

### image()

Get the image generation service.

```php
Atlas::image(?string $provider = null, ?string $model = null): ImageService
```

**Parameters:**
- `$provider` — Optional provider name
- `$model` — Optional model name

**Returns:** `ImageService`

**Example:**

```php
$result = Atlas::image()->generate('A sunset');

$result = Atlas::image('openai', 'dall-e-3')
    ->size('1024x1024')
    ->quality('hd')
    ->generate('A sunset');
```

### ImageService Methods

| Method | Description |
|--------|-------------|
| `using(string $provider)` | Set provider |
| `model(string $model)` | Set model |
| `size(string $size)` | Set image size |
| `quality(string $quality)` | Set quality |
| `withProviderOptions(array $options)` | Provider-specific options |
| `generate(string $prompt, array $options = [])` | Generate image |

## Speech Methods

### speech()

Get the speech service.

```php
Atlas::speech(?string $provider = null, ?string $model = null): SpeechService
```

**Parameters:**
- `$provider` — Optional provider name
- `$model` — Optional model name

**Returns:** `SpeechService`

**Example:**

```php
// Text to speech
$result = Atlas::speech()
    ->voice('nova')
    ->speak('Hello!');

// Transcription
$result = Atlas::speech()
    ->transcribe('/path/to/audio.mp3');
```

### SpeechService Methods

| Method | Description |
|--------|-------------|
| `using(string $provider)` | Set provider |
| `model(string $model)` | Set TTS model |
| `transcriptionModel(string $model)` | Set STT model |
| `voice(string $voice)` | Set voice |
| `speed(float $speed)` | Set speech speed |
| `format(string $format)` | Set audio format |
| `withProviderOptions(array $options)` | Provider-specific options |
| `speak(string $text, array $options = [])` | Text to speech |
| `transcribe(Audio\|string $audio, array $options = [])` | Speech to text |

## Quick Reference

| Method | Description |
|--------|-------------|
| `Atlas::chat($agent, $input)` | Simple chat |
| `Atlas::chat($agent, $input, $messages)` | Chat with history |
| `Atlas::chat($agent, $input, null, $schema)` | Structured output |
| `Atlas::forMessages($messages)` | Context builder |
| `Atlas::embed($text)` | Single embedding |
| `Atlas::embed($text, $options)` | Embedding with options |
| `Atlas::embedBatch($texts)` | Batch embeddings |
| `Atlas::embeddingDimensions()` | Get dimensions |
| `Atlas::image()` | Image service |
| `Atlas::image($provider, $model)` | Image with config |
| `Atlas::speech()` | Speech service |
| `Atlas::speech($provider, $model)` | Speech with config |

## Next Steps

- [AgentContract](/api-reference/agent-contract) — Agent interface
- [Response Objects](/api-reference/response-objects) — AgentResponse API
- [Context Objects](/api-reference/context-objects) — ExecutionContext, ToolContext
