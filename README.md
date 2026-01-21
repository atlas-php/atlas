# Atlas

[![Build](https://github.com/atlas-php/atlas/workflows/Build/badge.svg)](https://github.com/atlas-php/atlas/actions)
[![PHP Version](https://img.shields.io/badge/php-8.4%2B-blue)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/laravel-12.x-orange)](https://laravel.com/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

A Laravel package for AI-powered features with provider abstraction. Build AI agents, execute tools, generate embeddings, create images, and synthesize speech through a clean, stateless API.

## Features

- **Agent System** - Define AI agents with specific configurations, system prompts, and tools
- **Tool Execution** - Create callable tools that agents can invoke during execution
- **Multi-Provider Support** - OpenAI, Anthropic, and other providers through Prism
- **Embeddings** - Generate text embeddings for semantic search and RAG
- **Image Generation** - Create images with DALL-E and other providers
- **Speech** - Text-to-speech and speech-to-text capabilities
- **Pipeline System** - Extensible middleware for customization
- **Stateless Design** - Your application manages all persistence

## Requirements

- PHP 8.4+
- Laravel 12.x

## Installation

```bash
composer require atlas-php/atlas
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=atlas-config
```

## Quick Start

### Chat with an Agent

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

// Simple chat
$response = Atlas::chat('support-agent', 'Hello, I need help!');
echo $response->text;

// With conversation history
$response = Atlas::forMessages($previousMessages)
    ->withVariables(['user_name' => 'Alice'])
    ->chat('support-agent', 'Continue our conversation');
```

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
        return 'You are a helpful support agent. The user is {user_name}.';
    }

    public function tools(): array
    {
        return [LookupOrderTool::class];
    }
}
```

### Create a Tool

```php
use Atlasphp\Atlas\Tools\ToolDefinition;
use Atlasphp\Atlas\Tools\Support\ToolParameter;
use Atlasphp\Atlas\Tools\Support\ToolResult;

class LookupOrderTool extends ToolDefinition
{
    public function name(): string
    {
        return 'lookup_order';
    }

    public function description(): string
    {
        return 'Look up order details by ID';
    }

    public function parameters(): array
    {
        return [
            ToolParameter::string('order_id', 'The order ID')->required(),
        ];
    }

    public function handle(array $arguments, ToolContext $context): ToolResult
    {
        $order = Order::find($arguments['order_id']);
        return ToolResult::json($order->toArray());
    }
}
```

### Generate Embeddings

```php
$embedding = Atlas::embed('Hello, world!');
// [0.123, 0.456, ...]

$embeddings = Atlas::embedBatch(['Text 1', 'Text 2']);
```

### Generate Images

```php
$result = Atlas::image('openai', 'dall-e-3')
    ->size('1024x1024')
    ->generate('A sunset over mountains');

echo $result['url'];
```

### Text-to-Speech

```php
$result = Atlas::speech('openai', 'tts-1')
    ->voice('nova')
    ->speak('Hello, world!');

file_put_contents('audio.mp3', base64_decode($result['audio']));
```

## Configuration

Configure your providers in `config/atlas.php`:

```php
return [
    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
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
    ],
];
```

## Documentation

- [Installation Guide](docs/guides/Installation.md)
- [Creating Agents](docs/guides/Creating-Agents.md)
- [Creating Tools](docs/guides/Creating-Tools.md)
- [Multi-Turn Conversations](docs/guides/Multi-Turn-Conversations.md)
- [Extending Atlas](docs/guides/Extending-Atlas.md)

Technical specifications are available in [docs/spec/](docs/spec/).

## Philosophy

Atlas is designed around these principles:

1. **Stateless** - Atlas doesn't manage conversations or state. Your application owns all persistence.
2. **Provider Agnostic** - Switch between OpenAI, Anthropic, and others without code changes.
3. **Composable** - Mix agents, tools, and pipelines to build complex AI workflows.
4. **Testable** - Everything is mockable and testable without real API calls.

## Testing

```bash
composer test        # Run tests
composer lint        # Fix code style
composer analyse     # Static analysis
composer check       # All checks
```

## License

MIT License. See [LICENSE](LICENSE) for details.
