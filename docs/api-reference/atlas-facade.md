# Atlas Facade

The Atlas Facade provides the primary API for interacting with Atlas. It exposes `AtlasManager` methods through Laravel's facade pattern.

## Overview

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

// Chat with an agent (agent-first pattern)
$response = Atlas::agent('support-agent')->chat('Hello');

// Chat with conversation history
$response = Atlas::agent('support-agent')
    ->withMessages($messages)
    ->chat('Continue');

// With variables and metadata
$response = Atlas::agent('support-agent')
    ->withMessages($messages)
    ->withVariables(['user_name' => 'John'])
    ->withMetadata(['session_id' => 'abc'])
    ->chat('Hello');

// Structured output with schema
$response = Atlas::agent('support-agent')
    ->withSchema($schema)
    ->chat('Extract the data');

// Embeddings
$embedding = Atlas::embed('Hello world');

// Embedding with configuration
$embedding = Atlas::embedding()
    ->withMetadata(['user_id' => 123])
    ->withRetry(3, 1000)
    ->generate('Hello world');

// Image generation
$result = Atlas::image()
    ->withMetadata(['user_id' => 123])
    ->generate('A sunset');

// Speech
$audio = Atlas::speech()
    ->withMetadata(['user_id' => 123])
    ->speak('Hello');
```

## Agent Methods

### agent()

Start building a chat request for an agent.

```php
Atlas::agent(string|AgentContract $agent): PendingAgentRequest
```

**Parameters:**
- `$agent` — Agent key, class name, or instance

**Returns:** `PendingAgentRequest` — Fluent builder for chat operations

**Examples:**

```php
// By registry key
$response = Atlas::agent('support-agent')->chat('Hello');

// By class name
$response = Atlas::agent(SupportAgent::class)->chat('Hello');

// By instance
$response = Atlas::agent(new SupportAgent())->chat('Hello');
```

## PendingAgentRequest

Immutable fluent builder for agent chat operations.

### withMessages()

Set conversation history.

```php
public function withMessages(array $messages): static
```

**Parameters:**
- `$messages` — Array of `['role' => string, 'content' => string]`

**Example:**

```php
$response = Atlas::agent('support-agent')
    ->withMessages([
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi there!'],
    ])
    ->chat('Continue our conversation');
```

### withVariables()

Set variables for system prompt interpolation.

```php
public function withVariables(array $variables): static
```

**Parameters:**
- `$variables` — Variables for system prompt interpolation

**Example:**

```php
$response = Atlas::agent('support-agent')
    ->withVariables(['user_name' => 'John'])
    ->chat('Hello');
```

### withMetadata()

Set metadata for pipeline middleware and tools.

```php
public function withMetadata(array $metadata): static
```

**Parameters:**
- `$metadata` — Metadata for pipelines and tools

**Example:**

```php
$response = Atlas::agent('support-agent')
    ->withMetadata(['session_id' => 'abc123', 'user_id' => 456])
    ->chat('Hello');
```

### withSchema()

Set schema for structured output.

```php
public function withSchema(Schema $schema): static
```

**Parameters:**
- `$schema` — Schema defining the expected response structure

**Example:**

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
    requiredFields: ['name'],
);

$response = Atlas::agent('support-agent')
    ->withSchema($schema)
    ->chat('Extract person info from: John Smith, john@example.com');

$data = $response->structured;
// ['name' => 'John Smith', 'email' => 'john@example.com']
```

### withRetry()

Configure retry behavior for API requests.

```php
public function withRetry(
    array|int $times,
    Closure|int $sleepMilliseconds = 0,
    ?callable $when = null,
    bool $throw = true,
): static
```

**Parameters:**
- `$times` — Number of retries OR array of delay durations
- `$sleepMilliseconds` — Fixed delay or callback for dynamic delay
- `$when` — Condition to determine when to retry
- `$throw` — Whether to throw after all retries fail

**Example:**

```php
$response = Atlas::agent('support-agent')
    ->withRetry(3, 1000)
    ->chat('Hello');
```

### chat()

Execute the chat with the configured agent.

```php
public function chat(
    string $input,
    bool $stream = false,
): AgentResponse|StreamResponse
```

**Parameters:**
- `$input` — User message
- `$stream` — Whether to stream the response

**Returns:** `AgentResponse` or `StreamResponse`

**Examples:**

```php
// Simple chat
$response = Atlas::agent('support-agent')->chat('Hello');

// Streaming
$stream = Atlas::agent('support-agent')->chat('Hello', stream: true);

// Structured output
$response = Atlas::agent('support-agent')
    ->withSchema($schema)
    ->chat('Extract person info');

// Full configuration
$response = Atlas::agent('support-agent')
    ->withMessages($history)
    ->withVariables(['user_name' => 'John'])
    ->withMetadata(['session_id' => 'abc'])
    ->withRetry(3, 1000)
    ->chat('Continue');
```

## Embedding Methods

### embedding()

Get a fluent builder for embedding operations.

```php
Atlas::embedding(): PendingEmbeddingRequest
```

**Returns:** `PendingEmbeddingRequest`

**Example:**

```php
$embedding = Atlas::embedding()
    ->withMetadata(['user_id' => 123])
    ->withRetry(3, 1000)
    ->generate('Hello world');
```

### embed()

Simple shortcut for embedding a single text.

```php
Atlas::embed(string $text): array<int, float>
```

**Parameters:**
- `$text` — Text to embed

**Returns:** Array of floats (embedding vector)

**Example:**

```php
$embedding = Atlas::embed('Hello, world!');
// [0.123, 0.456, ...]
```

### embedBatch()

Simple shortcut for embedding multiple texts.

```php
Atlas::embedBatch(array $texts): array<int, array<int, float>>
```

**Parameters:**
- `$texts` — Array of texts to embed

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

## PendingEmbeddingRequest

Immutable fluent builder for embedding operations with metadata and retry support.

### withMetadata()

Set metadata for pipeline middleware.

```php
public function withMetadata(array $metadata): static
```

### withRetry()

Configure retry behavior.

```php
public function withRetry(
    array|int $times,
    Closure|int $sleepMilliseconds = 0,
    ?callable $when = null,
    bool $throw = true,
): static
```

### generate()

Generate embedding for a single text.

```php
public function generate(string $text): array<int, float>
```

### generateBatch()

Generate embeddings for multiple texts.

```php
public function generateBatch(array $texts): array<int, array<int, float>>
```

## Image Methods

### image()

Get a fluent builder for image generation.

```php
Atlas::image(?string $provider = null, ?string $model = null): PendingImageRequest
```

**Parameters:**
- `$provider` — Optional provider name
- `$model` — Optional model name

**Returns:** `PendingImageRequest`

**Example:**

```php
$result = Atlas::image()
    ->withMetadata(['user_id' => 123])
    ->generate('A sunset');

$result = Atlas::image('openai', 'dall-e-3')
    ->size('1024x1024')
    ->quality('hd')
    ->withMetadata(['user_id' => 123])
    ->generate('A sunset');
```

### PendingImageRequest Methods

| Method | Description |
|--------|-------------|
| `using(string $provider)` | Set provider |
| `model(string $model)` | Set model |
| `size(string $size)` | Set image size |
| `quality(string $quality)` | Set quality |
| `withProviderOptions(array $options)` | Provider-specific options |
| `withMetadata(array $metadata)` | Set metadata for pipelines |
| `withRetry($times, $delay, $when, $throw)` | Configure retry |
| `generate(string $prompt, array $options = [])` | Generate image |

## Speech Methods

### speech()

Get a fluent builder for speech operations.

```php
Atlas::speech(?string $provider = null, ?string $model = null): PendingSpeechRequest
```

**Parameters:**
- `$provider` — Optional provider name
- `$model` — Optional model name

**Returns:** `PendingSpeechRequest`

**Example:**

```php
// Text to speech
$result = Atlas::speech()
    ->voice('nova')
    ->withMetadata(['user_id' => 123])
    ->speak('Hello!');

// Transcription
$result = Atlas::speech()
    ->withMetadata(['user_id' => 123])
    ->transcribe('/path/to/audio.mp3');
```

### PendingSpeechRequest Methods

| Method | Description |
|--------|-------------|
| `using(string $provider)` | Set provider |
| `model(string $model)` | Set TTS model |
| `transcriptionModel(string $model)` | Set STT model |
| `voice(string $voice)` | Set voice |
| `speed(float $speed)` | Set speech speed |
| `format(string $format)` | Set audio format |
| `withProviderOptions(array $options)` | Provider-specific options |
| `withMetadata(array $metadata)` | Set metadata for pipelines |
| `withRetry($times, $delay, $when, $throw)` | Configure retry |
| `speak(string $text, array $options = [])` | Text to speech |
| `transcribe(Audio\|string $audio, array $options = [])` | Speech to text |

## Quick Reference

| Method | Description |
|--------|-------------|
| `Atlas::agent($agent)` | Get chat builder for agent |
| `Atlas::agent($agent)->chat($input)` | Simple chat |
| `Atlas::agent($agent)->withMessages($msgs)->chat($input)` | Chat with history |
| `Atlas::agent($agent)->withSchema($schema)->chat($input)` | Structured output |
| `Atlas::agent($agent)->chat($input, stream: true)` | Streaming response |
| `Atlas::embedding()` | Get embedding builder |
| `Atlas::embed($text)` | Simple embedding |
| `Atlas::embedBatch($texts)` | Batch embeddings |
| `Atlas::embeddingDimensions()` | Get dimensions |
| `Atlas::image()` | Image request builder |
| `Atlas::speech()` | Speech request builder |

## Next Steps

- [AgentContract](/api-reference/agent-contract) — Agent interface
- [Response Objects](/api-reference/response-objects) — AgentResponse API
- [Context Objects](/api-reference/context-objects) — ExecutionContext, ToolContext
