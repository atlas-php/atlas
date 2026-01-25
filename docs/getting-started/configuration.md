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

## Prism Configuration

Provider credentials and model defaults are configured in Prism's `config/prism.php`. After publishing:

```bash
php artisan vendor:publish --tag=prism-config
```

Configure your providers in `.env`:

```env
# OpenAI
OPENAI_API_KEY=sk-...

# Anthropic
ANTHROPIC_API_KEY=sk-ant-...

# Ollama (local)
OLLAMA_URL=http://localhost:11434
```

For detailed provider configuration including custom URLs, organization IDs, and model options, see the [Prism Configuration documentation](https://prismphp.com/getting-started/configuration.html).

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
