# Installation Guide

## Goal

Install and configure Atlas in your Laravel application.

## Prerequisites

- PHP 8.4+
- Laravel 12.x
- Composer

## Steps

### 1. Install the Package

```bash
composer require atlas-php/atlas
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --tag=atlas-config
```

This creates `config/atlas.php`.

### 3. Configure Providers

Add your AI provider credentials to `.env`:

```env
# OpenAI
OPENAI_API_KEY=sk-...

# Anthropic (optional)
ANTHROPIC_API_KEY=sk-ant-...
```

### 4. Update Configuration

Edit `config/atlas.php` to configure your providers:

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

### 5. Verify Installation

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

// Test embedding
$embedding = Atlas::embeddings()->generate('Hello, world!');
dd(count($embedding)); // Should output: 1536
```

## Common Issues

### Provider Not Configured

If you see "Provider not configured" errors, ensure:
1. The provider key in config matches exactly (e.g., `openai`, not `OpenAI`)
2. The API key environment variable is set
3. You've run `php artisan config:clear` after changes

### Rate Limiting

For high-volume applications, implement rate limiting middleware or use queued operations.

## Next Steps

- [Creating Agents](./creating-agents.md) - Build your first AI agent
- [Creating Tools](./creating-tools.md) - Add callable tools for agents
- [SPEC-Providers](../spec/SPEC-Providers.md) - Provider configuration details
