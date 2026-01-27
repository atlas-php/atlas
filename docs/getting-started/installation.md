# Installation

Install and configure Atlas in your Laravel application.

## Requirements

- PHP 8.2+
- Laravel 11+
- Composer

## Install via Composer

```bash
composer require atlas-php/atlas
```

## Publish Configuration

```bash
# Publish Atlas configuration
php artisan vendor:publish --tag=atlas-config

# Publish Prism configuration (if not already published)
php artisan vendor:publish --tag=prism-config
```

This creates `config/atlas.php` and `config/prism.php` with all configurable options.

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
use Atlasphp\Atlas\Atlas;

// Test embedding generation
$embedding = Atlas::embeddings()->generate('Hello, world!');
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
use Atlasphp\Atlas\Atlas;

// Register in a service provider
$registry = app(AgentRegistryContract::class);
$registry->register(SupportAgent::class);

// Use the agent
$response = Atlas::agent('support')
    ->withVariables(['company' => 'Acme'])
    ->chat('Where is my order?');
```

## Common Issues

### Provider Not Configured

If you see "Provider not configured" errors:

1. Ensure you've published Prism config: `php artisan vendor:publish --tag=prism-config`
2. Verify the API key environment variable is set in `.env`
3. Run `php artisan config:clear` after changes

Provider configuration is handled by Prism. See [Prism Configuration](https://prismphp.com/getting-started/configuration.html) for details.

### Rate Limiting

For high-volume applications, implement rate limiting middleware or use queued operations.

### Missing Dependencies

If you encounter class not found errors, ensure you've run:

```bash
composer dump-autoload
```

## Next Steps

- [Configuration](/getting-started/configuration) — Complete configuration reference
- [Agents](/core-concepts/agents) — Build your first AI agent
- [Tools](/core-concepts/tools) — Add callable tools for agents
