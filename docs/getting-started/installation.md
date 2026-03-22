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
php artisan vendor:publish --tag=atlas-config
```

This creates `config/atlas.php` with all configurable options. Atlas manages its own provider configuration — no external config files needed.

## Configure Provider Credentials

Add your AI provider credentials to `.env`:

```env
# OpenAI
OPENAI_API_KEY=sk-...

# or Anthropic
ANTHROPIC_API_KEY=sk-ant-...
```

See [Configuration](/getting-started/configuration) for the full list of supported providers and their environment variables.

## Verify Installation

Test that Atlas is working correctly:

```php
$response = Atlas::text('openai', 'gpt-4o')
    ->message('Hello, Atlas!')
    ->asText();

echo $response->text; // "Hello! How can I help you?"
```

## Quick Start

### Define an Agent

```php
use Atlasphp\Atlas\Agent;

class SupportAgent extends Agent
{
    public function provider(): ?string
    {
        return 'openai';
    }

    public function model(): ?string
    {
        return 'gpt-4o';
    }

    public function instructions(): ?string
    {
        return 'You are a helpful support agent for {company}.';
    }

    public function tools(): array
    {
        return [LookupOrderTool::class];
    }
}
```

### Use the Agent

```php
$response = Atlas::agent('support')
    ->withVariables(['company' => 'Acme'])
    ->message('Where is my order?')
    ->asText();
```

### Artisan Commands

Scaffold agents and tools with Artisan:

```bash
php artisan make:agent SupportAgent
php artisan make:tool LookupOrderTool
```

## Optional: Enable Persistence

Atlas works fully stateless by default. To enable conversation persistence and execution tracking:

```bash
php artisan vendor:publish --tag=atlas-migrations
php artisan migrate
```

Then set `ATLAS_PERSISTENCE_ENABLED=true` in your `.env`.

## Common Issues

### Provider Not Configured

If you see "Provider not configured" errors:

1. Verify the API key environment variable is set in `.env`
2. Check that the provider is configured in `config/atlas.php` under `providers`
3. Run `php artisan config:clear` after changes

### Missing Dependencies

If you encounter class not found errors, ensure you've run:

```bash
composer dump-autoload
```

## Next Steps

- [Configuration](/getting-started/configuration) — Complete configuration reference
- [Agents](/core-concepts/agents) — Build your first AI agent
- [Tools](/core-concepts/tools) — Add callable tools for agents
