# Phase 1: Foundation & Providers

> **Status:** ✅ COMPLETE (2026-01-21)
>
> **Purpose:** Establish project infrastructure and implement the Foundation and Providers modules.
>
> **Prerequisites:** None (this is the first phase)
>
> **Deliverables:** Complete Foundation module, Providers module, project tooling, and configuration.
>
> **Results:** 86 tests passing, 161 assertions, PHPStan level 6, Pint formatting

---

## Overview

Phase 1 establishes the foundational infrastructure for the Atlas package:

1. **Project Setup** - Laravel package structure, dependencies, quality tooling
2. **Foundation Module** - Pipelines, extension registries, base contracts
3. **Providers Module** - Prism integration, embeddings, image, speech services
4. **Configuration** - Publishable config file with sensible defaults

All code must be stateless. Atlas holds no state between calls.

---

## 1. Project Setup

### 1.1 Composer Configuration

**File:** `composer.json`

```json
{
    "name": "atlasphp/atlas",
    "description": "Stateless AI agent execution framework for Laravel",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.2",
        "illuminate/contracts": "^11.0|^12.0",
        "illuminate/support": "^11.0|^12.0",
        "prism-php/prism": "^0.99"
    },
    "require-dev": {
        "laravel/pint": "^1.18",
        "larastan/larastan": "^3.0",
        "orchestra/testbench": "^9.0|^10.0",
        "pestphp/pest": "^3.0"
    },
    "autoload": {
        "psr-4": {
            "Atlasphp\\Atlas\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Atlasphp\\Atlas\\Tests\\": "tests/"
        }
    },
    "scripts": {
        "lint": "pint",
        "lint:test": "pint --test",
        "analyse": "phpstan analyse",
        "test": "pest",
        "check": [
            "@lint:test",
            "@analyse",
            "@test"
        ]
    },
    "extra": {
        "laravel": {
            "providers": [
                "Atlasphp\\Atlas\\Foundation\\AtlasServiceProvider"
            ]
        }
    },
    "config": {
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

### 1.2 Quality Tooling

**File:** `pint.json`

```json
{
    "preset": "laravel"
}
```

**File:** `phpstan.neon`

```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    paths:
        - src

    level: 6

    checkMissingIterableValueType: false
```

**File:** `phpunit.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         cacheDirectory=".phpunit.cache"
>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

**File:** `tests/Pest.php`

```php
<?php

declare(strict_types=1);

use Atlasphp\Atlas\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');
```

**File:** `tests/TestCase.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tests;

use Atlasphp\Atlas\Foundation\AtlasServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            AtlasServiceProvider::class,
        ];
    }
}
```

---

## 2. Foundation Module

**Namespace:** `Atlasphp\Atlas\Foundation`

The Foundation module provides base infrastructure for pipelines and extension registries.

### 2.1 Directory Structure

```
src/Foundation/
├── AtlasServiceProvider.php
├── Contracts/
│   ├── ExtensionResolverContract.php
│   └── PipelineContract.php
├── Exceptions/
│   └── AtlasException.php
└── Services/
    ├── AbstractExtensionRegistry.php
    ├── PipelineRegistry.php
    └── PipelineRunner.php
```

### 2.2 Contracts

**File:** `src/Foundation/Contracts/PipelineContract.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Foundation\Contracts;

use Closure;

/**
 * Contract for pipeline middleware handlers.
 *
 * Pipelines process data through a series of handlers, each receiving
 * the data and a closure to pass control to the next handler.
 */
interface PipelineContract
{
    /**
     * Handle the pipeline data.
     *
     * @param  mixed  $data  The data being processed through the pipeline
     * @param  Closure  $next  The next handler in the pipeline chain
     * @return mixed The processed data
     */
    public function handle(mixed $data, Closure $next): mixed;
}
```

**File:** `src/Foundation/Contracts/ExtensionResolverContract.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Foundation\Contracts;

/**
 * Contract for extension resolvers.
 *
 * Extension resolvers provide a mechanism for lazy-loading or
 * configuring extension implementations within registries.
 */
interface ExtensionResolverContract
{
    /**
     * Get the unique key identifying this extension.
     */
    public function key(): string;

    /**
     * Resolve and return the extension implementation.
     */
    public function resolve(): mixed;

    /**
     * Check if this resolver supports the given key.
     */
    public function supports(string $key): bool;
}
```

### 2.3 Exceptions

**File:** `src/Foundation/Exceptions/AtlasException.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Foundation\Exceptions;

use Exception;

/**
 * Base exception class for all Atlas exceptions.
 */
class AtlasException extends Exception
{
    /**
     * Create exception for duplicate registration.
     */
    public static function duplicateRegistration(string $key, string $type = 'extension'): self
    {
        return new self("The {$type} '{$key}' is already registered.");
    }

    /**
     * Create exception for missing registration.
     */
    public static function notFound(string $key, string $type = 'extension'): self
    {
        return new self("The {$type} '{$key}' was not found.");
    }

    /**
     * Create exception for invalid configuration.
     */
    public static function invalidConfiguration(string $message): self
    {
        return new self("Invalid configuration: {$message}");
    }
}
```

### 2.4 Services

**File:** `src/Foundation/Services/PipelineRegistry.php`

**Purpose:** Manages pipeline definitions and handler registrations with priority-based ordering.

**Methods:**
- `define(string $name, string $description = '', bool $active = true): static` - Define a pipeline with metadata
- `register(string $name, string|PipelineContract $handler, int $priority = 0): static` - Register a handler with priority
- `get(string $name): array` - Get handlers sorted by priority (highest first)
- `has(string $name): bool` - Check if pipeline has handlers
- `definitions(): array` - Get all pipeline definitions with metadata
- `active(string $name): bool` - Check if pipeline is active
- `setActive(string $name, bool $active): static` - Set pipeline active status
- `pipelines(): array` - Get all pipeline names

**Implementation Notes:**
- Handlers stored as `array<string, array<int, array{handler: string|PipelineContract, priority: int}>>`
- Definitions stored as `array<string, array{description: string, active: bool}>`
- Handlers sorted by priority descending when retrieved
- Pipelines without explicit definition default to active
- Fluent interface (returns `static`)

**Reference:** `nexus/src/Foundation/Services/PipelineRegistry.php`

---

**File:** `src/Foundation/Services/PipelineRunner.php`

**Purpose:** Executes registered pipeline handlers in priority order.

**Constructor Dependencies:**
- `PipelineRegistry $registry`
- `Illuminate\Contracts\Container\Container $container`

**Methods:**
- `run(string $name, mixed $data, ?Closure $destination = null): mixed` - Execute pipeline
- `runIfActive(string $name, mixed $data, ?Closure $destination = null): mixed` - Execute only if active

**Implementation Notes:**
- Uses `array_reduce()` to build closure chain from handlers
- Handlers reversed before reduction to maintain priority order
- Resolves handler class strings via container
- Returns data unchanged if pipeline is inactive or has no handlers
- Optional destination closure called as final handler

**Reference:** `nexus/src/Foundation/Services/PipelineRunner.php`

---

**File:** `src/Foundation/Services/AbstractExtensionRegistry.php`

**Purpose:** Base class for registries managing named extensions.

**Methods:**
- `register(ExtensionResolverContract $resolver): static` - Register an extension
- `get(string $key): mixed` - Get a resolved extension
- `supports(string $key): bool` - Check if extension exists
- `registered(): array` - Get all registered keys
- `hasResolvers(): bool` - Check if any resolvers registered
- `count(): int` - Get resolver count

**Implementation Notes:**
- Throws `AtlasException` on duplicate registration
- Throws `AtlasException` when key not found
- Stores resolvers keyed by string identifier
- Fluent interface pattern

**Reference:** `nexus/src/Foundation/Services/AbstractExtensionRegistry.php`

---

### 2.5 Service Provider

**File:** `src/Foundation/AtlasServiceProvider.php`

**Purpose:** Register all Atlas services, bind singletons, publish config, define core pipelines.

**register() Method:**
```php
// Bind singletons
$this->app->singleton(PipelineRegistry::class);
$this->app->singleton(PipelineRunner::class);

// Phase 1: Provider services
$this->app->singleton(ProviderConfigService::class);
$this->app->singleton(PrismBuilder::class);
$this->app->singleton(EmbeddingService::class);
$this->app->singleton(ImageService::class);
$this->app->singleton(SpeechService::class);
$this->app->singleton(UsageExtractorRegistry::class);
$this->app->singleton(AtlasManager::class);

// Bind contracts
$this->app->bind(EmbeddingProviderContract::class, PrismEmbeddingProvider::class);
```

**boot() Method:**
```php
// Publish config
$this->publishes([
    __DIR__.'/../../config/atlas.php' => config_path('atlas.php'),
], 'atlas-config');

// Merge config
$this->mergeConfigFrom(__DIR__.'/../../config/atlas.php', 'atlas');

// Define core pipelines
$this->defineCorePipelines();
```

**defineCorePipelines() Method:**
```php
$registry = $this->app->make(PipelineRegistry::class);

$registry->define('agent.before_execute', 'Before agent execution');
$registry->define('agent.after_execute', 'After agent execution');
$registry->define('agent.system_prompt.before_build', 'Before building system prompt');
$registry->define('agent.system_prompt.after_build', 'After building system prompt');
$registry->define('tool.before_execute', 'Before tool execution');
$registry->define('tool.after_execute', 'After tool execution');
```

---

## 3. Providers Module

**Namespace:** `Atlasphp\Atlas\Providers`

The Providers module handles LLM integration via Prism PHP.

### 3.1 Directory Structure

```
src/Providers/
├── Contracts/
│   ├── EmbeddingProviderContract.php
│   └── UsageExtractorContract.php
├── Embedding/
│   └── PrismEmbeddingProvider.php
├── Exceptions/
│   └── ProviderException.php
├── Facades/
│   └── Atlas.php
├── Services/
│   ├── AtlasManager.php
│   ├── EmbeddingService.php
│   ├── ImageService.php
│   ├── PrismBuilder.php
│   ├── ProviderConfigService.php
│   ├── SpeechService.php
│   └── UsageExtractorRegistry.php
└── Support/
    ├── DefaultUsageExtractor.php
    └── MessageContextBuilder.php
```

### 3.2 Contracts

**File:** `src/Providers/Contracts/EmbeddingProviderContract.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Contracts;

/**
 * Contract for embedding providers.
 */
interface EmbeddingProviderContract
{
    /**
     * Generate an embedding for a single text.
     *
     * @return array<int, int|string|float> The embedding vector
     */
    public function generate(string $text): array;

    /**
     * Generate embeddings for multiple texts.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, int|string|float>> Array of embedding vectors
     */
    public function generateBatch(array $texts): array;

    /**
     * Get the dimensions of the embedding vectors.
     */
    public function dimensions(): int;
}
```

**File:** `src/Providers/Contracts/UsageExtractorContract.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Contracts;

/**
 * Contract for extracting usage data from LLM responses.
 */
interface UsageExtractorContract
{
    /**
     * Extract usage data from a response.
     *
     * @return array{input: int, output: int, cache_read: int, cache_write: int, thinking: int}
     */
    public function extract(mixed $response): array;

    /**
     * Get the provider this extractor handles.
     *
     * @return string|null Null for default/fallback extractor
     */
    public function provider(): ?string;
}
```

### 3.3 Exceptions

**File:** `src/Providers/Exceptions/ProviderException.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Exceptions;

use Atlasphp\Atlas\Foundation\Exceptions\AtlasException;

/**
 * Exception for provider-related errors.
 */
class ProviderException extends AtlasException
{
    /**
     * Create exception for unknown provider.
     */
    public static function unknownProvider(string $provider): self
    {
        return new self("Unknown provider: {$provider}");
    }

    /**
     * Create exception for missing configuration.
     */
    public static function missingConfiguration(string $key): self
    {
        return new self("Missing required configuration: {$key}");
    }

    /**
     * Create exception for API errors.
     */
    public static function apiError(string $provider, string $message): self
    {
        return new self("API error from {$provider}: {$message}");
    }
}
```

### 3.4 Services

**File:** `src/Providers/Services/ProviderConfigService.php`

**Purpose:** Access provider configuration with validation.

**Constructor Dependencies:**
- `Illuminate\Contracts\Config\Repository $config`

**Methods:**
- `getConfig(string $provider): array` - Get provider configuration
- `hasProvider(string $provider): bool` - Check if provider configured
- `getTimeout(string $provider): int` - Get request timeout
- `getDefaultProvider(): string` - Get default provider name
- `getEmbeddingConfig(): array` - Get embedding configuration
- `getImageConfig(): array` - Get image configuration
- `getSpeechConfig(): array` - Get speech configuration

**Reference:** Extract patterns from Nexus configuration handling

---

**File:** `src/Providers/Services/PrismBuilder.php`

**Purpose:** Internal service for building Prism requests across all modalities.

**Constructor Dependencies:**
- `ProviderConfigService $configService`

**Methods:**
- `forPrompt(AgentContract $agent, string $prompt, ?string $systemPrompt = null, array $tools = []): TextPendingRequest`
- `forMessages(AgentContract $agent, array $messages, ?string $systemPrompt = null, array $tools = []): TextPendingRequest`
- `forStructured(AgentContract $agent, Schema $schema, string $prompt, ?string $systemPrompt = null): StructuredPendingRequest`
- `forEmbeddings(string $provider, string $model, string|array $input): EmbeddingPendingRequest`
- `forImage(string $provider, string $model, string $prompt, array $options = []): ImagePendingRequest`
- `forSpeech(string $provider, string $model, string $text, array $options = []): AudioPendingRequest`
- `forTranscription(string $provider, string $model, Audio $audio, array $options = []): AudioPendingRequest`

**Implementation Notes:**
- Extract agent configuration (provider, model, temperature, maxTokens, maxSteps)
- Configure Prism requests based on agent settings
- Handle message role conversion (user/assistant format)
- Map provider tools to Prism's `ProviderTool` objects
- Use Prism's `Provider::from()` for provider name mapping

**Reference:** `nexus/src/Providers/Services/PrismBuilder.php`

---

**File:** `src/Providers/Services/EmbeddingService.php`

**Purpose:** Service layer for text embeddings.

**Constructor Dependencies:**
- `EmbeddingProviderContract $provider`

**Methods:**
- `generate(string $text): array` - Generate single embedding
- `generateBatch(array $texts): array` - Generate batch embeddings
- `dimensions(): int` - Get vector dimensions

**Implementation:** Simple facade delegating to provider contract.

**Reference:** `nexus/src/Providers/Services/EmbeddingService.php`

---

**File:** `src/Providers/Services/ImageService.php`

**Purpose:** Fluent builder for image generation.

**Constructor Dependencies:**
- `PrismBuilder $prismBuilder`

**Properties (private, with defaults):**
- `string $provider = 'openai'`
- `string $model = 'dall-e-3'`
- `?string $size = null`
- `?string $quality = null`

**Methods:**
- `using(string $provider): self` - Set provider (returns clone)
- `model(string $model): self` - Set model (returns clone)
- `size(string $size): self` - Set size (returns clone)
- `quality(string $quality): self` - Set quality (returns clone)
- `generate(string $prompt, array $options = []): array` - Generate image

**Implementation Notes:**
- Clone pattern for immutability
- Fluent API for method chaining
- Options parameter for additional settings
- Returns `array{url?: string, base64?: string, revised_prompt?: string}`

**Reference:** `nexus/src/Providers/Services/ImageService.php`

---

**File:** `src/Providers/Services/SpeechService.php`

**Purpose:** Fluent builder for text-to-speech and speech-to-text.

**Constructor Dependencies:**
- `PrismBuilder $prismBuilder`

**Properties (private, with defaults):**
- `string $provider = 'openai'`
- `string $ttsModel = 'tts-1'`
- `string $sttModel = 'whisper-1'`
- `?string $voice = null`
- `string $format = 'mp3'`

**Methods:**
- `using(string $provider): self` - Set provider (returns clone)
- `model(string $model): self` - Set TTS model (returns clone)
- `transcriptionModel(string $model): self` - Set STT model (returns clone)
- `voice(string $voice): self` - Set voice (returns clone)
- `format(string $format): self` - Set audio format (returns clone)
- `speak(string $text, array $options = []): array` - Text-to-speech
- `transcribe(Audio|string $audio, array $options = []): array` - Speech-to-text

**Implementation Notes:**
- Clone pattern for immutability
- Accepts file path or Audio object for transcription
- `speak()` returns `array{audio: string, format: string}`
- `transcribe()` returns `array{text: string, language: ?string, duration: ?float}`

**Reference:** `nexus/src/Providers/Services/SpeechService.php`

---

**File:** `src/Providers/Services/UsageExtractorRegistry.php`

**Purpose:** Registry for usage extractors by provider.

**Methods:**
- `register(UsageExtractorContract $extractor): static` - Register an extractor
- `forProvider(?string $provider): UsageExtractorContract` - Get extractor for provider
- `extract(mixed $response, ?string $provider = null): array` - Extract usage from response

**Implementation Notes:**
- Falls back to default extractor when provider-specific not found
- Registers `DefaultUsageExtractor` in constructor

**Reference:** `nexus/src/Providers/Services/UsageExtractorRegistry.php`

---

**File:** `src/Providers/Services/AtlasManager.php`

**Purpose:** Core manager service (partial in Phase 1 - embedding/image/speech only).

**Constructor Dependencies:**
- `EmbeddingService $embeddingService`
- `ImageService $imageService`
- `SpeechService $speechService`

**Methods (Phase 1):**
- `embed(string $text): array` - Generate single embedding
- `embedBatch(array $texts): array` - Generate batch embeddings
- `embeddingDimensions(): int` - Get embedding dimensions
- `image(?string $provider = null): ImageService` - Get image service
- `speech(?string $provider = null): SpeechService` - Get speech service

**Note:** Chat methods added in Phase 3.

---

### 3.5 Embedding Implementation

**File:** `src/Providers/Embedding/PrismEmbeddingProvider.php`

**Purpose:** Implements EmbeddingProviderContract using Prism.

**Constructor Dependencies:**
- `PrismBuilder $prismBuilder`
- `Illuminate\Contracts\Config\Repository $config`

**Implementation:**
- Reads provider/model/dimensions from `config('atlas.embedding')`
- Uses `PrismBuilder::forEmbeddings()` for API calls
- Handles batch size limits from config

**Reference:** `nexus/src/Providers/Embedding/PrismEmbeddingProvider.php`

---

### 3.6 Support Classes

**File:** `src/Providers/Support/DefaultUsageExtractor.php`

**Purpose:** Default implementation of usage extraction.

**Methods:**
- `extract(mixed $response): array` - Returns standard usage array
- `provider(): ?string` - Returns null (default extractor)

**Implementation:**
- Returns `['input' => 0, 'output' => 0, 'cache_read' => 0, 'cache_write' => 0, 'thinking' => 0]`
- Attempts to extract from Prism response `usage` property if available

---

**File:** `src/Providers/Support/MessageContextBuilder.php`

**Purpose:** Fluent builder for conversation context (stub in Phase 1).

**Note:** Full implementation in Phase 3. Phase 1 creates the class structure only.

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Support;

/**
 * Fluent builder for conversation context.
 *
 * Full implementation in Phase 3.
 */
final class MessageContextBuilder
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function __construct(
        private readonly array $messages = [],
        private array $variables = [],
        private array $metadata = [],
    ) {}

    public function withVariables(array $variables): self
    {
        $clone = clone $this;
        $clone->variables = array_merge($this->variables, $variables);

        return $clone;
    }

    public function withMetadata(array $metadata): self
    {
        $clone = clone $this;
        $clone->metadata = array_merge($this->metadata, $metadata);

        return $clone;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
```

---

### 3.7 Facade

**File:** `src/Providers/Facades/Atlas.php`

**Purpose:** Laravel facade for AtlasManager.

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Facades;

use Atlasphp\Atlas\Providers\Services\AtlasManager;
use Illuminate\Support\Facades\Facade;

/**
 * Atlas facade for stateless AI agent execution.
 *
 * @method static array embed(string $text)
 * @method static array embedBatch(array $texts)
 * @method static int embeddingDimensions()
 * @method static \Atlasphp\Atlas\Providers\Services\ImageService image(?string $provider = null)
 * @method static \Atlasphp\Atlas\Providers\Services\SpeechService speech(?string $provider = null)
 *
 * @see \Atlasphp\Atlas\Providers\Services\AtlasManager
 */
class Atlas extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AtlasManager::class;
    }
}
```

---

## 4. Configuration

**File:** `config/atlas.php`

```php
<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default LLM Provider
    |--------------------------------------------------------------------------
    |
    | The default provider to use for agent execution when not specified.
    |
    */
    'default_provider' => env('ATLAS_DEFAULT_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Embedding Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for text embedding generation.
    |
    */
    'embedding' => [
        'provider' => env('ATLAS_EMBEDDING_PROVIDER', 'openai'),
        'model' => env('ATLAS_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'dimensions' => (int) env('ATLAS_EMBEDDING_DIMENSIONS', 1536),
        'batch_size' => (int) env('ATLAS_EMBEDDING_BATCH_SIZE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Generation Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AI image generation.
    |
    */
    'image' => [
        'default_provider' => env('ATLAS_IMAGE_PROVIDER', 'openai'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Speech Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for text-to-speech and speech-to-text services.
    |
    */
    'speech' => [
        'default_provider' => env('ATLAS_SPEECH_PROVIDER', 'openai'),
    ],
];
```

---

## 5. Tests Required

### 5.1 Unit Tests

**Foundation Module:**

```
tests/Unit/Foundation/
├── PipelineRegistryTest.php
│   ├── it registers handlers with priority
│   ├── it returns handlers sorted by priority descending
│   ├── it defines pipelines with metadata
│   ├── it checks pipeline active status
│   ├── it sets pipeline active status
│   └── it returns all pipeline definitions
│
├── PipelineRunnerTest.php
│   ├── it runs pipeline with single handler
│   ├── it runs pipeline with multiple handlers in priority order
│   ├── it passes data through handler chain
│   ├── it resolves handler class strings from container
│   ├── it returns data unchanged when pipeline inactive
│   ├── it returns data unchanged when no handlers
│   └── it calls destination closure as final handler
│
└── AbstractExtensionRegistryTest.php
    ├── it registers extensions
    ├── it throws on duplicate registration
    ├── it retrieves registered extensions
    ├── it throws when extension not found
    ├── it checks if extension exists
    └── it returns all registered keys
```

**Providers Module:**

```
tests/Unit/Providers/
├── PrismBuilderTest.php
│   ├── it builds text request from agent
│   ├── it builds message request with history
│   ├── it builds structured request with schema
│   ├── it builds embedding request
│   ├── it builds image request
│   ├── it builds speech request
│   └── it builds transcription request
│
├── EmbeddingServiceTest.php
│   ├── it generates single embedding
│   ├── it generates batch embeddings
│   └── it returns dimensions
│
├── ImageServiceTest.php
│   ├── it generates image with default settings
│   ├── it uses fluent configuration
│   ├── it returns cloned instance for immutability
│   └── it merges options with fluent settings
│
├── SpeechServiceTest.php
│   ├── it generates speech from text
│   ├── it transcribes audio
│   ├── it uses fluent configuration
│   └── it accepts file path for transcription
│
├── ProviderConfigServiceTest.php
│   ├── it returns provider configuration
│   ├── it checks provider existence
│   ├── it returns default provider
│   └── it returns embedding configuration
│
└── UsageExtractorRegistryTest.php
    ├── it registers extractors
    ├── it returns extractor for provider
    ├── it falls back to default extractor
    └── it extracts usage from response
```

### 5.2 Feature Tests

```
tests/Feature/
├── ServiceProviderTest.php
│   ├── it registers foundation services
│   ├── it registers provider services
│   ├── it binds contracts to implementations
│   ├── it defines core pipelines
│   └── it publishes configuration
│
└── EmbeddingIntegrationTest.php
    ├── it generates embedding via facade (mocked Prism)
    ├── it generates batch embeddings (mocked Prism)
    └── it returns configured dimensions
```

### 5.3 Test Patterns

All tests should:
- Use Pest syntax
- Mock Prism for API calls (no real API calls)
- Use `Orchestra\Testbench` for Laravel integration
- Follow Arrange-Act-Assert pattern
- Test both success and error paths

---

## 6. Documentation Required

### 6.1 SPEC Documents

**File:** `docs/spec/SPEC-Foundation.md`

Contents:
- Pipeline system overview
- PipelineContract interface
- PipelineRegistry methods and usage
- PipelineRunner execution flow
- Extension registry system
- AbstractExtensionRegistry base class
- AtlasServiceProvider responsibilities

**File:** `docs/spec/SPEC-Providers.md`

Contents:
- PrismBuilder service (all modalities)
- EmbeddingProviderContract and implementation
- EmbeddingService API
- ImageService fluent API
- SpeechService fluent API
- UsageExtractorContract and registry
- ProviderConfigService
- AtlasManager (Phase 1 methods)

---

## 7. Reference Files (Nexus)

Extract patterns and implementations from:

| Atlas File | Nexus Reference |
|------------|-----------------|
| `PipelineContract.php` | `nexus/src/Foundation/Contracts/PipelineContract.php` |
| `PipelineRegistry.php` | `nexus/src/Foundation/Services/PipelineRegistry.php` |
| `PipelineRunner.php` | `nexus/src/Foundation/Services/PipelineRunner.php` |
| `AbstractExtensionRegistry.php` | `nexus/src/Foundation/Services/AbstractExtensionRegistry.php` |
| `ExtensionResolverContract.php` | `nexus/src/Foundation/Contracts/ExtensionResolverContract.php` |
| `PrismBuilder.php` | `nexus/src/Providers/Services/PrismBuilder.php` |
| `EmbeddingService.php` | `nexus/src/Providers/Services/EmbeddingService.php` |
| `ImageService.php` | `nexus/src/Providers/Services/ImageService.php` |
| `SpeechService.php` | `nexus/src/Providers/Services/SpeechService.php` |
| `PrismEmbeddingProvider.php` | `nexus/src/Providers/Embedding/PrismEmbeddingProvider.php` |

**Note:** When extracting from Nexus:
- Remove any database/model dependencies (Atlas is stateless)
- Remove user/session/thread references
- Remove ProcessStep recording
- Keep only pure execution logic

---

## 8. Acceptance Criteria

> **Status: COMPLETE** - All criteria verified on 2026-01-21

### 8.1 Infrastructure

- [x] `composer install` completes without errors
- [x] `composer check` passes (lint, analyse, test) - 86 tests, 161 assertions
- [x] Package auto-discovery works (AtlasServiceProvider registered)
- [x] Config publishes to `config/atlas.php`

### 8.2 Foundation Module

- [x] `PipelineRegistry` registers handlers with priority
- [x] `PipelineRunner` executes handlers in correct order
- [x] `AbstractExtensionRegistry` provides base extension management
- [x] Core pipelines defined at boot (6 pipelines: agent.before_execute, agent.after_execute, agent.system_prompt.before_build, agent.system_prompt.after_build, tool.before_execute, tool.after_execute)
- [x] All contracts have implementations

### 8.3 Providers Module

- [x] `PrismBuilder` creates Prism requests for all modalities (embeddings, image, speech, transcription)
- [x] `EmbeddingService` generates embeddings (via mock)
- [x] `ImageService` fluent API works correctly (using/model/size/quality/generate)
- [x] `SpeechService` fluent API works correctly (using/model/transcriptionModel/voice/format/speak/transcribe)
- [x] `UsageExtractorRegistry` extracts usage data
- [x] Atlas facade accessible

### 8.4 Code Quality

- [x] All classes have PHPDoc blocks
- [x] All exceptions have static factory methods (AtlasException, ProviderException)
- [x] No direct database access
- [x] No user/session/state management
- [x] Strict types declared in all files
- [x] PSR-12 compliant (Pint passes)
- [x] PHPStan level 6 passes

### 8.5 Tests

- [x] Unit tests for all services (9 test files covering all Foundation and Provider services)
- [x] Feature tests for integration (ServiceProviderTest, EmbeddingIntegrationTest)
- [x] No real API calls (all mocked via Mockery and Prism facade mocking)
- [x] 100% of acceptance criteria verified by tests

### 8.6 Additional Implementations (Beyond Original Plan)

- [x] `PrismBuilderContract` interface added for improved testability

### 8.7 Implementation Notes

**Deviations from original plan:**

1. **phpstan.neon** - Removed `checkMissingIterableValueType: false` option as it's invalid in current PHPStan versions
2. **PrismBuilderContract** - Added new contract interface to allow mocking PrismBuilder in unit tests (PHP's strict return type enforcement required this)
3. **Config structure** - Added `providers` section and `chat` config; removed `default_provider` in favor of per-modality provider/model settings
4. **TestCase** - Includes PrismServiceProvider in loaded providers for facade mocking support

---

## 9. File Checklist

Phase 1 creates these files:

```
atlas/
├── composer.json
├── pint.json
├── phpstan.neon
├── phpunit.xml
├── config/
│   └── atlas.php
├── src/
│   ├── Foundation/
│   │   ├── AtlasServiceProvider.php
│   │   ├── Contracts/
│   │   │   ├── ExtensionResolverContract.php
│   │   │   └── PipelineContract.php
│   │   ├── Exceptions/
│   │   │   └── AtlasException.php
│   │   └── Services/
│   │       ├── AbstractExtensionRegistry.php
│   │       ├── PipelineRegistry.php
│   │       └── PipelineRunner.php
│   └── Providers/
│       ├── Contracts/
│       │   ├── EmbeddingProviderContract.php
│       │   ├── PrismBuilderContract.php          # Added for testability
│       │   └── UsageExtractorContract.php
│       ├── Embedding/
│       │   └── PrismEmbeddingProvider.php
│       ├── Exceptions/
│       │   └── ProviderException.php
│       ├── Facades/
│       │   └── Atlas.php
│       ├── Services/
│       │   ├── AtlasManager.php
│       │   ├── EmbeddingService.php
│       │   ├── ImageService.php
│       │   ├── PrismBuilder.php
│       │   ├── ProviderConfigService.php
│       │   ├── SpeechService.php
│       │   └── UsageExtractorRegistry.php
│       └── Support/
│           ├── DefaultUsageExtractor.php
│           └── MessageContextBuilder.php
├── tests/
│   ├── Pest.php
│   ├── TestCase.php
│   ├── Unit/
│   │   ├── Foundation/
│   │   │   ├── PipelineRegistryTest.php
│   │   │   ├── PipelineRunnerTest.php
│   │   │   └── AbstractExtensionRegistryTest.php
│   │   └── Providers/
│   │       ├── PrismBuilderTest.php
│   │       ├── EmbeddingServiceTest.php
│   │       ├── ImageServiceTest.php
│   │       ├── SpeechServiceTest.php
│   │       ├── ProviderConfigServiceTest.php
│   │       └── UsageExtractorRegistryTest.php
│   └── Feature/
│       ├── ServiceProviderTest.php
│       └── EmbeddingIntegrationTest.php
└── docs/
    └── spec/
        ├── SPEC-Foundation.md
        └── SPEC-Providers.md
```

---

## 10. Implementation Order

Recommended order for implementing Phase 1:

1. **Project Setup**
   - Create `composer.json`
   - Create quality tool configs (`pint.json`, `phpstan.neon`, `phpunit.xml`)
   - Create `tests/Pest.php` and `tests/TestCase.php`
   - Run `composer install`

2. **Foundation Contracts & Exceptions**
   - `PipelineContract.php`
   - `ExtensionResolverContract.php`
   - `AtlasException.php`

3. **Foundation Services**
   - `PipelineRegistry.php` (with tests)
   - `PipelineRunner.php` (with tests)
   - `AbstractExtensionRegistry.php` (with tests)

4. **Provider Contracts & Exceptions**
   - `EmbeddingProviderContract.php`
   - `UsageExtractorContract.php`
   - `ProviderException.php`

5. **Provider Services**
   - `ProviderConfigService.php` (with tests)
   - `PrismBuilder.php` (with tests)
   - `EmbeddingService.php` (with tests)
   - `ImageService.php` (with tests)
   - `SpeechService.php` (with tests)
   - `UsageExtractorRegistry.php` (with tests)

6. **Provider Support & Implementation**
   - `DefaultUsageExtractor.php`
   - `PrismEmbeddingProvider.php`
   - `MessageContextBuilder.php` (stub)
   - `AtlasManager.php` (Phase 1 methods)

7. **Facade & Service Provider**
   - `Atlas.php` facade
   - `AtlasServiceProvider.php`
   - `config/atlas.php`

8. **Feature Tests**
   - `ServiceProviderTest.php`
   - `EmbeddingIntegrationTest.php`

9. **Documentation**
   - `docs/spec/SPEC-Foundation.md`
   - `docs/spec/SPEC-Providers.md`

10. **Final Verification**
    - Run `composer check`
    - Verify all acceptance criteria
