# Phase 3: Atlas Facade & Finalization

> **Purpose:** Complete the consumer-facing API, CI/CD setup, and documentation.
>
> **Prerequisites:** Phase 1 and Phase 2 complete
>
> **Deliverables:** Complete Atlas facade, MessageContextBuilder, CI/CD pipeline, full documentation.

---

## Overview

Phase 3 ties everything together:

1. **Atlas Facade Completion** - Full consumer-facing API
2. **MessageContextBuilder** - Fluent builder for conversations
3. **Service Provider Finalization** - All bindings and configuration
4. **CI/CD Setup** - GitHub Actions workflow
5. **Gap Analysis** - Test coverage verification
6. **Documentation** - Installation guides, usage guides, complete SPEC docs

---

## 1. Atlas Facade Completion

### 1.1 AtlasManager Complete API

**File:** `src/Providers/Services/AtlasManager.php`

Update the AtlasManager (started in Phase 1) to include all chat methods.

**Constructor Dependencies:**
- `AgentResolver $agentResolver`
- `AgentExecutorContract $agentExecutor`
- `EmbeddingService $embeddingService`
- `ImageService $imageService`
- `SpeechService $speechService`

**Complete Methods:**

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Services;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Providers\Support\MessageContextBuilder;
use Prism\Prism\Schema\Schema;

/**
 * Core manager for Atlas operations.
 *
 * Provides the primary API for agent execution, embeddings,
 * image generation, and speech services.
 */
class AtlasManager
{
    public function __construct(
        private readonly AgentResolver $agentResolver,
        private readonly AgentExecutorContract $agentExecutor,
        private readonly EmbeddingService $embeddingService,
        private readonly ImageService $imageService,
        private readonly SpeechService $speechService,
    ) {}

    // =========================================================================
    // Agent Execution
    // =========================================================================

    /**
     * Execute an agent with the given input.
     *
     * @param  string|class-string<AgentContract>|AgentContract  $agent
     * @param  array<int, array{role: string, content: string}>|null  $messages
     */
    public function chat(
        string|AgentContract $agent,
        string $input,
        ?array $messages = null,
        ?Schema $schema = null,
    ): AgentResponse {
        $resolvedAgent = $this->agentResolver->resolve($agent);

        $context = $messages !== null
            ? new ExecutionContext(messages: $messages)
            : null;

        return $this->agentExecutor->execute($resolvedAgent, $input, $context, $schema);
    }

    /**
     * Start a fluent conversation builder with message history.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function forMessages(array $messages): MessageContextBuilder
    {
        return new MessageContextBuilder($this, $messages);
    }

    /**
     * Execute an agent with full context (internal method for MessageContextBuilder).
     *
     * @internal
     */
    public function executeWithContext(
        string|AgentContract $agent,
        string $input,
        ExecutionContext $context,
        ?Schema $schema = null,
    ): AgentResponse {
        $resolvedAgent = $this->agentResolver->resolve($agent);

        return $this->agentExecutor->execute($resolvedAgent, $input, $context, $schema);
    }

    // =========================================================================
    // Embeddings
    // =========================================================================

    /**
     * Generate an embedding for a single text.
     *
     * @return array<int, int|string|float>
     */
    public function embed(string $text): array
    {
        return $this->embeddingService->generate($text);
    }

    /**
     * Generate embeddings for multiple texts.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, int|string|float>>
     */
    public function embedBatch(array $texts): array
    {
        return $this->embeddingService->generateBatch($texts);
    }

    /**
     * Get the configured embedding dimensions.
     */
    public function embeddingDimensions(): int
    {
        return $this->embeddingService->dimensions();
    }

    // =========================================================================
    // Image Generation
    // =========================================================================

    /**
     * Get an image service instance.
     */
    public function image(?string $provider = null): ImageService
    {
        if ($provider !== null) {
            return $this->imageService->using($provider);
        }

        return $this->imageService;
    }

    // =========================================================================
    // Speech Services
    // =========================================================================

    /**
     * Get a speech service instance.
     */
    public function speech(?string $provider = null): SpeechService
    {
        if ($provider !== null) {
            return $this->speechService->using($provider);
        }

        return $this->speechService;
    }
}
```

### 1.2 Update Atlas Facade

**File:** `src/Providers/Facades/Atlas.php`

Update facade with complete method signatures:

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Facades;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Providers\Services\AtlasManager;
use Atlasphp\Atlas\Providers\Services\ImageService;
use Atlasphp\Atlas\Providers\Services\SpeechService;
use Atlasphp\Atlas\Providers\Support\MessageContextBuilder;
use Illuminate\Support\Facades\Facade;
use Prism\Prism\Schema\Schema;

/**
 * Atlas facade for stateless AI agent execution.
 *
 * @method static AgentResponse chat(string|AgentContract $agent, string $input, ?array $messages = null, ?Schema $schema = null)
 * @method static MessageContextBuilder forMessages(array $messages)
 * @method static array embed(string $text)
 * @method static array embedBatch(array $texts)
 * @method static int embeddingDimensions()
 * @method static ImageService image(?string $provider = null)
 * @method static SpeechService speech(?string $provider = null)
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

## 2. MessageContextBuilder

### 2.1 Complete Implementation

**File:** `src/Providers/Support/MessageContextBuilder.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Support;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Providers\Services\AtlasManager;
use Prism\Prism\Schema\Schema;

/**
 * Fluent builder for multi-turn conversation context.
 *
 * Enables chaining of context configuration before agent execution.
 * Each method returns a new instance (immutable pattern).
 */
final class MessageContextBuilder
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $variables
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private readonly AtlasManager $manager,
        private readonly array $messages = [],
        private readonly array $variables = [],
        private readonly array $metadata = [],
    ) {}

    /**
     * Add variables for system prompt interpolation.
     *
     * Variables are merged with existing variables.
     *
     * @param  array<string, mixed>  $variables
     */
    public function withVariables(array $variables): self
    {
        return new self(
            $this->manager,
            $this->messages,
            array_merge($this->variables, $variables),
            $this->metadata,
        );
    }

    /**
     * Add metadata for pipeline middleware.
     *
     * Metadata is merged with existing metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            $this->manager,
            $this->messages,
            $this->variables,
            array_merge($this->metadata, $metadata),
        );
    }

    /**
     * Execute the agent with the configured context.
     *
     * @param  string|class-string<AgentContract>|AgentContract  $agent
     */
    public function chat(
        string|AgentContract $agent,
        string $input,
        ?Schema $schema = null,
    ): AgentResponse {
        $context = new ExecutionContext(
            messages: $this->messages,
            variables: $this->variables,
            metadata: $this->metadata,
        );

        return $this->manager->executeWithContext($agent, $input, $context, $schema);
    }

    /**
     * Get the current messages.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get the current variables.
     *
     * @return array<string, mixed>
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * Get the current metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
```

### 2.2 Usage Examples

```php
// Simple conversation continuation
$response = Atlas::forMessages($messages)->chat('support-agent', 'Follow up');

// With variables for system prompt interpolation
$response = Atlas::forMessages($messages)
    ->withVariables([
        'user_name' => $user->name,
        'account_type' => $user->subscription,
        'timezone' => $user->timezone,
    ])
    ->chat('support-agent', 'What time is it?');

// With metadata for pipeline middleware
$response = Atlas::forMessages($messages)
    ->withVariables(['user_name' => $user->name])
    ->withMetadata(['request_id' => $requestId, 'source' => 'api'])
    ->chat('support-agent', 'Help me');

// With structured output
$response = Atlas::forMessages($messages)
    ->withVariables(['user_name' => $user->name])
    ->chat('extraction-agent', 'Extract the order details', schema: $orderSchema);

// Consumer manages message persistence
$messages[] = ['role' => 'user', 'content' => 'Help me'];
$messages[] = ['role' => 'assistant', 'content' => $response->text];
// Store $messages however you want (Redis, database, session, etc.)
```

---

## 3. Service Provider Finalization

### 3.1 Complete AtlasServiceProvider

**File:** `src/Foundation/AtlasServiceProvider.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Foundation;

use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Agents\Services\AgentExecutor;
use Atlasphp\Atlas\Agents\Services\AgentExtensionRegistry;
use Atlasphp\Atlas\Agents\Services\AgentRegistry;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Agents\Services\SystemPromptBuilder;
use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;
use Atlasphp\Atlas\Foundation\Services\PipelineRunner;
use Atlasphp\Atlas\Providers\Contracts\EmbeddingProviderContract;
use Atlasphp\Atlas\Providers\Embedding\PrismEmbeddingProvider;
use Atlasphp\Atlas\Providers\Services\AtlasManager;
use Atlasphp\Atlas\Providers\Services\EmbeddingService;
use Atlasphp\Atlas\Providers\Services\ImageService;
use Atlasphp\Atlas\Providers\Services\PrismBuilder;
use Atlasphp\Atlas\Providers\Services\ProviderConfigService;
use Atlasphp\Atlas\Providers\Services\SpeechService;
use Atlasphp\Atlas\Providers\Services\UsageExtractorRegistry;
use Atlasphp\Atlas\Tools\Contracts\ToolRegistryContract;
use Atlasphp\Atlas\Tools\Services\ToolBuilder;
use Atlasphp\Atlas\Tools\Services\ToolExecutor;
use Atlasphp\Atlas\Tools\Services\ToolExtensionRegistry;
use Atlasphp\Atlas\Tools\Services\ToolRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Atlas service provider.
 *
 * Registers all Atlas services, binds contracts to implementations,
 * publishes configuration, and defines core pipelines.
 */
class AtlasServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->registerFoundationServices();
        $this->registerProviderServices();
        $this->registerAgentServices();
        $this->registerToolServices();
        $this->registerManager();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishConfiguration();
        $this->defineCorePipelines();
    }

    /**
     * Register Foundation module services.
     */
    protected function registerFoundationServices(): void
    {
        $this->app->singleton(PipelineRegistry::class);
        $this->app->singleton(PipelineRunner::class);
    }

    /**
     * Register Providers module services.
     */
    protected function registerProviderServices(): void
    {
        $this->app->singleton(ProviderConfigService::class);
        $this->app->singleton(PrismBuilder::class);
        $this->app->singleton(UsageExtractorRegistry::class);

        // Embedding
        $this->app->bind(EmbeddingProviderContract::class, PrismEmbeddingProvider::class);
        $this->app->singleton(EmbeddingService::class);

        // Image
        $this->app->singleton(ImageService::class);

        // Speech
        $this->app->singleton(SpeechService::class);
    }

    /**
     * Register Agents module services.
     */
    protected function registerAgentServices(): void
    {
        $this->app->singleton(AgentRegistryContract::class, AgentRegistry::class);
        $this->app->singleton(AgentExecutorContract::class, AgentExecutor::class);
        $this->app->singleton(AgentResolver::class);
        $this->app->singleton(SystemPromptBuilder::class);
        $this->app->singleton(AgentExtensionRegistry::class);
    }

    /**
     * Register Tools module services.
     */
    protected function registerToolServices(): void
    {
        $this->app->singleton(ToolRegistryContract::class, ToolRegistry::class);
        $this->app->singleton(ToolExecutor::class);
        $this->app->singleton(ToolBuilder::class);
        $this->app->singleton(ToolExtensionRegistry::class);
    }

    /**
     * Register the Atlas manager.
     */
    protected function registerManager(): void
    {
        $this->app->singleton(AtlasManager::class);
    }

    /**
     * Publish configuration files.
     */
    protected function publishConfiguration(): void
    {
        $this->publishes([
            __DIR__.'/../../config/atlas.php' => config_path('atlas.php'),
        ], 'atlas-config');

        $this->mergeConfigFrom(
            __DIR__.'/../../config/atlas.php',
            'atlas',
        );
    }

    /**
     * Define core pipelines.
     */
    protected function defineCorePipelines(): void
    {
        /** @var PipelineRegistry $registry */
        $registry = $this->app->make(PipelineRegistry::class);

        // Agent execution pipelines
        $registry->define('agent.before_execute', 'Fires before agent execution');
        $registry->define('agent.after_execute', 'Fires after agent execution');

        // System prompt pipelines
        $registry->define('agent.system_prompt.before_build', 'Fires before building system prompt');
        $registry->define('agent.system_prompt.after_build', 'Fires after building system prompt');

        // Tool execution pipelines
        $registry->define('tool.before_execute', 'Fires before tool execution');
        $registry->define('tool.after_execute', 'Fires after tool execution');
    }
}
```

---

## 4. CI/CD Setup

### 4.1 GitHub Actions Workflow

**File:** `.github/workflows/tests.yml`

```yaml
name: Build

on:
  push:
    branches: [main]
  pull_request:

jobs:
  lint:
    runs-on: ubuntu-latest
    name: Lint

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: dom, curl, mbstring, zip
          coverage: none

      - name: Install dependencies
        run: composer install --prefer-dist --no-interaction --no-progress

      - name: Run Pint
        run: composer lint:test

  analyse:
    runs-on: ubuntu-latest
    name: Static Analysis

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: dom, curl, mbstring, zip
          coverage: none

      - name: Install dependencies
        run: composer install --prefer-dist --no-interaction --no-progress

      - name: Run PHPStan
        run: composer analyse

  tests:
    runs-on: ubuntu-latest
    name: Tests (PHP ${{ matrix.php }})

    strategy:
      fail-fast: true
      matrix:
        php: ['8.2', '8.3', '8.4']

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: dom, curl, mbstring, zip
          coverage: none

      - name: Install dependencies
        run: composer install --prefer-dist --no-interaction --no-progress

      - name: Run tests
        run: composer test
```

**Notes:**
- No PostgreSQL/pgvector service (Atlas is stateless)
- Tests run on PHP 8.2, 8.3, and 8.4
- Three jobs: lint, analyse, tests
- Uses latest GitHub Actions

### 4.2 Additional CI Files

**File:** `.github/workflows/dependabot.yml`

```yaml
version: 2
updates:
  - package-ecosystem: "composer"
    directory: "/"
    schedule:
      interval: "weekly"
```

**File:** `.gitignore`

```gitignore
/vendor/
/node_modules/
.env
.env.*.local
.phpunit.cache/
.phpunit.result.cache
composer.lock
*.log
.DS_Store
```

---

## 5. Gap Analysis

### 5.1 Test Coverage Verification

After Phase 1 and 2, verify coverage for:

**Foundation Module:**
- [ ] PipelineRegistry - all public methods
- [ ] PipelineRunner - run, runIfActive, handler resolution
- [ ] AbstractExtensionRegistry - all public methods
- [ ] AtlasException - all factory methods

**Providers Module:**
- [ ] PrismBuilder - all modalities (prompt, messages, structured, embeddings, image, speech)
- [ ] EmbeddingService - generate, generateBatch, dimensions
- [ ] ImageService - fluent methods, generate
- [ ] SpeechService - fluent methods, speak, transcribe
- [ ] ProviderConfigService - all config retrieval
- [ ] UsageExtractorRegistry - register, forProvider, extract
- [ ] AtlasManager - all public methods
- [ ] MessageContextBuilder - all methods including chat

**Agents Module:**
- [ ] AgentDefinition - contract implementation
- [ ] AgentRegistry - register, registerInstance, get, has, all, override
- [ ] AgentResolver - resolve from key, class, instance
- [ ] AgentExecutor - execute with all parameter combinations
- [ ] SystemPromptBuilder - interpolation, sections, pipelines
- [ ] ExecutionContext - immutable updates
- [ ] AgentResponse - factory methods, queries

**Tools Module:**
- [ ] ToolDefinition - contract implementation, toPrismTool
- [ ] ToolRegistry - all CRUD operations
- [ ] ToolExecutor - execute with pipeline hooks
- [ ] ToolBuilder - buildForAgent
- [ ] ToolParameter - all factory methods, toSchema
- [ ] ToolResult - all factory methods
- [ ] ToolContext - metadata operations

### 5.2 Additional Tests Needed

**File:** `tests/Unit/Providers/AtlasManagerTest.php`

```
├── it executes chat with agent key
├── it executes chat with agent class
├── it executes chat with agent instance
├── it executes chat with messages
├── it executes chat with schema
├── it returns message context builder
├── it generates embedding
├── it generates batch embeddings
├── it returns embedding dimensions
├── it returns image service
├── it returns image service with provider
├── it returns speech service
└── it returns speech service with provider
```

**File:** `tests/Unit/Providers/MessageContextBuilderTest.php`

```
├── it creates with messages
├── it adds variables immutably
├── it adds metadata immutably
├── it chains multiple operations
├── it executes chat with context
├── it executes chat with schema
├── it returns messages
├── it returns variables
└── it returns metadata
```

**File:** `tests/Feature/AtlasFacadeTest.php`

```
├── it executes simple chat
├── it executes chat with forMessages
├── it executes chat with variables
├── it executes chat with metadata
├── it executes structured output
├── it generates embeddings
├── it generates images
└── it handles speech operations
```

### 5.3 Edge Case Tests

Add edge case tests for:

- Empty message arrays
- Empty variable arrays
- Missing agent keys
- Invalid agent classes
- Tool execution errors
- Pipeline handler exceptions
- Schema validation failures

---

## 6. Documentation

### 6.1 Installation Guide

**File:** `docs/guides/Installation.md`

```markdown
# Installation

## Requirements

- PHP 8.2+
- Laravel 11.x or 12.x
- prism-php/prism ^0.99

## Installation

```bash
composer require atlasphp/atlas
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=atlas-config
```

This creates `config/atlas.php` with the following options:

```php
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

## Environment Variables

Add to your `.env` file:

```env
ATLAS_DEFAULT_PROVIDER=openai
ATLAS_EMBEDDING_PROVIDER=openai
ATLAS_EMBEDDING_MODEL=text-embedding-3-small
ATLAS_EMBEDDING_DIMENSIONS=1536
```

## Service Provider

Atlas auto-discovers via Laravel's package discovery. Manual registration:

```php
// config/app.php
'providers' => [
    Atlasphp\Atlas\Foundation\AtlasServiceProvider::class,
],
```

## Quick Start

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

// Simple chat
$response = Atlas::chat('my-agent', 'Hello!');
echo $response->text;

// With conversation history
$response = Atlas::forMessages($messages)
    ->withVariables(['user_name' => 'John'])
    ->chat('my-agent', 'Continue our conversation');
```
```

### 6.2 Creating Agents Guide

**File:** `docs/guides/Creating-Agents.md`

```markdown
# Creating Agents

## Basic Agent

Create an agent by extending `AgentDefinition`:

```php
namespace App\Agents;

use Atlasphp\Atlas\Agents\AgentDefinition;

class SupportAgent extends AgentDefinition
{
    public function key(): string
    {
        return 'support-agent';
    }

    public function name(): string
    {
        return 'Customer Support';
    }

    public function provider(): string
    {
        return 'anthropic';
    }

    public function model(): string
    {
        return 'claude-sonnet-4-20250514';
    }

    public function systemPrompt(): string
    {
        return <<<PROMPT
            You are {agent_name}, a helpful customer support assistant.
            You are helping {user_name}.
            Be professional and helpful.
            PROMPT;
    }
}
```

## Registering Agents

Register agents in a service provider:

```php
use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;

public function boot(AgentRegistryContract $agents): void
{
    $agents->register(SupportAgent::class);
    $agents->register(SalesAgent::class);
}
```

## Agent with Tools

```php
class AnalysisAgent extends AgentDefinition
{
    public function key(): string
    {
        return 'analysis-agent';
    }

    // ... other methods ...

    public function tools(): array
    {
        return [
            CalculatorTool::class,
            WebSearchTool::class,
        ];
    }

    public function maxSteps(): ?int
    {
        return 5; // Limit tool execution loops
    }
}
```

## Agent Settings

Use `settings()` for custom configuration:

```php
public function settings(): array
{
    return [
        'memory' => ['enabled' => true],
        'rate_limit' => 10,
    ];
}
```

These can be read by pipeline middleware or extensions.

## Variable Interpolation

System prompts support `{variable}` interpolation:

```php
public function systemPrompt(): string
{
    return 'You are helping {user_name} ({account_type}).';
}
```

Pass variables when executing:

```php
Atlas::forMessages($messages)
    ->withVariables([
        'user_name' => 'John',
        'account_type' => 'premium',
    ])
    ->chat('support-agent', 'Help me');
```
```

### 6.3 Creating Tools Guide

**File:** `docs/guides/Creating-Tools.md`

```markdown
# Creating Tools

## Basic Tool

Create a tool by extending `ToolDefinition`:

```php
namespace App\Tools;

use Atlasphp\Atlas\Tools\ToolDefinition;
use Atlasphp\Atlas\Tools\Support\ToolContext;
use Atlasphp\Atlas\Tools\Support\ToolParameter;
use Atlasphp\Atlas\Tools\Support\ToolResult;

class CalculatorTool extends ToolDefinition
{
    public function name(): string
    {
        return 'calculator';
    }

    public function description(): string
    {
        return 'Perform basic arithmetic operations';
    }

    public function parameters(): array
    {
        return [
            ToolParameter::enum('operation', 'The operation', ['add', 'subtract', 'multiply', 'divide']),
            ToolParameter::number('a', 'First number'),
            ToolParameter::number('b', 'Second number'),
        ];
    }

    public function handle(array $args, ToolContext $context): ToolResult
    {
        $result = match ($args['operation']) {
            'add' => $args['a'] + $args['b'],
            'subtract' => $args['a'] - $args['b'],
            'multiply' => $args['a'] * $args['b'],
            'divide' => $args['b'] !== 0
                ? $args['a'] / $args['b']
                : 'Error: Division by zero',
        };

        return is_string($result)
            ? ToolResult::error($result)
            : ToolResult::text((string) $result);
    }
}
```

## Registering Tools

Register tools in a service provider:

```php
use Atlasphp\Atlas\Tools\Contracts\ToolRegistryContract;

public function boot(ToolRegistryContract $tools): void
{
    $tools->register(CalculatorTool::class);
    $tools->register(WebSearchTool::class);
}
```

## Parameter Types

```php
// String
ToolParameter::string('name', 'User name');

// Integer
ToolParameter::integer('count', 'Number of items');

// Number (float)
ToolParameter::number('price', 'Item price');

// Boolean
ToolParameter::boolean('active', 'Is active');

// Enum
ToolParameter::enum('status', 'Order status', ['pending', 'shipped', 'delivered']);

// Array
ToolParameter::array('items', 'List of items', ['type' => 'string']);

// Object
ToolParameter::object('address', 'Shipping address', [
    'street' => ToolParameter::string('street', 'Street address'),
    'city' => ToolParameter::string('city', 'City'),
]);
```

## Optional Parameters

```php
ToolParameter::string('note', 'Optional note', required: false, default: '');
```

## Returning Results

```php
// Success text
return ToolResult::text('Operation completed');

// Error
return ToolResult::error('Something went wrong');

// JSON data
return ToolResult::json(['items' => $items, 'total' => $total]);
```

## Using Context

```php
public function handle(array $args, ToolContext $context): ToolResult
{
    // Access consumer-provided metadata
    $userId = $context->getMeta('user_id');
    $permissions = $context->getMeta('permissions', []);

    // ...
}
```
```

### 6.4 Multi-Turn Conversations Guide

**File:** `docs/guides/Multi-Turn-Conversations.md`

```markdown
# Multi-Turn Conversations

Atlas is stateless - it does not store conversation history. You manage message storage.

## Basic Pattern

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

// Your message storage (database, Redis, session, etc.)
$messages = [];

// First turn
$response = Atlas::chat('support-agent', 'Hello, I need help');
$messages[] = ['role' => 'user', 'content' => 'Hello, I need help'];
$messages[] = ['role' => 'assistant', 'content' => $response->text];

// Second turn - pass history
$response = Atlas::forMessages($messages)->chat('support-agent', 'What were we discussing?');
$messages[] = ['role' => 'user', 'content' => 'What were we discussing?'];
$messages[] = ['role' => 'assistant', 'content' => $response->text];

// Store $messages however you want
```

## With Variables

```php
$response = Atlas::forMessages($messages)
    ->withVariables([
        'user_name' => $user->name,
        'account_type' => $user->subscription,
        'timezone' => $user->timezone,
    ])
    ->chat('support-agent', 'Check my account');
```

## With Metadata

Metadata is passed to pipeline middleware:

```php
$response = Atlas::forMessages($messages)
    ->withVariables(['user_name' => 'John'])
    ->withMetadata([
        'request_id' => $requestId,
        'source' => 'api',
        'ip_address' => $request->ip(),
    ])
    ->chat('support-agent', 'Help me');
```

## Direct Messages Parameter

For simpler cases:

```php
$response = Atlas::chat('support-agent', 'Follow up', messages: $messages);
```

## Message Format

Messages must follow this format:

```php
$messages = [
    ['role' => 'user', 'content' => 'First message'],
    ['role' => 'assistant', 'content' => 'First response'],
    ['role' => 'user', 'content' => 'Second message'],
    ['role' => 'assistant', 'content' => 'Second response'],
];
```

## Structured Output in Conversations

```php
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

$schema = new ObjectSchema(
    name: 'summary',
    description: 'Conversation summary',
    properties: [
        new StringSchema('topic', 'Main topic'),
        new StringSchema('outcome', 'Resolution'),
    ],
    requiredFields: ['topic', 'outcome'],
);

$response = Atlas::forMessages($messages)
    ->chat('support-agent', 'Summarize our conversation', schema: $schema);

$summary = $response->structured;
// ['topic' => '...', 'outcome' => '...']
```
```

### 6.5 Extending Atlas Guide

**File:** `docs/guides/Extending-Atlas.md`

```markdown
# Extending Atlas

## Pipeline Middleware

Register handlers for execution hooks:

```php
use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;

public function boot(PipelineRegistry $pipelines): void
{
    // Before agent execution
    $pipelines->register(
        'agent.before_execute',
        InjectMemoriesMiddleware::class,
        priority: 50
    );

    // After agent execution
    $pipelines->register(
        'agent.after_execute',
        LogResponseMiddleware::class,
        priority: 100
    );
}
```

## Creating Pipeline Handlers

```php
use Atlasphp\Atlas\Foundation\Contracts\PipelineContract;
use Closure;

class InjectMemoriesMiddleware implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        // $data contains: agent, context, input
        $agent = $data['agent'];
        $context = $data['context'];

        // Modify context with additional variables
        $data['context'] = $context->withVariables([
            'memories' => $this->getMemories($agent, $context),
        ]);

        return $next($data);
    }
}
```

## Available Pipelines

| Pipeline | Data | Purpose |
|----------|------|---------|
| `agent.before_execute` | agent, context, input | Modify context before execution |
| `agent.after_execute` | agent, context, input, response | Process/log response |
| `agent.system_prompt.before_build` | prompt, agent, context | Modify raw prompt |
| `agent.system_prompt.after_build` | prompt, agent, context | Modify final prompt |
| `tool.before_execute` | tool, args, context | Modify args or context |
| `tool.after_execute` | tool, args, context, result | Process/log result |

## Extension Registries

For agent-specific extensions:

```php
use Atlasphp\Atlas\Agents\Services\AgentExtensionRegistry;
use Atlasphp\Atlas\Foundation\Contracts\ExtensionResolverContract;

class MemoryExtensionResolver implements ExtensionResolverContract
{
    public function key(): string
    {
        return 'memory';
    }

    public function resolve(): mixed
    {
        return new MemoryService();
    }

    public function supports(string $key): bool
    {
        return $key === 'memory';
    }
}

// Register
$extensions->register(new MemoryExtensionResolver());
```

## Custom System Prompt Variables

```php
use Atlasphp\Atlas\Agents\Services\SystemPromptBuilder;

public function boot(SystemPromptBuilder $builder): void
{
    $builder->registerVariable('current_time', function () {
        return now()->toDateTimeString();
    });

    $builder->registerVariable('weather', function ($agent, $context) {
        $city = $context->getVariable('city', 'New York');
        return $this->weatherService->get($city);
    });
}
```

## Custom System Prompt Sections

```php
$builder->addSection('rules', function ($agent, $context) {
    return "Always follow these rules:\n- Be helpful\n- Be concise";
}, priority: 100);

$builder->addSection('context', function ($agent, $context) {
    return "Current context: " . json_encode($context->metadata);
}, priority: 50);
```
```

### 6.6 Complete SPEC-Facade.md

**File:** `docs/spec/SPEC-Facade.md`

```markdown
# Atlas Facade Specification

## Overview

The Atlas facade provides the primary consumer-facing API for the Atlas package.

## Namespace

```php
Atlasphp\Atlas\Providers\Facades\Atlas
```

## Dependencies

- `AtlasManager` - Core manager service
- `AgentResolver` - Resolves agents from keys/classes/instances
- `AgentExecutorContract` - Executes agents
- `EmbeddingService` - Text embeddings
- `ImageService` - Image generation
- `SpeechService` - TTS/STT

## Methods

### Agent Execution

#### `chat(string|AgentContract $agent, string $input, ?array $messages = null, ?Schema $schema = null): AgentResponse`

Execute an agent with the given input.

**Parameters:**
- `$agent` - Agent key, class name, or instance
- `$input` - User input message
- `$messages` - Optional conversation history
- `$schema` - Optional structured output schema

**Returns:** `AgentResponse`

#### `forMessages(array $messages): MessageContextBuilder`

Start a fluent conversation builder.

**Parameters:**
- `$messages` - Array of `{role, content}` messages

**Returns:** `MessageContextBuilder`

### Embeddings

#### `embed(string $text): array`

Generate embedding for single text.

**Returns:** Embedding vector

#### `embedBatch(array $texts): array`

Generate embeddings for multiple texts.

**Returns:** Array of embedding vectors

#### `embeddingDimensions(): int`

Get configured embedding dimensions.

### Image Generation

#### `image(?string $provider = null): ImageService`

Get image service instance.

### Speech Services

#### `speech(?string $provider = null): SpeechService`

Get speech service instance.

## MessageContextBuilder

### Methods

#### `withVariables(array $variables): self`

Add variables for system prompt interpolation.

#### `withMetadata(array $metadata): self`

Add metadata for pipeline middleware.

#### `chat(string|AgentContract $agent, string $input, ?Schema $schema = null): AgentResponse`

Execute with configured context.

## Usage Examples

See [Multi-Turn Conversations Guide](../guides/Multi-Turn-Conversations.md).
```

### 6.7 Documentation Index

**File:** `docs/README.md`

```markdown
# Atlas Documentation

> Stateless AI agent execution framework for Laravel.

## Quick Links

- [Installation](guides/Installation.md)
- [Creating Agents](guides/Creating-Agents.md)
- [Creating Tools](guides/Creating-Tools.md)
- [Multi-Turn Conversations](guides/Multi-Turn-Conversations.md)
- [Extending Atlas](guides/Extending-Atlas.md)

## Specifications

- [Foundation Module](spec/SPEC-Foundation.md)
- [Providers Module](spec/SPEC-Providers.md)
- [Agents Module](spec/SPEC-Agents.md)
- [Tools Module](spec/SPEC-Tools.md)
- [Atlas Facade](spec/SPEC-Facade.md)

## Core Concepts

Atlas is a **stateless** execution engine:

- Takes inputs (agent, message, context)
- Executes against LLMs via Prism PHP
- Returns structured responses
- Stores nothing

All persistence is your responsibility.

## Quick Start

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

// Simple chat
$response = Atlas::chat('my-agent', 'Hello!');

// With conversation history
$response = Atlas::forMessages($messages)
    ->withVariables(['user_name' => 'John'])
    ->chat('my-agent', 'Continue our conversation');

// Embeddings
$embedding = Atlas::embed('Hello world');

// Images
$result = Atlas::image()->generate('A sunset over mountains');

// Speech
$audio = Atlas::speech()->speak('Hello, world!');
```
```

### 6.8 README.md Updates

**File:** `README.md`

```markdown
# Atlas

> Stateless AI agent execution framework for Laravel.

[![Build](https://github.com/atlasphp/atlas/actions/workflows/tests.yml/badge.svg)](https://github.com/atlasphp/atlas/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/atlasphp/atlas.svg)](https://packagist.org/packages/atlasphp/atlas)
[![License](https://img.shields.io/packagist/l/atlasphp/atlas.svg)](https://packagist.org/packages/atlasphp/atlas)

Atlas provides foundational building blocks for AI agent execution. Define agents, register tools, and interact with LLM providers without any state management.

## Installation

```bash
composer require atlasphp/atlas
```

Publish configuration:

```bash
php artisan vendor:publish --tag=atlas-config
```

## Quick Start

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

// Execute an agent
$response = Atlas::chat('support-agent', 'Hello, I need help');
echo $response->text;

// With conversation history
$response = Atlas::forMessages($messages)
    ->withVariables(['user_name' => 'John'])
    ->chat('support-agent', 'Continue our conversation');

// Generate embeddings
$embedding = Atlas::embed('Hello world');

// Generate images
$result = Atlas::image()->generate('A sunset over mountains');
```

## Documentation

Full documentation is available in the [docs](docs/) directory:

- [Installation](docs/guides/Installation.md)
- [Creating Agents](docs/guides/Creating-Agents.md)
- [Creating Tools](docs/guides/Creating-Tools.md)
- [Multi-Turn Conversations](docs/guides/Multi-Turn-Conversations.md)
- [Extending Atlas](docs/guides/Extending-Atlas.md)

## Philosophy

Atlas is a **pure execution engine**:

- **Stateless** - No users, sessions, or history stored
- **Synchronous** - All operations complete in the request lifecycle
- **Provider Agnostic** - Abstracts LLMs through Prism PHP
- **Consumer Controlled** - You manage persistence and context

## Requirements

- PHP 8.2+
- Laravel 11.x or 12.x
- prism-php/prism ^0.99

## License

MIT License. See [LICENSE](LICENSE) for details.
```

---

## 7. Reference Files (Nexus)

For Phase 3, minimal extraction needed:

| Atlas File | Nexus Reference |
|------------|-----------------|
| `AtlasManager.php` | Pattern from Nexus service managers |
| `MessageContextBuilder.php` | New implementation (no direct reference) |

---

## 8. Acceptance Criteria

### 8.1 Atlas Facade

- [ ] `Atlas::chat()` works with key, class, and instance
- [ ] `Atlas::chat()` accepts messages parameter
- [ ] `Atlas::chat()` accepts schema parameter
- [ ] `Atlas::forMessages()` returns MessageContextBuilder
- [ ] `Atlas::embed()` generates single embedding
- [ ] `Atlas::embedBatch()` generates batch embeddings
- [ ] `Atlas::embeddingDimensions()` returns configured dimensions
- [ ] `Atlas::image()` returns ImageService
- [ ] `Atlas::speech()` returns SpeechService

### 8.2 MessageContextBuilder

- [ ] `withVariables()` adds variables immutably
- [ ] `withMetadata()` adds metadata immutably
- [ ] `chat()` executes with full context
- [ ] Method chaining works correctly
- [ ] Immutability maintained

### 8.3 Service Provider

- [ ] All services registered as singletons
- [ ] All contracts bound to implementations
- [ ] Configuration publishes correctly
- [ ] Core pipelines defined

### 8.4 CI/CD

- [ ] GitHub Actions workflow runs
- [ ] Lint job passes
- [ ] Analyse job passes
- [ ] Test job passes on PHP 8.2, 8.3, 8.4

### 8.5 Documentation

- [ ] Installation guide complete
- [ ] Creating Agents guide complete
- [ ] Creating Tools guide complete
- [ ] Multi-Turn Conversations guide complete
- [ ] Extending Atlas guide complete
- [ ] All SPEC documents complete
- [ ] README updated

### 8.6 Code Quality

- [ ] All classes have PHPDoc blocks
- [ ] `composer check` passes
- [ ] No database dependencies
- [ ] Stateless design maintained

---

## 9. File Checklist

Phase 3 creates/updates these files:

```
atlas/
├── .github/
│   └── workflows/
│       └── tests.yml
├── .gitignore
├── README.md
├── src/
│   ├── Foundation/
│   │   └── AtlasServiceProvider.php (update)
│   └── Providers/
│       ├── Facades/
│       │   └── Atlas.php (update)
│       ├── Services/
│       │   └── AtlasManager.php (update)
│       └── Support/
│           └── MessageContextBuilder.php (complete)
├── tests/
│   ├── Unit/
│   │   └── Providers/
│   │       ├── AtlasManagerTest.php
│   │       └── MessageContextBuilderTest.php
│   └── Feature/
│       └── AtlasFacadeTest.php
└── docs/
    ├── README.md
    ├── guides/
    │   ├── Installation.md
    │   ├── Creating-Agents.md
    │   ├── Creating-Tools.md
    │   ├── Multi-Turn-Conversations.md
    │   └── Extending-Atlas.md
    └── spec/
        └── SPEC-Facade.md
```

---

## 10. Implementation Order

Recommended order for implementing Phase 3:

1. **Complete AtlasManager**
   - Add chat methods
   - Add forMessages method
   - Add executeWithContext internal method

2. **Complete MessageContextBuilder**
   - Full implementation with chat method
   - Tests

3. **Update Atlas Facade**
   - Add all method signatures
   - Update PHPDoc

4. **Finalize Service Provider**
   - Verify all bindings
   - Verify all pipelines

5. **AtlasManager Tests**
   - Unit tests for all methods
   - Integration with mocked services

6. **MessageContextBuilder Tests**
   - Immutability tests
   - Chaining tests
   - Execution tests

7. **Facade Integration Tests**
   - End-to-end tests (mocked Prism)

8. **CI/CD Setup**
   - Create GitHub Actions workflow
   - Test locally with `act` if available
   - Push and verify

9. **Documentation**
   - Installation guide
   - Creating Agents guide
   - Creating Tools guide
   - Multi-Turn Conversations guide
   - Extending Atlas guide
   - SPEC-Facade.md
   - docs/README.md
   - Update main README.md

10. **Gap Analysis**
    - Review all test coverage
    - Add missing edge case tests
    - Run full `composer check`

11. **Final Verification**
    - All acceptance criteria met
    - CI pipeline green
    - Documentation accurate

---

## 11. Final Checklist

Before marking Phase 3 complete:

- [ ] `composer install` works
- [ ] `composer check` passes (lint, analyse, test)
- [ ] GitHub Actions CI passes
- [ ] All facade methods work
- [ ] MessageContextBuilder fluent API works
- [ ] All documentation written
- [ ] README reflects current state
- [ ] No TODO comments in code
- [ ] No debug code or var_dump
- [ ] All tests pass
- [ ] PHPStan level 6 clean
- [ ] Pint formatting applied
