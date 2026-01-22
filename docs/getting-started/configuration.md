# Configuration

Complete reference for configuring Atlas in your Laravel application.

## Configuration File

After publishing, you'll find the configuration at `config/atlas.php`:

```php
return [
    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'version' => '2023-06-01',
        ],
    ],

    'chat' => [
        'provider' => 'openai',
        'model' => 'gpt-4o',
    ],

    'embedding' => [
        'provider' => 'openai',
        'model' => 'text-embedding-3-small',
        'dimensions' => 1536,
        'batch_size' => 100,
    ],

    'image' => [
        'provider' => 'openai',
        'model' => 'dall-e-3',
    ],

    'speech' => [
        'provider' => 'openai',
        'model' => 'tts-1',
        'transcription_model' => 'whisper-1',
    ],
];
```

## Environment Variables

Configure Atlas using environment variables in your `.env` file:

```env
# Provider API Keys
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...

# Default Provider
ATLAS_DEFAULT_PROVIDER=openai

# Embedding Configuration
ATLAS_EMBEDDING_PROVIDER=openai
ATLAS_EMBEDDING_MODEL=text-embedding-3-small
ATLAS_EMBEDDING_DIMENSIONS=1536
ATLAS_EMBEDDING_BATCH_SIZE=100

# Image Configuration
ATLAS_IMAGE_PROVIDER=openai

# Speech Configuration
ATLAS_SPEECH_PROVIDER=openai
```

## Provider Configuration

### OpenAI

```php
'providers' => [
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'), // Optional
    ],
],
```

Available models:
- **Chat:** `gpt-4o`, `gpt-4o-mini`, `gpt-4-turbo`, `gpt-3.5-turbo`
- **Embeddings:** `text-embedding-3-small`, `text-embedding-3-large`, `text-embedding-ada-002`
- **Images:** `dall-e-3`, `dall-e-2`
- **Speech:** `tts-1`, `tts-1-hd`, `whisper-1`

### Anthropic

```php
'providers' => [
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'version' => '2023-06-01',
    ],
],
```

Available models:
- **Chat:** `claude-3-opus`, `claude-3-sonnet`, `claude-3-haiku`, `claude-3-5-sonnet`

## Chat Configuration

Default settings for chat operations:

```php
'chat' => [
    'provider' => 'openai',
    'model' => 'gpt-4o',
],
```

These defaults are used when an agent doesn't specify its own provider/model.

## Embedding Configuration

Configure vector embedding generation:

```php
'embedding' => [
    'provider' => 'openai',
    'model' => 'text-embedding-3-small',
    'dimensions' => 1536,
    'batch_size' => 100,
],
```

| Option | Description |
|--------|-------------|
| `provider` | AI provider for embeddings |
| `model` | Embedding model to use |
| `dimensions` | Output vector dimensions |
| `batch_size` | Maximum texts per batch request |

### Variable Dimensions

Some models support custom dimensions:

```php
// Using smaller dimensions for faster similarity search
$embedding = Atlas::embed('Hello world', ['dimensions' => 256]);
```

## Image Configuration

Configure image generation:

```php
'image' => [
    'provider' => 'openai',
    'model' => 'dall-e-3',
],
```

Override at runtime:

```php
$result = Atlas::image('openai', 'dall-e-3')
    ->size('1024x1024')
    ->quality('hd')
    ->generate('A sunset over mountains');
```

## Speech Configuration

Configure text-to-speech and transcription:

```php
'speech' => [
    'provider' => 'openai',
    'model' => 'tts-1',
    'transcription_model' => 'whisper-1',
],
```

Available voices for OpenAI TTS: `alloy`, `echo`, `fable`, `onyx`, `nova`, `shimmer`

## Registering Agents and Tools

Register agents and tools in a service provider:

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

## Pipeline Configuration

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

## Caching Configuration

For production, consider caching your configuration:

```bash
php artisan config:cache
```

Remember to clear the cache when making changes:

```bash
php artisan config:clear
```

## Next Steps

- [Agents](/core-concepts/agents) — Understand the agent system
- [Tools](/core-concepts/tools) — Learn about typed tools
- [Creating Agents](/guides/creating-agents) — Build your first agent
