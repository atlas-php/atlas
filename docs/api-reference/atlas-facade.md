# Atlas Facade

The Atlas Facade provides the primary API for interacting with Atlas. It exposes `AtlasManager` methods through Laravel's facade pattern.

## Overview

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

// Chat with an agent (agent-first pattern)
$response = Atlas::agent('support-agent')->chat('Hello');

// Override provider/model at runtime
$response = Atlas::agent('support-agent')
    ->withProvider('anthropic')
    ->withModel('claude-3-opus')
    ->chat('Hello');

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

// Embeddings (single text)
$embedding = Atlas::embedding()->generate('Hello world');

// Embeddings (batch)
$embeddings = Atlas::embedding()->generate(['text 1', 'text 2']);

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
Atlas::agent(string|AgentContract $agent)
```

**Parameters:**
- `$agent` — Agent key, class name, or instance

**Returns:** Fluent builder for chat operations

**Examples:**

```php
// By registry key
$response = Atlas::agent('support-agent')->chat('Hello');

// By class name
$response = Atlas::agent(SupportAgent::class)->chat('Hello');

// By instance
$response = Atlas::agent(new SupportAgent())->chat('Hello');
```

## Agent Chat Builder

Immutable fluent builder for agent chat operations returned by `Atlas::agent()`.

### withProvider()

Override the agent's configured provider at runtime.

```php
->withProvider(string $provider)
```

**Parameters:**
- `$provider` — The provider name (e.g., 'openai', 'anthropic')

**Example:**

```php
// Use Anthropic instead of the agent's default provider
$response = Atlas::agent('support-agent')
    ->withProvider('anthropic')
    ->chat('Hello');
```

### withModel()

Override the agent's configured model at runtime.

```php
->withModel(string $model)
```

**Parameters:**
- `$model` — The model name (e.g., 'gpt-4', 'claude-3-opus')

**Example:**

```php
// Use a specific model
$response = Atlas::agent('support-agent')
    ->withModel('gpt-4-turbo')
    ->chat('Hello');

// Override both provider and model
$response = Atlas::agent('support-agent')
    ->withProvider('anthropic')
    ->withModel('claude-3-opus')
    ->chat('Hello');
```

### withMessages()

Set conversation history.

```php
->withMessages(array $messages)
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
->withVariables(array $variables)
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
->withMetadata(array $metadata)
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
->withSchema(Schema $schema)
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
->withRetry(
    array|int $times,
    Closure|int $sleepMilliseconds = 0,
    ?callable $when = null,
    bool $throw = true,
)
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
->chat(string $input, bool $stream = false): AgentResponse|StreamResponse
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
    ->withProvider('anthropic')
    ->withModel('claude-3-opus')
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
Atlas::embedding()
```

**Returns:** Fluent builder for embeddings

**Example:**

```php
// Single text
$embedding = Atlas::embedding()->generate('Hello world');

// Batch (array input)
$embeddings = Atlas::embedding()->generate(['text 1', 'text 2']);

// With configuration
$embedding = Atlas::embedding()
    ->withMetadata(['user_id' => 123])
    ->withRetry(3, 1000)
    ->generate('Hello world');
```

## Embedding Builder

Immutable fluent builder for embedding operations returned by `Atlas::embedding()`.

### withMetadata()

Set metadata for pipeline middleware.

```php
->withMetadata(array $metadata)
```

### withRetry()

Configure retry behavior.

```php
->withRetry(
    array|int $times,
    Closure|int $sleepMilliseconds = 0,
    ?callable $when = null,
    bool $throw = true,
)
```

### generate()

Generate embedding(s) for text input. Accepts either a single string or an array of strings.

```php
// Single text
->generate(string $input): array<int, float>

// Batch (array input)
->generate(array $input): array<int, array<int, float>>
```

**Examples:**

```php
// Single embedding
$embedding = Atlas::embedding()->generate('Hello world');
// [0.123, 0.456, ...]

// Batch embeddings
$embeddings = Atlas::embedding()->generate(['Text 1', 'Text 2']);
// [[0.123, ...], [0.456, ...]]
```

### dimensions()

Get configured embedding dimensions.

```php
->dimensions(): int
```

**Returns:** Number of dimensions (e.g., 1536)

**Example:**

```php
$dimensions = Atlas::embedding()->dimensions();
// 1536
```

## Image Methods

### image()

Get a fluent builder for image generation.

```php
Atlas::image(?string $provider = null, ?string $model = null)
```

**Parameters:**
- `$provider` — Optional provider name
- `$model` — Optional model name

**Returns:** Fluent builder for images

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

### Image Builder Methods

| Method | Description |
|--------|-------------|
| `withProvider(string $provider, ?string $model = null)` | Override provider and optionally model |
| `withModel(string $model)` | Override model |
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
Atlas::speech(?string $provider = null, ?string $model = null)
```

**Parameters:**
- `$provider` — Optional provider name
- `$model` — Optional model name

**Returns:** Fluent builder for speech

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

### Speech Builder Methods

| Method | Description |
|--------|-------------|
| `withProvider(string $provider)` | Override provider |
| `withModel(string $model)` | Override model |
| `using(string $provider)` | Alias for withProvider |
| `model(string $model)` | Alias for withModel |
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
| `Atlas::agent($agent)->withProvider($p)->chat($input)` | Chat with provider override |
| `Atlas::agent($agent)->withMessages($msgs)->chat($input)` | Chat with history |
| `Atlas::agent($agent)->withSchema($schema)->chat($input)` | Structured output |
| `Atlas::agent($agent)->chat($input, stream: true)` | Streaming response |
| `Atlas::embedding()->generate($text)` | Single embedding |
| `Atlas::embedding()->generate($texts)` | Batch embeddings (array input) |
| `Atlas::embedding()->dimensions()` | Get embedding dimensions |
| `Atlas::image()` | Image builder |
| `Atlas::speech()` | Speech builder |

## Next Steps

- [AgentContract](/api-reference/agent-contract) — Agent interface
- [Response Objects](/api-reference/response-objects) — AgentResponse API
- [Context Objects](/api-reference/context-objects) — ExecutionContext, ToolContext
