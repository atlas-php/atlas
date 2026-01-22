# Providers Module Specification

> **Module:** `Atlasphp\Atlas\Providers`
> **Status:** Implemented (Phase 1)

---

## Overview

The Providers module handles integration with AI providers through the Prism PHP library, offering:
- Embedding generation services
- Image generation services
- Text-to-speech and speech-to-text services
- Usage data extraction
- Provider configuration management

---

## Atlas Manager

The main entry point for Atlas capabilities.

### Usage via Facade

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

// Generate embeddings
$embedding = Atlas::embed('Hello world');
$embeddings = Atlas::embedBatch(['text 1', 'text 2']);
$dimensions = Atlas::embeddingDimensions();

// Access image service
$result = Atlas::image()
    ->using('openai')
    ->model('dall-e-3')
    ->size('1024x1024')
    ->generate('A beautiful sunset');

// Access speech service
$audio = Atlas::speech()
    ->voice('alloy')
    ->speak('Hello world');
```

---

## Embedding Service

### EmbeddingProviderContract

Contract for embedding providers.

```php
interface EmbeddingProviderContract
{
    public function generate(string $text, array $options = []): array;
    public function generateBatch(array $texts, array $options = []): array;
    public function dimensions(): int;
    public function provider(): string;
    public function model(): string;
}
```

### EmbeddingService

Service layer that delegates to the configured provider.

```php
$service = app(EmbeddingService::class);

// Basic usage
$embedding = $service->generate('Sample text');
$embeddings = $service->generateBatch(['text 1', 'text 2', 'text 3']);
$dimensions = $service->dimensions();

// With options (dimensions, encoding_format, etc.)
$embedding = $service->generate('Sample text', [
    'dimensions' => 256,
]);

$embeddings = $service->generateBatch(['text 1', 'text 2'], [
    'dimensions' => 512,
    'encoding_format' => 'float',
]);
```

**Options:**
- `dimensions` - Output embedding dimensions (for models that support variable dimensions)
- `encoding_format` - Encoding format ('float' or 'base64')

### PrismEmbeddingProvider

Default implementation using Prism PHP.

**Configuration:**
```php
// config/atlas.php
'embedding' => [
    'provider' => env('ATLAS_EMBEDDING_PROVIDER', 'openai'),
    'model' => env('ATLAS_EMBEDDING_MODEL', 'text-embedding-3-small'),
    'dimensions' => (int) env('ATLAS_EMBEDDING_DIMENSIONS', 1536),
    'batch_size' => (int) env('ATLAS_EMBEDDING_BATCH_SIZE', 100),
],
```

---

## Image Service

Fluent API for image generation using clone pattern for immutability.

```php
$service = app(ImageService::class);

// Basic usage
$result = $service->generate('A mountain landscape');

// With fluent configuration
$result = $service
    ->using('openai')
    ->model('dall-e-3')
    ->size('1024x1024')
    ->quality('hd')
    ->generate('A futuristic city');

// With provider-specific options
$result = $service
    ->using('openai')
    ->model('dall-e-3')
    ->size('1024x1024')
    ->quality('hd')
    ->withProviderOptions(['style' => 'vivid'])
    ->generate('A photorealistic portrait');

// Result structure
[
    'url' => 'https://...',      // Image URL
    'base64' => '...',           // Base64 data (if available)
    'revised_prompt' => '...',   // Provider's revised prompt
]
```

**Methods:**
- `using(string $provider): self` - Set provider
- `model(string $model): self` - Set model
- `size(string $size): self` - Set image size
- `quality(string $quality): self` - Set quality
- `withProviderOptions(array $options): self` - Set provider-specific options
- `generate(string $prompt, array $options = []): array` - Generate image

**Provider Options (OpenAI):**
- `style` - Image style ('vivid' or 'natural')
- `response_format` - Response format ('url' or 'b64_json')

---

## Speech Service

Fluent API for text-to-speech and speech-to-text operations.

### Text-to-Speech

```php
$service = app(SpeechService::class);

$result = $service
    ->using('openai')
    ->model('tts-1')
    ->voice('alloy')
    ->format('mp3')
    ->speak('Hello, world!');

// With speed control and provider options
$result = $service
    ->using('openai')
    ->model('tts-1')
    ->voice('nova')
    ->speed(1.25)
    ->withProviderOptions(['language' => 'en'])
    ->speak('Hello, world!');

// Result structure
[
    'audio' => '...',  // Base64 encoded audio
    'format' => 'mp3', // Audio format
]
```

### Speech-to-Text

```php
$result = $service
    ->using('openai')
    ->transcriptionModel('whisper-1')
    ->transcribe('/path/to/audio.mp3');

// With provider options
$result = $service
    ->using('openai')
    ->transcriptionModel('whisper-1')
    ->withProviderOptions(['language' => 'en', 'prompt' => 'Technical jargon'])
    ->transcribe('/path/to/audio.mp3');

// Result structure
[
    'text' => '...',      // Transcribed text
    'language' => 'en',   // Detected language
    'duration' => 5.2,    // Audio duration in seconds
]
```

**Methods:**
- `using(string $provider): self` - Set provider
- `model(string $model): self` - Set TTS model
- `transcriptionModel(string $model): self` - Set transcription model
- `voice(string $voice): self` - Set voice for TTS
- `speed(float $speed): self` - Set speech speed (0.25-4.0 for OpenAI)
- `format(string $format): self` - Set audio format
- `withProviderOptions(array $options): self` - Set provider-specific options
- `speak(string $text, array $options = []): array` - Convert text to speech
- `transcribe(Audio|string $audio, array $options = []): array` - Transcribe audio

**Provider Options (OpenAI TTS):**
- `speed` - Speech speed (0.25 to 4.0, default 1.0)

**Provider Options (OpenAI Transcription):**
- `language` - Language code (e.g., 'en', 'es', 'fr')
- `prompt` - Context or vocabulary hints
- `temperature` - Sampling temperature (0-1)

---

## PrismBuilder

Internal service for building Prism requests. Used by capability services.

```php
$builder = app(PrismBuilder::class);

// Embeddings (with options)
$request = $builder->forEmbeddings('openai', 'text-embedding-3-small', 'text', [
    'dimensions' => 256,
]);

// Images (with provider options)
$request = $builder->forImage('openai', 'dall-e-3', 'A sunset', [
    'style' => 'vivid',
]);

// Speech (with voice and provider options)
$request = $builder->forSpeech('openai', 'tts-1', 'Hello', [
    'voice' => 'nova',
    'speed' => 1.25,
]);

// Transcription (with provider options)
$request = $builder->forTranscription('openai', 'whisper-1', $audio, [
    'language' => 'en',
]);
```

All provider-specific options are passed through via Prism's `withProviderOptions()` method.

---

## Usage Extraction

### UsageExtractorContract

Contract for normalizing usage data from provider responses.

```php
interface UsageExtractorContract
{
    public function extract(mixed $response): array;
    public function provider(): string;
}
```

### UsageExtractorRegistry

Registry for provider-specific usage extractors.

```php
$registry = app(UsageExtractorRegistry::class);

// Register custom extractor
$registry->register($customExtractor);

// Extract usage
$usage = $registry->extract('openai', $response);

// Result structure
[
    'prompt_tokens' => 100,
    'completion_tokens' => 50,
    'total_tokens' => 150,
]
```

---

## Provider Configuration

### ProviderConfigService

Service for accessing provider configuration.

```php
$service = app(ProviderConfigService::class);

$defaultProvider = $service->getDefaultProvider();
$embeddingConfig = $service->getEmbeddingConfig();
$imageConfig = $service->getImageConfig();
$speechConfig = $service->getSpeechConfig();
$hasProvider = $service->hasProvider('openai');
$timeout = $service->getTimeout('openai');
```

---

## Configuration

```php
// config/atlas.php
return [
    'default_provider' => env('ATLAS_DEFAULT_PROVIDER', 'openai'),

    'embedding' => [
        'provider' => env('ATLAS_EMBEDDING_PROVIDER', 'openai'),
        'model' => env('ATLAS_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'dimensions' => (int) env('ATLAS_EMBEDDING_DIMENSIONS', 1536),
        'batch_size' => (int) env('ATLAS_EMBEDDING_BATCH_SIZE', 100),
    ],

    'image' => [
        'default_provider' => env('ATLAS_IMAGE_PROVIDER', 'openai'),
    ],

    'speech' => [
        'default_provider' => env('ATLAS_SPEECH_PROVIDER', 'openai'),
    ],
];
```

---

## Exceptions

### ProviderException

Exception for provider-related errors.

```php
throw ProviderException::unknownProvider('invalid-provider');
throw ProviderException::missingConfiguration('api_key', 'openai');
throw ProviderException::apiError('openai', 'Rate limit exceeded', 429);
```

---

## Usage Examples

### Generating Embeddings for Search

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

// Index documents
$documents = ['Document 1 content', 'Document 2 content'];
$embeddings = Atlas::embedBatch($documents);

// Store embeddings with documents...

// Search with query embedding
$queryEmbedding = Atlas::embed('search query');
// Compare with stored embeddings using cosine similarity...
```

### Generating Images with Different Providers

```php
// OpenAI DALL-E
$result = Atlas::image()
    ->using('openai')
    ->model('dall-e-3')
    ->generate('A cat wearing a hat');

// Access the generated image
$imageUrl = $result['url'];
```

### Text-to-Speech Workflow

```php
// Convert article to audio
$audio = Atlas::speech()
    ->voice('nova')
    ->format('mp3')
    ->speak($articleContent);

// Save to file
file_put_contents('article.mp3', base64_decode($audio['audio']));
```
