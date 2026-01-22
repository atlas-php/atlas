# Installation

Install and configure Atlas in your Laravel application.

## Requirements

- PHP 8.4+
- Laravel 12.x
- Composer

## Install via Composer

```bash
composer require atlas-php/atlas
```

## Publish Configuration

```bash
php artisan vendor:publish --tag=atlas-config
```

This creates `config/atlas.php` with all configurable options.

## Configure Provider Credentials

Add your AI provider credentials to `.env`:

```env
# OpenAI (required for default configuration)
OPENAI_API_KEY=sk-...

# Anthropic (optional)
ANTHROPIC_API_KEY=sk-ant-...
```

## Verify Installation

Test that Atlas is working correctly:

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

// Test embedding generation
$embedding = Atlas::embed('Hello, world!');
dd(count($embedding)); // Should output: 1536
```

## Quick Start

### Define an Agent

```php
use Atlasphp\Atlas\Agents\AgentDefinition;

class SupportAgent extends AgentDefinition
{
    public function provider(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return 'gpt-4o';
    }

    public function systemPrompt(): string
    {
        return 'You help customers for {company}.';
    }

    public function tools(): array
    {
        return [
            LookupOrderTool::class
        ];
    }
}
```

### Register and Use

```php
use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Providers\Facades\Atlas;

// Register in a service provider
$registry = app(AgentRegistryContract::class);
$registry->register(SupportAgent::class);

// Use the agent
$response = Atlas::withVariables(['company' => 'Acme'])
    ->chat('support', 'Where is my order?');
```

## Common Issues

### Provider Not Configured

If you see "Provider not configured" errors:

1. Ensure the provider key in config matches exactly (e.g., `openai`, not `OpenAI`)
2. Verify the API key environment variable is set
3. Run `php artisan config:clear` after changes

### Rate Limiting

For high-volume applications, implement rate limiting middleware or use queued operations.

### Missing Dependencies

If you encounter class not found errors, ensure you've run:

```bash
composer dump-autoload
```

## Next Steps

- [Configuration](/getting-started/configuration) — Complete configuration reference
- [Creating Agents](/guides/creating-agents) — Build your first AI agent
- [Creating Tools](/guides/creating-tools) — Add callable tools for agents
