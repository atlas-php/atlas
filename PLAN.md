# atlasphp/atlas Package Specification

> **Version:** 1.0  
> **Purpose:** Stateless AI agent execution framework

---

## Executive Summary

`atlasphp/atlas` is a lightweight, stateless Laravel package that provides the foundational building blocks for AI agent execution. It enables consumers to define agents, register tools, and interact with LLM providers without any state management or persistence.

**Key Principle:** Atlas is a pure execution engine. It takes inputs, executes against LLMs, and returns outputs. All state management (users, sessions, message history, persistence) is the consumer's responsibility.

---

## Table of Contents

1. [Philosophy](#philosophy)
2. [Package Scope](#package-scope)
3. [Architecture Overview](#architecture-overview)
4. [Module Specifications](#module-specifications)
5. [Consumer-Facing API](#consumer-facing-api)
6. [Extension System](#extension-system)
7. [Configuration](#configuration)
8. [Installation & Requirements](#installation--requirements)

---

## Philosophy

### Core Principles

1. **Completely Stateless** — Atlas holds no state. No users, no sessions, no history. Every call is independent.

2. **Synchronous Execution** — All operations complete within the request lifecycle. Returns responses directly.

3. **Provider Agnostic** — Atlas abstracts LLM providers through Prism PHP. No hardcoded provider dependencies.

4. **Consumer-Controlled Everything** — Consumers manage messages, users, context, and persistence. Atlas just executes.

5. **Extension Ready** — Open pipeline system allows consumers to hook into execution without modifying Atlas.

6. **Minimal Dependencies** — Only Laravel framework and Prism PHP. Nothing else.

### What Atlas Does

- Define and register code-based agents
- Define and register tools with type-safe parameters
- Execute agents against LLM providers via Prism PHP
- Accept message history from consumers for multi-turn context
- Accept custom variables for system prompt interpolation
- Generate embeddings (single and batch)
- Generate images via provider APIs
- Text-to-speech and speech-to-text
- Provide extension hooks via pipelines
- Return structured responses to consumers

### What Atlas Does NOT Do

- Store or manage users
- Track sessions or conversations
- Persist messages or history
- Manage any state between calls
- Handle async/queue dispatch
- Provide database models or migrations
- Make assumptions about your application architecture

---

## Package Scope

### Included Modules

| Module         | Purpose                                         |
|----------------|-------------------------------------------------|
| **Foundation** | Pipelines, extension registries, base contracts |
| **Agents**     | Agent definitions, registry, execution          |
| **Tools**      | Tool definitions, registry, execution           |
| **Providers**  | Prism integration, embeddings, image, speech    |

### Not Included (Nexus Territory)

| Component             | Package        |
|-----------------------|----------------|
| Process/Step tracking | atlasphp/nexus |
| Thread/Message models | atlasphp/nexus |
| User management       | atlasphp/nexus |
| Asset storage         | atlasphp/nexus |
| Database migrations   | atlasphp/nexus |
| Async job dispatch    | atlasphp/nexus |
| Database agents       | atlasphp/nexus |

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           ATLASPHP/ATLAS                                 │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │                     Consumer API Layer                           │   │
│  │                                                                  │   │
│  │   Atlas::chat('agent', 'input')           // Agent execution    │   │
│  │   Atlas::forMessages($msgs)->chat(...)    // With history       │   │
│  │   Atlas::embed('text')                    // Embeddings          │   │
│  │   Atlas::image()->generate('prompt')      // Image generation    │   │
│  │   Atlas::speech()->toText($audio)         // Speech-to-text      │   │
│  │                                                                  │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                    │                                    │
│                                    ▼                                    │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │                      Agents Module                               │   │
│  │                                                                  │   │
│  │   AgentContract        AgentRegistry        AgentExecutor       │   │
│  │   AgentDefinition      SystemPromptBuilder                       │   │
│  │                                                                  │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                    │                                    │
│                                    ▼                                    │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │                       Tools Module                               │   │
│  │                                                                  │   │
│  │   ToolContract         ToolRegistry         ToolExecutor        │   │
│  │   ToolDefinition       ToolParameter        ToolResult          │   │
│  │                                                                  │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                    │                                    │
│                                    ▼                                    │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │                     Providers Module                             │   │
│  │                                                                  │   │
│  │   PrismBuilder         EmbeddingService     ImageService        │   │
│  │   SpeechService        UsageExtractor                           │   │
│  │                                                                  │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                    │                                    │
│                                    ▼                                    │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │                    Foundation Module                             │   │
│  │                                                                  │   │
│  │   PipelineRegistry     PipelineRunner       ExtensionRegistry   │   │
│  │   Contracts            Exceptions           Support Classes     │   │
│  │                                                                  │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
                          ┌─────────────────┐
                          │   Prism PHP     │
                          │  (LLM Bridge)   │
                          └─────────────────┘
```

### Dependency Layers

```
Layer 3: Agents (highest level - uses all below)
    ↑
Layer 2: Tools + Providers
    ↑  
Layer 1: Foundation (base layer)
```

---

## Module Specifications

### Foundation Module

**Namespace:** `Atlasphp\Atlas\Foundation`

Provides base infrastructure for extension and composition.

#### Contracts

```php
namespace Atlasphp\Atlas\Foundation\Contracts;

interface ExtensionResolverContract
{
    public function key(): string;
    public function resolve(): mixed;
    public function supports(string $key): bool;
}

interface PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed;
}
```

#### Services

| Service                     | Purpose                               |
|-----------------------------|---------------------------------------|
| `PipelineRegistry`          | Define and register pipeline handlers |
| `PipelineRunner`            | Execute pipelines with middleware     |
| `AbstractExtensionRegistry` | Base class for agent/tool registries  |

#### Directory Structure

```
Foundation/
├── Contracts/
│   ├── ExtensionResolverContract.php
│   └── PipelineContract.php
├── Services/
│   ├── AbstractExtensionRegistry.php
│   ├── PipelineRegistry.php
│   └── PipelineRunner.php
├── Exceptions/
│   └── AtlasException.php
└── AtlasServiceProvider.php
```

---

### Agents Module

**Namespace:** `Atlasphp\Atlas\Agents`

Provides agent definition, registration, and execution.

#### Core Contracts

```php
namespace Atlasphp\Atlas\Agents\Contracts;

interface AgentContract
{
    public function key(): string;
    public function name(): string;
    public function provider(): string;
    public function model(): string;
    public function systemPrompt(): string;
    public function description(): ?string;
    public function tools(): array;
    public function providerTools(): array;
    public function temperature(): ?float;
    public function maxTokens(): ?int;
    public function maxSteps(): ?int;
    public function settings(): array;
}

interface AgentRegistryContract
{
    public function register(string $agentClass, bool $override = false): void;
    public function registerInstance(AgentContract $agent, bool $override = false): void;
    public function get(string $key): AgentContract;
    public function has(string $key): bool;
    public function all(): array;
}

interface AgentExecutorContract
{
    public function execute(
        AgentContract $agent,
        string $input,
        ?ExecutionContext $context = null,
        ?Schema $schema = null,
    ): AgentResponse;
}
```

#### Agent Definition Base Class

```php
namespace Atlasphp\Atlas\Agents;

abstract class AgentDefinition implements AgentContract
{
    abstract public function key(): string;
    abstract public function name(): string;
    abstract public function provider(): string;
    abstract public function model(): string;
    abstract public function systemPrompt(): string;

    // Optional with defaults
    public function description(): ?string { return null; }
    public function tools(): array { return []; }
    public function providerTools(): array { return []; }
    public function temperature(): ?float { return null; }
    public function maxTokens(): ?int { return null; }
    public function maxSteps(): ?int { return null; }
    public function settings(): array { return []; }
}
```

#### Agent Response (Value Object)

```php
namespace Atlasphp\Atlas\Agents\Support;

final readonly class AgentResponse
{
    public function __construct(
        public ?string $text = null,
        public ?array $structured = null,
        public array $toolCalls = [],
        public array $usage = [],
        public array $metadata = [],
    ) {}

    public function hasText(): bool;
    public function hasStructured(): bool;
    public function hasToolCalls(): bool;
    public function totalTokens(): int;
}
```

#### Execution Context (Value Object)

Context is purely for passing data into the execution - Atlas does not interpret or store it.

```php
namespace Atlasphp\Atlas\Agents\Support;

final readonly class ExecutionContext
{
    public function __construct(
        public array $messages = [],
        public array $variables = [],
        public array $metadata = [],
    ) {}

    public function withMessages(array $messages): self;
    public function withVariables(array $variables): self;
    public function withMetadata(array $metadata): self;
    public function getVariable(string $key, mixed $default = null): mixed;
}
```

**Note:** There is no `user` property. If consumers need user context in system prompts, they pass it via `variables`:

```php
$context = new ExecutionContext(
    messages: $history,
    variables: [
        'user_name' => $user->name,
        'user_tier' => $user->subscription_tier,
    ],
);
```
```

#### Directory Structure

```
Agents/
├── Contracts/
│   ├── AgentContract.php
│   ├── AgentRegistryContract.php
│   └── AgentExecutorContract.php
├── Enums/
│   └── AgentType.php
├── Exceptions/
│   ├── AgentException.php
│   ├── AgentNotFoundException.php
│   └── InvalidAgentException.php
├── Services/
│   ├── AgentRegistry.php
│   ├── AgentExecutor.php
│   ├── AgentResolver.php
│   └── SystemPromptBuilder.php
├── Support/
│   ├── ExecutionContext.php
│   └── AgentResponse.php
└── AgentDefinition.php
```

---

### Tools Module

**Namespace:** `Atlasphp\Atlas\Tools`

Provides tool definition, registration, and execution.

#### Core Contracts

```php
namespace Atlasphp\Atlas\Tools\Contracts;

interface ToolContract
{
    public function name(): string;
    public function description(): string;
    public function parameters(): array;
    public function handle(array $args, ToolContext $context): ToolResult;
}

interface ToolRegistryContract
{
    public function register(string $toolClass): void;
    public function registerInstance(ToolContract $tool): void;
    public function get(string $name): ToolContract;
    public function has(string $name): bool;
    public function all(): array;
    public function only(array $names): array;
}
```

#### Tool Definition Base Class

```php
namespace Atlasphp\Atlas\Tools;

abstract class ToolDefinition implements ToolContract
{
    abstract public function name(): string;
    abstract public function description(): string;
    abstract public function parameters(): array;
    abstract public function handle(array $args, ToolContext $context): ToolResult;

    public function toPrismTool(?callable $handler = null): \Prism\Prism\Tool;
}
```

#### Tool Parameter Builder

```php
namespace Atlasphp\Atlas\Tools\Support;

final class ToolParameter
{
    public static function string(string $name, string $description, bool $required = true): array;
    public static function number(string $name, string $description, bool $required = true): array;
    public static function integer(string $name, string $description, bool $required = true): array;
    public static function boolean(string $name, string $description, bool $required = true): array;
    public static function enum(string $name, string $description, array $values, bool $required = true): array;
    public static function array(string $name, string $description, array $itemSchema, bool $required = true): array;
    public static function object(string $name, string $description, array $properties, bool $required = true): array;
}
```

#### Tool Result (Value Object)

```php
namespace Atlasphp\Atlas\Tools\Support;

final readonly class ToolResult
{
    public function __construct(
        public string $text,
        public bool $isError = false,
        public array $metadata = [],
    ) {}

    public static function text(string $text): self;
    public static function error(string $message): self;
    public static function json(array $data): self;
}
```

#### Tool Context (Value Object)

Context passed to tools during execution. Consumer can pass any metadata they need.

```php
namespace Atlasphp\Atlas\Tools\Support;

final readonly class ToolContext
{
    public function __construct(
        public array $metadata = [],
    ) {}

    public function getMeta(string $key, mixed $default = null): mixed;
    public function withMetadata(array $metadata): self;
}
```

**Note:** There is no `user` property. If tools need user context, consumers pass it via `metadata`:

```php
$context = new ToolContext(
    metadata: [
        'user_id' => $user->id,
        'permissions' => $user->permissions,
    ],
);
```
```

#### Directory Structure

```
Tools/
├── Contracts/
│   ├── ToolContract.php
│   └── ToolRegistryContract.php
├── Exceptions/
│   ├── ToolException.php
│   └── ToolNotFoundException.php
├── Services/
│   ├── ToolRegistry.php
│   ├── ToolExecutor.php
│   └── ToolBuilder.php
├── Support/
│   ├── ToolContext.php
│   ├── ToolParameter.php
│   └── ToolResult.php
└── ToolDefinition.php
```

---

### Providers Module

**Namespace:** `Atlasphp\Atlas\Providers`

Provides LLM integration via Prism PHP.

#### Services

| Service | Purpose |
|---------|---------|
| `PrismBuilder` | Build Prism requests for all modalities |
| `EmbeddingService` | Text embedding generation |
| `ImageService` | Image generation |
| `SpeechService` | TTS and STT operations |
| `UsageExtractorRegistry` | Extract usage data from responses |

#### Embedding Contracts

```php
namespace Atlasphp\Atlas\Providers\Contracts;

interface EmbeddingProviderContract
{
    public function generate(string $text): array;
    public function generateBatch(array $texts): array;
    public function dimensions(): int;
}
```

#### Directory Structure

```
Providers/
├── Contracts/
│   └── EmbeddingProviderContract.php
├── Embedding/
│   └── PrismEmbeddingProvider.php
├── Services/
│   ├── PrismBuilder.php
│   ├── EmbeddingService.php
│   ├── ImageService.php
│   ├── SpeechService.php
│   ├── ProviderConfigService.php
│   ├── UsageExtractorRegistry.php
│   └── AtlasManager.php
├── Support/
│   ├── DefaultUsageExtractor.php
│   └── MessageContextBuilder.php
└── Facades/
    └── Atlas.php
```

---

## Consumer-Facing API

### The Atlas Facade

**Namespace:** `Atlasphp\Atlas\Facades\Atlas`

The single entry point for all Atlas operations.

```php
use Atlasphp\Atlas\Facades\Atlas;
```

### Agent Execution

#### Basic Chat

```php
// By registry key
$response = Atlas::chat('support-agent', 'Hello, I need help');

// By class name
$response = Atlas::chat(SupportAgent::class, 'Hello!');

// By agent instance
$agent = new SupportAgent();
$response = Atlas::chat($agent, 'Hello!');

// Access response
echo $response->text;
echo $response->totalTokens();
```

#### With Conversation History (forMessages)

For multi-turn conversations, use `forMessages()` to provide conversation history. Atlas uses them for context but does not store them.

```php
// Consumer manages their own message storage (database, cache, session, etc.)
$messages = [
    ['role' => 'user', 'content' => 'Hi there'],
    ['role' => 'assistant', 'content' => 'Hello! How can I help?'],
];

// Fluent API
$response = Atlas::forMessages($messages)->chat('support-agent', 'What were we discussing?');

// The new user input is automatically appended to messages for the LLM call
// Consumer is responsible for storing the response:
$messages[] = ['role' => 'user', 'content' => 'What were we discussing?'];
$messages[] = ['role' => 'assistant', 'content' => $response->text];
// Store $messages however you want (Redis, database, session, etc.)
```

#### Direct Messages Parameter

For simpler cases, pass messages directly:

```php
$response = Atlas::chat('support-agent', 'Follow up', messages: $messages);
```

#### With Variables (System Prompt Interpolation)

Pass variables that get interpolated into the agent's system prompt:

```php
$response = Atlas::forMessages($messages)
    ->withVariables([
        'user_name' => $user->name,
        'account_type' => $user->subscription,
        'timezone' => $user->timezone,
    ])
    ->chat('support-agent', 'Check my account status');
```

The agent's system prompt can reference these:
```
You are helping {user_name}, a {account_type} customer.
Their timezone is {timezone}.
```

#### With Metadata

Pass metadata for pipeline middleware or custom processing:

```php
$response = Atlas::forMessages($messages)
    ->withVariables(['user_name' => 'John'])
    ->withMetadata(['request_id' => $requestId, 'source' => 'api'])
    ->chat('support-agent', 'Help me');
```

#### Structured Output

```php
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

$schema = new ObjectSchema(
    name: 'contact',
    description: 'Contact information',
    properties: [
        new StringSchema('name', 'Person name'),
        new StringSchema('email', 'Email address'),
    ],
    requiredFields: ['name', 'email'],
);

$response = Atlas::chat('extraction-agent', 'John Doe, john@example.com', schema: $schema);

// Access structured data
$contact = $response->structured;
// ['name' => 'John Doe', 'email' => 'john@example.com']

// Also works with forMessages
$response = Atlas::forMessages($history)
    ->chat('extraction-agent', 'Extract from our conversation', schema: $schema);
```

### Embeddings

```php
// Single embedding
$embedding = Atlas::embed('Hello world');
// Returns: [0.123, -0.456, 0.789, ...]

// Batch embeddings
$embeddings = Atlas::embedBatch([
    'First document',
    'Second document',
    'Third document',
]);
// Returns: [[...], [...], [...]]

// Get configured dimensions
$dimensions = Atlas::embeddingDimensions();
// Returns: 1536
```

### Image Generation

```php
// Generate image
$result = Atlas::image()->generate('A sunset over mountains');
echo $result->url;

// With specific provider
$result = Atlas::image('openai')->generate('A sunset', [
    'size' => '1024x1024',
    'quality' => 'hd',
]);
```

### Speech Services

```php
// Text to speech
$audio = Atlas::speech()->toSpeech('Hello, world!', [
    'voice' => 'alloy',
]);

// Speech to text
$text = Atlas::speech()->toText($audioContent, [
    'language' => 'en',
]);
```

### Agent Registration

```php
// In a service provider
use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;

public function boot(AgentRegistryContract $agents): void
{
    // Register by class
    $agents->register(SupportAgent::class);
    $agents->register(SalesAgent::class);

    // Register instance with override
    $agents->registerInstance(new CustomAgent(), override: true);
}
```

### Tool Registration

```php
// In a service provider
use Atlasphp\Atlas\Tools\Contracts\ToolRegistryContract;

public function boot(ToolRegistryContract $tools): void
{
    // Register by class
    $tools->register(CalculatorTool::class);
    $tools->register(WeatherTool::class);

    // Register instance
    $tools->registerInstance(new CustomTool());
}
```

### Creating an Agent

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
        // Variables like {user_name} are interpolated from consumer-provided variables
        return <<<PROMPT
            You are {agent_name}, a helpful customer support assistant.
            
            You are helping {user_name}, a {account_type} customer.
            
            Help customers with their inquiries professionally.
            PROMPT;
    }

    public function tools(): array
    {
        return [
            SearchKnowledgeBaseTool::class,
            CreateTicketTool::class,
        ];
    }

    public function temperature(): ?float
    {
        return 0.7;
    }
}
```

**Usage with variables:**
```php
$response = Atlas::forMessages($messages)
    ->withVariables([
        'agent_name' => 'Support Bot',
        'user_name' => $user->name,
        'account_type' => $user->subscription_tier,
    ])
    ->chat('support-agent', 'I need help with my order');
```

### Creating a Tool

```php
namespace App\Tools;

use Atlasphp\Atlas\Tools\ToolDefinition;
use Atlasphp\Atlas\Tools\Support\ToolParameter;
use Atlasphp\Atlas\Tools\Support\ToolResult;
use Atlasphp\Atlas\Tools\Support\ToolContext;

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
            'divide' => $args['b'] !== 0 ? $args['a'] / $args['b'] : 'Error: Division by zero',
        };

        return is_string($result)
            ? ToolResult::error($result)
            : ToolResult::text((string) $result);
    }
}
```

---

## Extension System

### Pipeline Hooks

Core provides open pipelines that extensions can hook into.

#### Available Pipelines

| Pipeline                           | When Fired             | Data                  |
|------------------------------------|------------------------|-----------------------|
| `agent.before_execute`             | Before agent execution | AgentContext          |
| `agent.after_execute`              | After agent execution  | AgentResponse         |
| `agent.system_prompt.before_build` | Before building prompt | Agent + Context       |
| `agent.system_prompt.after_build`  | After building prompt  | System prompt string  |
| `tool.before_execute`              | Before tool execution  | Tool + Args + Context |
| `tool.after_execute`               | After tool execution   | ToolResult            |

#### Registering Pipeline Handlers

```php
use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;

// In a service provider
public function boot(PipelineRegistry $pipelines): void
{
    // Register middleware for agent execution
    $pipelines->register(
        'agent.before_execute',
        InjectMemoriesMiddleware::class,
        priority: 50
    );

    // Register middleware for tool execution
    $pipelines->register(
        'tool.after_execute',
        LogToolResultMiddleware::class,
        priority: 100
    );
}
```

### Agent Extension Registry

```php
use Atlasphp\Atlas\Agents\Services\AgentExtensionRegistry;

// Register custom metadata resolver
$extensions->register(new MemoryExtensionResolver());

// In agent definition
public function settings(): array
{
    return [
        'memory' => [
            'enabled' => true,
            'context_window' => 10,
        ],
    ];
}
```

### Tool Extension Registry

```php
use Atlasphp\Atlas\Tools\Services\ToolExtensionRegistry;

// Register custom tool configuration
$extensions->register(new RateLimitExtensionResolver());
```

---

## Configuration

### Config File: `config/atlas.php`

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Default LLM Provider
    |--------------------------------------------------------------------------
    */
    'default_provider' => env('ATLAS_DEFAULT_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Embedding Configuration
    |--------------------------------------------------------------------------
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
    */
    'image' => [
        'default_provider' => env('ATLAS_IMAGE_PROVIDER', 'openai'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Speech Configuration
    |--------------------------------------------------------------------------
    */
    'speech' => [
        'default_provider' => env('ATLAS_SPEECH_PROVIDER', 'openai'),
    ],
];
```

**Note:** There are no built-in system prompt variables. All variables are consumer-provided via `withVariables()`. If you want time/date in prompts, pass them yourself:

```php
Atlas::forMessages($messages)
    ->withVariables([
        'current_time' => now()->toDateTimeString(),
        'current_date' => now()->toDateString(),
    ])
    ->chat('agent', 'input');
```

---

## Installation & Requirements

### Requirements

- PHP 8.2+
- Laravel 11.x
- prism-php/prism ^1.0

### Installation

```bash
composer require atlas-php/atlas
```

### Service Provider Registration

Auto-discovered via Laravel package discovery. Manual registration:

```php
// config/app.php
'providers' => [
    Atlasphp\Atlas\Foundation\AtlasServiceProvider::class,
],
```

### Publish Configuration

```bash
php artisan vendor:publish --tag=atlas-config
```

---

## The forMessages Context Builder

The `forMessages()` method returns a fluent context builder for multi-turn conversations:

```php
namespace Atlasphp\Atlas\Providers\Support;

final class MessageContextBuilder
{
    public function __construct(
        private readonly AtlasManager $manager,
        private array $messages,
    ) {}

    /**
     * Add variables for system prompt interpolation.
     */
    public function withVariables(array $variables): self;

    /**
     * Add metadata for pipeline middleware.
     */
    public function withMetadata(array $metadata): self;

    /**
     * Execute chat with the configured context.
     */
    public function chat(
        string|AgentContract $agent,
        string $input,
        ?Schema $schema = null,
    ): AgentResponse;
}
```

### Usage Patterns

```php
// Simple conversation continuation
$response = Atlas::forMessages($messages)->chat('agent', 'Follow up');

// With variables for system prompt
$response = Atlas::forMessages($messages)
    ->withVariables([
        'user_name' => $user->name,
        'timezone' => 'UTC',
    ])
    ->chat('agent', 'What time is it?');

// With metadata for middleware
$response = Atlas::forMessages($messages)
    ->withVariables(['user_name' => $user->name])
    ->withMetadata(['request_id' => $requestId])
    ->chat('agent', 'Help me');

// Consumer manages message persistence
$messages[] = ['role' => 'user', 'content' => 'Help me'];
$messages[] = ['role' => 'assistant', 'content' => $response->text];
// Store $messages however you want (Redis, database, session, etc.)
```

---

## Summary

`atlasphp/atlas` is a stateless AI execution engine. It takes inputs, runs them through LLMs, and returns outputs. All state management is the consumer's responsibility.

| Feature           | API                                                              |
|-------------------|------------------------------------------------------------------|
| Basic chat        | `Atlas::chat($agent, $input)`                                    |
| With messages     | `Atlas::forMessages($messages)->chat($agent, $input)`            |
| With variables    | `Atlas::forMessages($messages)->withVariables([...])->chat(...)` |
| Direct messages   | `Atlas::chat($agent, $input, messages: $messages)`               |
| Structured output | `Atlas::chat($agent, $input, schema: $schema)`                   |
| Embeddings        | `Atlas::embed($text)` / `Atlas::embedBatch($texts)`              |
| Images            | `Atlas::image()->generate($prompt)`                              |
| Speech            | `Atlas::speech()->toSpeech($text)` / `->toText($audio)`          |

**Atlas does NOT:**
- Store users, sessions, or any state
- Persist messages or conversation history
- Provide built-in variables (consumers pass everything)
- Make assumptions about your application

Consumers who need persistence, user management, async processing, and full conversation management should add `atlasphp/nexus`.