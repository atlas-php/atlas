# Configuration

Complete reference for configuring Atlas in your Laravel application.

## Overview

All Atlas configuration lives in a single file: `config/atlas.php`. Publish it with:

```bash
php artisan vendor:publish --tag=atlas-config
```

Atlas manages its own provider connections, timeouts, middleware, and persistence. No external configuration files are needed.

## Full Configuration File

After publishing, you'll find the complete configuration at `config/atlas.php`:

```php
<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Provider & Model
    |--------------------------------------------------------------------------
    |
    | The default provider and model used when none is explicitly specified.
    |
    */

    'defaults' => [
        'text' => ['provider' => env('ATLAS_TEXT_PROVIDER'), 'model' => env('ATLAS_TEXT_MODEL')],
        'image' => ['provider' => env('ATLAS_IMAGE_PROVIDER'), 'model' => env('ATLAS_IMAGE_MODEL')],
        'video' => ['provider' => env('ATLAS_VIDEO_PROVIDER'), 'model' => env('ATLAS_VIDEO_MODEL')],
        'embed' => ['provider' => env('ATLAS_EMBED_PROVIDER'), 'model' => env('ATLAS_EMBED_MODEL')],
        'moderate' => ['provider' => env('ATLAS_MODERATE_PROVIDER'), 'model' => env('ATLAS_MODERATE_MODEL')],
        'rerank' => ['provider' => env('ATLAS_RERANK_PROVIDER'), 'model' => env('ATLAS_RERANK_MODEL')],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agents
    |--------------------------------------------------------------------------
    |
    | Auto-discovery path and namespace for agent classes. Agents found in
    | the configured directory are automatically registered at boot time.
    |
    */

    'agents' => [
        'path' => null,
        'namespace' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Configurations
    |--------------------------------------------------------------------------
    |
    | Configuration for each AI provider. Each provider requires at minimum
    | an API key. Additional provider-specific options can be set here.
    |
    */

    'providers' => [

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
            'organization' => env('OPENAI_ORGANIZATION'),
        ],

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
            'version' => env('ANTHROPIC_VERSION', '2024-10-22'),
        ],

        'google' => [
            'api_key' => env('GOOGLE_API_KEY'),
            'url' => env('GOOGLE_URL', 'https://generativelanguage.googleapis.com'),
        ],

        'xai' => [
            'api_key' => env('XAI_API_KEY'),
            'url' => env('XAI_URL', 'https://api.x.ai/v1'),
        ],

        'cohere' => [
            'api_key' => env('COHERE_API_KEY'),
            'url' => env('COHERE_URL', 'https://api.cohere.com'),
        ],

        'jina' => [
            'api_key' => env('JINA_API_KEY'),
            'url' => env('JINA_URL', 'https://api.jina.ai'),
        ],

        'elevenlabs' => [
            'api_key' => env('ELEVENLABS_API_KEY'),
            'url' => env('ELEVENLABS_URL', 'https://api.elevenlabs.io/v1'),
            'media_timeout' => 300,
        ],

        // ─── Custom Providers (Chat Completions compatible) ─────────────
        //
        // Add a 'driver' key to use a named driver or custom class.
        // Available named drivers: 'chat_completions', 'responses'
        //
        // 'ollama' => [
        //     'driver'   => 'chat_completions',
        //     'api_key'  => env('OLLAMA_API_KEY', 'ollama'),
        //     'base_url' => env('OLLAMA_URL', 'http://localhost:11434/v1'),
        // ],
        //
        // 'lmstudio' => [
        //     'driver'   => 'chat_completions',
        //     'api_key'  => env('LMSTUDIO_API_KEY', 'lm-studio'),
        //     'base_url' => env('LMSTUDIO_URL', 'http://localhost:1234/v1'),
        // ],
        //
        // 'groq' => [
        //     'driver'   => 'chat_completions',
        //     'api_key'  => env('GROQ_API_KEY'),
        //     'base_url' => 'https://api.groq.com/openai/v1',
        // ],
        //
        // 'together' => [
        //     'driver'   => 'chat_completions',
        //     'api_key'  => env('TOGETHER_API_KEY'),
        //     'base_url' => 'https://api.together.xyz/v1',
        // ],
        //
        // 'deepseek' => [
        //     'driver'   => 'chat_completions',
        //     'api_key'  => env('DEEPSEEK_API_KEY'),
        //     'base_url' => 'https://api.deepseek.com/v1',
        // ],
        //
        // 'openrouter' => [
        //     'driver'   => 'chat_completions',
        //     'api_key'  => env('OPENROUTER_API_KEY'),
        //     'base_url' => 'https://openrouter.ai/api/v1',
        // ],

        // ─── Custom Provider (own driver class) ─────────────────────────
        //
        // Use a custom driver class for non-OpenAI-compatible providers.
        // The class receives all driver dependencies (ProviderConfig,
        // HttpClient, MiddlewareStack, AtlasCache) via constructor injection.
        //
        // 'my-provider' => [
        //     'driver'       => \App\Atlas\MyCustomDriver::class,
        //     'api_key'      => env('MY_PROVIDER_API_KEY'),
        //     'base_url'     => 'https://api.my-provider.com/v1',
        //     'capabilities' => ['text' => true, 'stream' => true],
        // ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Timeout Configuration
    |--------------------------------------------------------------------------
    |
    | Request timeout values in seconds for different operation types.
    |
    */

    'timeout' => [
        'default' => (int) env('ATLAS_TIMEOUT', 60),
        'reasoning' => (int) env('ATLAS_TIMEOUT_REASONING', 300),
        'media' => (int) env('ATLAS_TIMEOUT_MEDIA', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Global middleware applied at each layer of Atlas execution.
    | Provider middleware runs on every HTTP call to an AI provider.
    | Step middleware runs on each executor round trip.
    | Tool middleware runs on each tool execution.
    | Agent middleware runs on each agent execution.
    |
    */

    'middleware' => [
        'provider' => [],
        'step' => [],
        'tool' => [],
        'agent' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Variables
    |--------------------------------------------------------------------------
    |
    | Static variables available in all instructions across all modalities.
    | These are the lowest priority — global registry and withVariables()
    | override them.
    |
    | Supports flat keys and nested arrays:
    |   'APP_NAME' => 'My App'
    |   'COMPANY' => ['NAME' => 'Acme', 'SUPPORT_EMAIL' => 'help@acme.com']
    |
    | Access flat: {APP_NAME}
    | Access nested: {COMPANY.NAME}, {COMPANY.SUPPORT_EMAIL}
    |
    */

    'variables' => [
        'APP_NAME' => env('APP_NAME', 'Laravel'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Configuration for queued Atlas executions. Use ->queue() on any
    | pending request to dispatch it asynchronously.
    |
    */

    'queue' => [
        'connection' => env('ATLAS_QUEUE_CONNECTION'),
        'queue' => env('ATLAS_QUEUE', 'default'),
        'tries' => (int) env('ATLAS_QUEUE_TRIES', 3),
        'backoff' => (int) env('ATLAS_QUEUE_BACKOFF', 30),
        'timeout' => (int) env('ATLAS_QUEUE_TIMEOUT', 300),
        'after_commit' => (bool) env('ATLAS_QUEUE_AFTER_COMMIT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Media Storage
    |--------------------------------------------------------------------------
    |
    | Configure how Atlas stores media files (images, audio, video).
    | Used by Input::store() and Response::store() methods.
    |
    */

    'storage' => [
        'disk' => env('ATLAS_STORAGE_DISK'),
        'prefix' => 'atlas',
        'visibility' => 'private',
    ],

    /*
    |--------------------------------------------------------------------------
    | Embeddings
    |--------------------------------------------------------------------------
    |
    | Configuration for embedding generation. The dimensions value controls
    | vector column size in migrations. For embedding caching, see the
    | 'cache' section below (atlas.cache.ttl.embeddings).
    |
    | Note: 'dimensions' was previously at 'persistence.embedding_dimensions'.
    | The old location is still supported as a fallback in migrations.
    |
    */

    'embeddings' => [
        'dimensions' => (int) env('ATLAS_EMBEDDING_DIMENSIONS', 1536),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Unified caching for provider data (models, voices) and embeddings.
    | Set any TTL to 0 to disable caching for that type.
    |
    */

    'cache' => [
        'store' => env('ATLAS_CACHE_STORE'),
        'prefix' => 'atlas',
        'ttl' => [
            'models' => (int) env('ATLAS_CACHE_MODELS_TTL', 86400),        // 24 hours
            'voices' => (int) env('ATLAS_CACHE_VOICES_TTL', 3600),         // 1 hour
            'embeddings' => (int) env('ATLAS_CACHE_EMBEDDINGS_TTL', 0),    // disabled by default
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Persistence
    |--------------------------------------------------------------------------
    |
    | Optional conversation persistence and execution tracking. If you don't
    | publish the migrations, Atlas works fully stateless. When enabled,
    | execution tracking is always active — conversations with tools require
    | execution data for replay.
    |
    */

    'persistence' => [
        'enabled' => env('ATLAS_PERSISTENCE_ENABLED', false),
        'table_prefix' => env('ATLAS_TABLE_PREFIX', 'atlas_'),
        'message_limit' => (int) env('ATLAS_MESSAGE_LIMIT', 50),

        // Auto-store generated files (images, audio, video) as assets.
        // Only applies to direct calls tracked by TrackProviderCall.
        // Tool-generated assets use ToolAssets::store() explicitly.
        'auto_store_assets' => env('ATLAS_AUTO_STORE_ASSETS', true),

        // Model overrides — extend base models with your own
        'models' => [
            // 'conversation'        => \Atlasphp\Atlas\Persistence\Models\Conversation::class,
            // 'message'             => \Atlasphp\Atlas\Persistence\Models\Message::class,
            // 'asset'               => \Atlasphp\Atlas\Persistence\Models\Asset::class,
            // 'message_asset'   => \Atlasphp\Atlas\Persistence\Models\MessageAsset::class,
            // 'execution'           => \Atlasphp\Atlas\Persistence\Models\Execution::class,
            // 'execution_step'      => \Atlasphp\Atlas\Persistence\Models\ExecutionStep::class,
            // 'execution_tool_call' => \Atlasphp\Atlas\Persistence\Models\ExecutionToolCall::class,
        ],
    ],

];
```

## Configuration Sections

### Default Providers

Set default providers and models per modality so you don't have to specify them on every call:

```env
ATLAS_TEXT_PROVIDER=openai
ATLAS_TEXT_MODEL=gpt-4o
ATLAS_IMAGE_PROVIDER=openai
ATLAS_IMAGE_MODEL=dall-e-3
ATLAS_EMBED_PROVIDER=openai
ATLAS_EMBED_MODEL=text-embedding-3-small
```

Atlas supports six modalities: `text`, `image`, `video`, `embed`, `moderate`, and `rerank`. Each can have its own default provider and model.

### Provider Credentials

Atlas ships with built-in support for seven providers. Add the relevant API keys to your `.env`:

#### OpenAI

```env
OPENAI_API_KEY=sk-...
OPENAI_URL=https://api.openai.com/v1          # optional, custom endpoint
OPENAI_ORGANIZATION=org-...                    # optional
```

#### Anthropic

```env
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_URL=https://api.anthropic.com/v1     # optional, custom endpoint
ANTHROPIC_VERSION=2024-10-22                   # optional
```

#### Google

```env
GOOGLE_API_KEY=...
GOOGLE_URL=https://generativelanguage.googleapis.com  # optional
```

#### xAI (Grok)

```env
XAI_API_KEY=...
XAI_URL=https://api.x.ai/v1                   # optional
```

#### Cohere

```env
COHERE_API_KEY=...
```

#### Jina

```env
JINA_API_KEY=...
```

#### ElevenLabs

```env
ELEVENLABS_API_KEY=...
```

### Custom Providers (Chat Completions Compatible)

Any service that exposes an OpenAI-compatible Chat Completions API can be added as a custom provider. Add a `driver` key set to `'chat_completions'` and a `base_url`:

```php
// config/atlas.php — inside 'providers' array

'ollama' => [
    'driver'   => 'chat_completions',
    'api_key'  => env('OLLAMA_API_KEY', 'ollama'),
    'base_url' => env('OLLAMA_URL', 'http://localhost:11434/v1'),
],

'lmstudio' => [
    'driver'   => 'chat_completions',
    'api_key'  => env('LMSTUDIO_API_KEY', 'lm-studio'),
    'base_url' => env('LMSTUDIO_URL', 'http://localhost:1234/v1'),
],

'groq' => [
    'driver'   => 'chat_completions',
    'api_key'  => env('GROQ_API_KEY'),
    'base_url' => 'https://api.groq.com/openai/v1',
],

'deepseek' => [
    'driver'   => 'chat_completions',
    'api_key'  => env('DEEPSEEK_API_KEY'),
    'base_url' => 'https://api.deepseek.com/v1',
],

'openrouter' => [
    'driver'   => 'chat_completions',
    'api_key'  => env('OPENROUTER_API_KEY'),
    'base_url' => 'https://openrouter.ai/api/v1',
],
```

Available named drivers: `chat_completions` and `responses`. See the [Custom Providers Guide](/guides/custom-providers) for full setup instructions.

### Custom Drivers (Non-Compatible APIs)

For providers that don't follow the OpenAI protocol, use a custom driver class:

```php
'my-provider' => [
    'driver'       => \App\Atlas\MyProviderDriver::class,
    'api_key'      => env('MY_PROVIDER_API_KEY'),
    'base_url'     => 'https://api.my-provider.com/v1',
    'capabilities' => ['text' => true, 'stream' => true],
],
```

The driver class receives all dependencies (`ProviderConfig`, `HttpClient`, `MiddlewareStack`, `AtlasCache`) via constructor injection. See the [Custom Drivers Guide](/guides/custom-drivers) for creating driver classes and implementing handler interfaces.

### Using Multiple Providers

Configure as many providers as you need and switch between them per request:

```php
// Use OpenAI for text
$text = Atlas::text('openai', 'gpt-4o')->message('Hello')->asText();

// Use Anthropic for another task
$text = Atlas::text('anthropic', 'claude-sonnet-4-20250514')->message('Hello')->asText();

// Use a custom provider
$text = Atlas::text('ollama', 'llama3')->message('Hello')->asText();
```

### Timeout

Request timeout values in seconds, tuned for different operation types:

```env
ATLAS_TIMEOUT=60              # Default timeout
ATLAS_TIMEOUT_REASONING=300   # Extended thinking models
ATLAS_TIMEOUT_MEDIA=120       # Image/audio/video generation
```

### Middleware

Atlas provides four middleware layers, each running at a different point in the execution lifecycle:

```php
'middleware' => [
    'provider' => [],   // Runs on every HTTP call to an AI provider
    'step' => [],       // Runs on each executor round trip
    'tool' => [],       // Runs on each tool execution
    'agent' => [],      // Runs on each agent execution
],
```

Register middleware classes in each array. They execute in order for every request at that layer.

```php
'middleware' => [
    'provider' => [
        \App\Atlas\Middleware\LogProviderCalls::class,
        \App\Atlas\Middleware\RateLimiter::class,
    ],
    'agent' => [
        \App\Atlas\Middleware\AuditAgentRuns::class,
    ],
],
```

### Variables

Define global instruction variables that are available across all modalities. Use `{VARIABLE_NAME}` syntax in any instruction string:

```php
'variables' => [
    'APP_NAME' => env('APP_NAME', 'Laravel'),
    'COMPANY' => [
        'NAME' => 'Acme',
        'SUPPORT_EMAIL' => 'help@acme.com',
    ],
],
```

Access flat variables with `{APP_NAME}` and nested variables with `{COMPANY.NAME}`. These are the lowest priority — values passed via `->withVariables()` or the global variable registry take precedence.

### Queue

Configuration for asynchronous execution via `->queue()`:

```env
ATLAS_QUEUE_CONNECTION=        # Queue connection (default: app default)
ATLAS_QUEUE=default            # Queue name
ATLAS_QUEUE_TRIES=3            # Max retry attempts
ATLAS_QUEUE_BACKOFF=30         # Seconds between retries
ATLAS_QUEUE_TIMEOUT=300        # Job timeout in seconds
ATLAS_QUEUE_AFTER_COMMIT=true  # Dispatch after DB commit
```

### Storage

Configure how Atlas stores media files (images, audio, video):

```env
ATLAS_STORAGE_DISK=            # Filesystem disk (default: app default)
```

```php
'storage' => [
    'disk' => env('ATLAS_STORAGE_DISK'),
    'prefix' => 'atlas',
    'visibility' => 'private',
],
```

### Embeddings

Configure the vector dimensions for embedding storage:

```env
ATLAS_EMBEDDING_DIMENSIONS=1536
```

This value controls vector column size in persistence migrations. The default of 1536 matches OpenAI's `text-embedding-3-small`.

### Cache

Unified caching for provider data and embeddings. Set any TTL to `0` to disable caching for that type:

```env
ATLAS_CACHE_STORE=             # Cache store (default: app default)
ATLAS_CACHE_MODELS_TTL=86400   # Model list cache: 24 hours
ATLAS_CACHE_VOICES_TTL=3600    # Voice list cache: 1 hour
ATLAS_CACHE_EMBEDDINGS_TTL=0   # Embedding cache: disabled by default
```

### Persistence

Optional conversation persistence and execution tracking. Atlas works fully stateless by default.

```env
ATLAS_PERSISTENCE_ENABLED=false
ATLAS_TABLE_PREFIX=atlas_
ATLAS_MESSAGE_LIMIT=50
ATLAS_AUTO_STORE_ASSETS=true
```

To enable persistence:

1. Publish and run migrations:
   ```bash
   php artisan vendor:publish --tag=atlas-migrations
   php artisan migrate
   ```

2. Set `ATLAS_PERSISTENCE_ENABLED=true` in `.env`

You can override any persistence model by uncommenting and replacing the class in the `models` array.

### Agent Auto-Discovery

Configure a directory for Atlas to scan and auto-register agent classes at boot:

```php
'agents' => [
    'path' => app_path('Agents'),
    'namespace' => 'App\\Agents',
],
```

Both values are `null` by default (auto-discovery disabled). Set them to enable scanning. You can also scaffold agents with:

```bash
php artisan make:agent SupportAgent
```

## Next Steps

- [Agents](/features/agents) — Understand the agent system
- [Tools](/features/tools) — Learn about typed tools
- [Middleware](/features/middleware) — Add middleware to execution layers
