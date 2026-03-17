# Configuration

Complete reference for configuring Atlas in your Laravel application.

::: tip Prism Configuration
Provider credentials, models, and API settings are configured in Prism. See the [Prism Configuration documentation](https://prismphp.com/getting-started/configuration.html) for provider setup.
:::

## Atlas Configuration File

After publishing, you'll find the Atlas configuration at `config/atlas.php`:

```php
return [

    /*
    |--------------------------------------------------------------------------
    | Pipelines Configuration
    |--------------------------------------------------------------------------
    |
    | Control whether Atlas pipelines are enabled. Pipelines provide
    | middleware hooks for observability, logging, and custom processing
    | during agent execution and API operations.
    |
    */

    'pipelines' => [
        'enabled' => env('ATLAS_PIPELINES_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Events Configuration
    |--------------------------------------------------------------------------
    |
    | Control whether Atlas dispatches Laravel events at lifecycle points.
    | Events are informational (observe-only) and fire AFTER pipelines.
    | Disable to eliminate event overhead when not using listeners.
    |
    */

    'events' => [
        'enabled' => env('ATLAS_EVENTS_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Agents Configuration (auto-discovery) *optional
    |--------------------------------------------------------------------------
    |
    | Configure where Atlas should look for agent definitions.
    | Agents can also be registered programmatically via the AgentRegistry.
    |
    */

    'agents' => [
        'path' => app_path('Agents'),
        'namespace' => 'App\\Agents',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tools Configuration (auto-discovery) *optional
    |--------------------------------------------------------------------------
    |
    | Configure where Atlas should look for tool definitions.
    | Tools can also be registered programmatically via the ToolRegistry.
    |
    */

    'tools' => [
        'path' => app_path('Tools'),
        'namespace' => 'App\\Tools',
    ],

    /*
    |--------------------------------------------------------------------------
    | Models Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for the provider model listing service. When enabled,
    | fetched model lists are cached to minimize API calls. Models don't
    | change frequently, so a longer TTL is recommended.
    |
    */

    'models' => [
        'cache' => [
            'enabled' => env('ATLAS_MODELS_CACHE_ENABLED', true),
            'store' => env('ATLAS_MODELS_CACHE_STORE'),
            'ttl' => (int) env('ATLAS_MODELS_CACHE_TTL', 3600),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Embeddings Configuration
    |--------------------------------------------------------------------------
    |
    | Configure default provider/model for embeddings and optional caching.
    | When provider and model are set, Atlas::embeddings() uses them
    | automatically. Users can still override with ->using().
    |
    */

    'embeddings' => [
        'provider' => env('ATLAS_EMBEDDING_PROVIDER'),
        'model' => env('ATLAS_EMBEDDING_MODEL'),
        'cache' => [
            'enabled' => env('ATLAS_EMBEDDING_CACHE_ENABLED', false),
            'store' => env('ATLAS_EMBEDDING_CACHE_STORE'),
            'ttl' => (int) env('ATLAS_EMBEDDING_CACHE_TTL', 3600),
        ],
    ],

];
```

## Configuration Options

### Pipelines

Control the pipeline middleware system:

```env
ATLAS_PIPELINES_ENABLED=true
```

When enabled, pipelines provide hooks for:
- Logging agent executions
- Adding authentication/authorization
- Collecting metrics and observability data
- Custom pre/post processing

See [Pipelines](/core-concepts/pipelines) for available hooks.

### Events

Control whether Atlas dispatches Laravel events at agent and tool lifecycle points:

```env
ATLAS_EVENTS_ENABLED=true
```

Events are informational — they observe execution but cannot modify it. They fire after pipelines have processed. Disable to eliminate event dispatch overhead when you have no listeners.

See [Events](/advanced/events) for available events and listener examples.

### Agent Auto-Discovery

Atlas can automatically discover and register agents from a configured directory:

```php
'agents' => [
    'path' => app_path('Agents'),      // Directory to scan
    'namespace' => 'App\\Agents',       // PSR-4 namespace
],
```

Place your agent classes in `app/Agents/` and they'll be registered automatically. Set `path` to `null` to disable auto-discovery.

### Tool Auto-Discovery

Similarly, tools can be auto-discovered:

```php
'tools' => [
    'path' => app_path('Tools'),       // Directory to scan
    'namespace' => 'App\\Tools',        // PSR-4 namespace
],
```

Place your tool classes in `app/Tools/` and they'll be registered automatically. Set `path` to `null` to disable auto-discovery.

### Models Cache

Cache provider model listings to reduce API calls:

```env
ATLAS_MODELS_CACHE_ENABLED=true
ATLAS_MODELS_CACHE_STORE=        # Cache store (default: app default)
ATLAS_MODELS_CACHE_TTL=3600      # TTL in seconds (default: 1 hour)
```

When enabled, calls to list available models from providers are cached. Models don't change frequently, so a longer TTL is recommended.

See [Models](/capabilities/models) for usage details.

### Embeddings

Configure default provider and model for embeddings, with optional caching:

```env
ATLAS_EMBEDDING_PROVIDER=        # Default provider (e.g. openai)
ATLAS_EMBEDDING_MODEL=           # Default model (e.g. text-embedding-3-small)
ATLAS_EMBEDDING_CACHE_ENABLED=false
ATLAS_EMBEDDING_CACHE_STORE=     # Cache store (default: app default)
ATLAS_EMBEDDING_CACHE_TTL=3600   # TTL in seconds (default: 1 hour)
```

When `provider` and `model` are set, `Atlas::embeddings()` uses them automatically. You can still override per-call with `->using()`.

See [Embeddings](/capabilities/embeddings) for usage details.

## Provider Configuration

Atlas uses [Prism](https://prismphp.com) for provider connectivity. All provider credentials and settings live in Prism's `config/prism.php`. Publish it with:

```bash
php artisan vendor:publish --tag=prism-config
```

### Supported Providers

Atlas supports **13 providers** out of the box through Prism:

#### OpenAI

```env
OPENAI_API_KEY=sk-...
OPENAI_URL=https://api.openai.com/v1          # optional, custom endpoint
OPENAI_ORGANIZATION=org-...                    # optional
OPENAI_PROJECT=proj-...                        # optional
```

#### Anthropic

```env
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_URL=https://api.anthropic.com/v1     # optional, custom endpoint
ANTHROPIC_API_VERSION=2023-06-01               # optional
ANTHROPIC_DEFAULT_THINKING_BUDGET=1024         # optional, for extended thinking
ANTHROPIC_BETA=                                # optional, comma-separated beta features
```

#### Gemini

```env
GEMINI_API_KEY=...
GEMINI_URL=https://generativelanguage.googleapis.com/v1beta/models  # optional
```

#### DeepSeek

```env
DEEPSEEK_API_KEY=...
DEEPSEEK_URL=https://api.deepseek.com/v1       # optional
```

#### Mistral

```env
MISTRAL_API_KEY=...
MISTRAL_URL=https://api.mistral.ai/v1          # optional
```

#### Groq

```env
GROQ_API_KEY=gsk_...
GROQ_URL=https://api.groq.com/openai/v1        # optional
```

#### xAI (Grok)

```env
XAI_API_KEY=...
XAI_URL=https://api.x.ai/v1                    # optional
```

#### OpenRouter

```env
OPENROUTER_API_KEY=sk-or-...
OPENROUTER_URL=https://openrouter.ai/api/v1    # optional
OPENROUTER_SITE_HTTP_REFERER=                  # optional, for rankings
OPENROUTER_SITE_X_TITLE=                       # optional, for rankings
```

#### Perplexity

```env
PERPLEXITY_API_KEY=pplx-...
PERPLEXITY_URL=https://api.perplexity.ai       # optional
```

#### Ollama (Local)

No API key required — just point to your running Ollama instance:

```env
OLLAMA_URL=http://localhost:11434
```

#### ElevenLabs

```env
ELEVENLABS_API_KEY=...
ELEVENLABS_URL=https://api.elevenlabs.io/v1/   # optional
```

#### VoyageAI

```env
VOYAGEAI_API_KEY=...
VOYAGEAI_URL=https://api.voyageai.com/v1       # optional
```

#### Z

```env
Z_API_KEY=...
Z_URL=https://api.z.ai/api/paas/v4            # optional
```

### Prism Config File

The full `config/prism.php` structure with all providers:

```php
return [
    'prism_server' => [
        'middleware' => [],
        'enabled' => env('PRISM_SERVER_ENABLED', false),
    ],

    'request_timeout' => env('PRISM_REQUEST_TIMEOUT', 30),

    'providers' => [
        'openai' => [
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
            'api_key' => env('OPENAI_API_KEY', ''),
            'organization' => env('OPENAI_ORGANIZATION', null),
            'project' => env('OPENAI_PROJECT', null),
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY', ''),
            'version' => env('ANTHROPIC_API_VERSION', '2023-06-01'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
            'default_thinking_budget' => env('ANTHROPIC_DEFAULT_THINKING_BUDGET', 1024),
            'anthropic_beta' => env('ANTHROPIC_BETA', null),
        ],
        'ollama' => [
            'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        ],
        'mistral' => [
            'api_key' => env('MISTRAL_API_KEY', ''),
            'url' => env('MISTRAL_URL', 'https://api.mistral.ai/v1'),
        ],
        'groq' => [
            'api_key' => env('GROQ_API_KEY', ''),
            'url' => env('GROQ_URL', 'https://api.groq.com/openai/v1'),
        ],
        'xai' => [
            'api_key' => env('XAI_API_KEY', ''),
            'url' => env('XAI_URL', 'https://api.x.ai/v1'),
        ],
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY', ''),
            'url' => env('GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta/models'),
        ],
        'deepseek' => [
            'api_key' => env('DEEPSEEK_API_KEY', ''),
            'url' => env('DEEPSEEK_URL', 'https://api.deepseek.com/v1'),
        ],
        'elevenlabs' => [
            'api_key' => env('ELEVENLABS_API_KEY', ''),
            'url' => env('ELEVENLABS_URL', 'https://api.elevenlabs.io/v1/'),
        ],
        'voyageai' => [
            'api_key' => env('VOYAGEAI_API_KEY', ''),
            'url' => env('VOYAGEAI_URL', 'https://api.voyageai.com/v1'),
        ],
        'openrouter' => [
            'api_key' => env('OPENROUTER_API_KEY', ''),
            'url' => env('OPENROUTER_URL', 'https://openrouter.ai/api/v1'),
            'site' => [
                'http_referer' => env('OPENROUTER_SITE_HTTP_REFERER', null),
                'x_title' => env('OPENROUTER_SITE_X_TITLE', null),
            ],
        ],
        'perplexity' => [
            'api_key' => env('PERPLEXITY_API_KEY', ''),
            'url' => env('PERPLEXITY_URL', 'https://api.perplexity.ai'),
        ],
        'z' => [
            'url' => env('Z_URL', 'https://api.z.ai/api/paas/v4'),
            'api_key' => env('Z_API_KEY', ''),
        ],
    ],
];
```

### Using Multiple Providers

You can configure as many providers as you need simultaneously. Just add the API keys for each provider you want to use, and switch between them per-request:

```php
use Atlasphp\Atlas\Facades\Atlas;
use PrismPHP\Prism\Enums\Provider;

// Use OpenAI for chat
$response = Atlas::chat()
    ->using(Provider::OpenAI, 'gpt-4o')
    ->withPrompt('Explain quantum computing')
    ->generate();

// Use Anthropic for a different task
$response = Atlas::chat()
    ->using(Provider::Anthropic, 'claude-sonnet-4-20250514')
    ->withPrompt('Review this code')
    ->generate();

// Use Ollama for local inference
$response = Atlas::chat()
    ->using(Provider::Ollama, 'llama3')
    ->withPrompt('Summarize this document')
    ->generate();
```

### Custom & OpenAI-Compatible Providers

Many local inference servers (LM Studio, LocalAI, vLLM, text-generation-webui) expose an OpenAI-compatible API. You can add them as custom provider entries in your `config/prism.php` with their own env variables:

```env
# LM Studio
LMSTUDIO_URL=http://localhost:1234/v1
LMSTUDIO_API_KEY=lm-studio

# LocalAI
LOCALAI_URL=http://localhost:8080/v1
LOCALAI_API_KEY=not-needed

# vLLM
VLLM_URL=http://localhost:8000/v1
VLLM_API_KEY=not-needed
```

Then register them as providers in `config/prism.php`:

```php
'providers' => [
    // ... built-in providers ...

    'lmstudio' => [
        'url' => env('LMSTUDIO_URL', 'http://localhost:1234/v1'),
        'api_key' => env('LMSTUDIO_API_KEY', 'lm-studio'),
    ],
    'localai' => [
        'url' => env('LOCALAI_URL', 'http://localhost:8080/v1'),
        'api_key' => env('LOCALAI_API_KEY', ''),
    ],
    'vllm' => [
        'url' => env('VLLM_URL', 'http://localhost:8000/v1'),
        'api_key' => env('VLLM_API_KEY', ''),
    ],
],
```

Use them alongside your cloud providers without any conflicts:

```php
// Cloud provider
$response = Atlas::chat()
    ->using(Provider::OpenAI, 'gpt-4o')
    ->withPrompt('Hello from OpenAI')
    ->generate();

// Local LM Studio
$response = Atlas::chat()
    ->using('lmstudio', 'my-local-model')
    ->withPrompt('Hello from local inference')
    ->generate();
```

## Manual Registration

If you prefer manual registration over auto-discovery, register agents and tools in a service provider:

```php
<?php

namespace App\Providers;

use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Tools\Contracts\ToolRegistryContract;
use Illuminate\Support\ServiceProvider;

class AtlasServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Register agents
        $agents = app(AgentRegistryContract::class);
        $agents->register(\App\Agents\SupportAgent::class);
        $agents->register(\App\Agents\AnalysisAgent::class);

        // Register tools
        $tools = app(ToolRegistryContract::class);
        $tools->register(\App\Tools\LookupOrderTool::class);
        $tools->register(\App\Tools\SearchTool::class);
    }
}
```

## Pipeline Registration

Register pipeline middleware for extensibility:

```php
use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;

public function boot(): void
{
    $registry = app(PipelineRegistry::class);

    // Add logging to all agent executions
    $registry->register(
        'agent.after_execute',
        \App\Pipelines\LogAgentExecution::class,
        priority: 100,
    );
}
```

See [Pipelines](/core-concepts/pipelines) for available hooks.

## Next Steps

- [Agents](/core-concepts/agents) — Understand the agent system
- [Tools](/core-concepts/tools) — Learn about typed tools
- [Chat](/capabilities/chat) — Start using agents
