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
    public function generate(string $text): array;
    public function generateBatch(array $texts): array;
    public function dimensions(): int;
}
```

### EmbeddingService

Service layer that delegates to the configured provider.

```php
$service = app(EmbeddingService::class);

$embedding = $service->generate('Sample text');
$embeddings = $service->generateBatch(['text 1', 'text 2', 'text 3']);
$dimensions = $service->dimensions();
```

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
- `generate(string $prompt, array $options = []): array` - Generate image

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
- `format(string $format): self` - Set audio format
- `speak(string $text, array $options = []): array` - Convert text to speech
- `transcribe(Audio|string $audio, array $options = []): array` - Transcribe audio

---

## PrismBuilder

Internal service for building Prism requests. Used by capability services.

```php
$builder = app(PrismBuilder::class);

// Embeddings
$request = $builder->forEmbeddings('openai', 'text-embedding-3-small', 'text');

// Images
$request = $builder->forImage('openai', 'dall-e-3', 'A sunset');

// Speech
$request = $builder->forSpeech('openai', 'tts-1', 'Hello');

// Transcription
$request = $builder->forTranscription('openai', 'whisper-1', $audio);
```

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
