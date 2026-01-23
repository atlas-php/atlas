<p align="center">
  <a href="https://atlasphp.org">
    <img src="./atlas-logo-3.png" alt="Atlas logo" height="180">
  </a>
</p>
<p align="center">
    <a href="https://github.com/atlas-php/atlas/actions"><img src="https://github.com/atlas-php/atlas/workflows/Automated%20Tests/badge.svg" alt="Automated Tests"></a>
    <a href="https://codecov.io/gh/atlas-php/atlas"><img src="https://codecov.io/gh/atlas-php/atlas/branch/main/graph/badge.svg" alt="Code Coverage"></a>
    <img src="https://img.shields.io/badge/php-8.4%2B-blue?style=flat-square" alt="PHP Version">
    <img src="https://img.shields.io/badge/laravel-12.x-orange?style=flat-square" alt="Laravel">
    <img src="https://img.shields.io/badge/license-MIT-green?style=flat-square" alt="License">
</p>
<p align="center">
    📚 <a href="https://atlasphp.org"><strong>Official Documentation</strong></a>
</p>

# Atlas

Atlas is a Laravel package for building AI-powered applications with structure and scale. It provides reusable agents, typed tools, system prompt templating, and execution pipelines; all through a clean, stateless API. 

```php
$response = Atlas::agent('support')->chat('I need help with my order');
```

Built on [Prism PHP](https://github.com/prism-php/prism), **Atlas** lets you focus on your application logic instead of wiring AI infrastructure.

---

## Why Atlas?

Atlas handles **application-level AI concerns** while Prism handles **LLM communication**.

* Build reusable, composable agents with acces to tools
* Use dynamic prompts to customize behavior based on user context
* Extend behavior (logging, auth, metrics) without touching the core

### Note from the Author
> _Atlas has been built through deliberate iteration over the past year. This RC4 release reflects a stable, battle-tested core already running in large-scale production. Atlas is intentionally stateless, with persistence and orchestration planned for Nexus, a companion package in active development. Feedback and issues are always welcome._
>
> _— TM_

---

## Table of Contents

* [What's Included](#whats-included)
* [Installation & Quick Start](#installation--quick-start)
* [Tools](#tools)
* [Pipelines](#pipelines)
* [Conversations](#conversations)
* [Embeddings](#embeddings)
* [Images](#images)
* [Speech](#speech)
* [Moderation](#moderation)
* [Configuration](#configuration)
* [Code Quality & Testing](#code-quality--testing)
* [Documentation](#documentation)

---

## What's included

| Feature             | What it does                                                   | Learn more                                             |
|---------------------|----------------------------------------------------------------|--------------------------------------------------------|
| **Agent Registry**  | Define agents once, use anywhere by key, class, or instance    | [Guide](docs/guides/creating-agents.md)                |
| **Tool Registry**   | Connect agents to your business services with typed parameters | [Guide](docs/guides/creating-tools.md)                 |
| **Dynamic Prompts** | Variables like `{user_name}` interpolate at runtime            | [Guide](docs/guides/creating-agents.md#system-prompts) |
| **Pipelines**       | Extend Atlas for logging, auth, metrics—without coupling       | [Guide](docs/guides/extending-atlas.md)                |
| **Multi-Provider**  | OpenAI, Anthropic, others. Swap via config                     | [Guide](docs/guides/installation.md#configuration)     |

Beyond chat: [Embeddings](#embeddings) · [Images](#images) · [Speech](#speech) · [Moderation](#moderation)

---

## Installation & Quick Start

```bash
composer require atlas-php/atlas

php artisan vendor:publish --tag=atlas-config
```

### Define an agent:

```php
use Atlasphp\Atlas\Agents\AgentDefinition;

class SupportAgent extends AgentDefinition
{
    public function provider(): ?string { return 'openai'; }

    public function model(): ?string { return 'gpt-4o'; }

    public function systemPrompt(): ?string {
        return 'You help customers for {company}.';
    }

    public function tools(): array {
        return [
            LookupOrderTool::class
        ];
    }
}
```

#### Register and use:

```php
$agents->register(SupportAgent::class);

$response = Atlas::agent('support')
    ->withVariables(['company' => 'Acme'])
    ->chat('Where is my order?');
```

---

## Tools

Tools connect agents to your application services.

```php

use Atlasphp\Atlas\Tools\ToolDefinition;
use Atlasphp\Atlas\Tools\Support\{ToolParameter, ToolResult, ToolContext};

class LookupOrderTool extends ToolDefinition
{
    public function name(): string { return 'lookup_order'; }
    public function description(): string { return 'Look up order by ID'; }

    public function parameters(): array
    {
        return [
            ToolParameter::string('order_id', 'The order ID', required: true)
        ];
    }

    public function handle(array $args, ToolContext $context): ToolResult
    {
        $order = Order::find($args['order_id']);
        return $order ? ToolResult::json($order) : ToolResult::error('Not found');
    }
}
```

---

## Pipelines

Extend Atlas without modifying or coupling with the core code.

```php
use Atlasphp\Atlas\Foundation\Contracts\PipelineContract;

class AuditMiddleware implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        $result = $next($data);
        AuditLog::create([
            'agent' => $data['agent']->key(),
            'tool_calls' => $result->toolCalls ?? [],
        ]);
        return $result;
    }
}
```

---

## Conversations

Atlas is stateless. You are responsible for storing and passing conversation history on each request. This gives you full control over persistence, trimming, summarization, and replay logic.

Messages must follow the standard chat format (`role` + `content`) and are passed in the order they occurred.

```php
$messages = [
    [
        'role' => 'user',
        'content' => 'I placed an order yesterday.',
    ],
    [
        'role' => 'assistant',
        'content' => 'Can you share your order number?',
    ],
    [
        'role' => 'user',
        'content' => 'Order #12345.',
    ],
];

$response = Atlas::agent('support')
    ->withMessages($messages)
    ->chat('Where is my package?');
```

---

## Embeddings

Convert text into vector representations for semantic search, similarity matching, and RAG applications.

```php
$vector = Atlas::embeddings()->generate('Hello world');
```

---

## Images

Generate images from text prompts using DALL-E or other supported providers.

```php
$result = Atlas::image('openai', 'dall-e-3')->generate('A sunset over mountains');
```

---

## Speech

Convert text to speech or transcribe audio to text with a simple, fluent API.

```php
// Text-to-speech
$result = Atlas::speech()->generate('Hello world');

// Speech-to-text
$result = Atlas::speech()->transcribe('/path/to/audio.mp3');
```

---

## Moderation

Moderate content for safety using OpenAI's moderation API.

```php
$result = Atlas::moderation()->moderate('Text to check');

if ($result->isFlagged()) {
    $categories = $result->categories();
    $scores = $result->categoryScores();
}

// Batch moderation
$result = Atlas::moderation()->moderate(['Text 1', 'Text 2']);
```

---

## Configuration

Atlas supports all major AI providers out of the box.

```php
return [
    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
        ],
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
        ],
        'mistral' => [
            'api_key' => env('MISTRAL_API_KEY'),
        ],
        'groq' => [
            'api_key' => env('GROQ_API_KEY'),
        ],
        'xai' => [
            'api_key' => env('XAI_API_KEY'),
        ],
        // Custom or self-hosted providers
        'ollama' => [
            'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        ],
    ],
];
```

---

## Code Quality & Testing

Atlas maintains high code quality standards with comprehensive tooling:

| Tool | Purpose |
|------|---------|
| [Pest PHP](https://pestphp.com) | Elegant testing framework with 450+ tests |
| [Laravel Pint](https://laravel.com/docs/pint) | Code style enforcement (PSR-12) |
| [Larastan](https://github.com/larastan/larastan) | Static analysis at max level |
| [Codecov](https://codecov.io/gh/atlas-php/atlas) | Code coverage tracking |

```bash
composer test      # Run tests
composer lint      # Fix code style
composer analyse   # Run static analysis
composer check     # Run all checks
```

---

## Documentation

**[Official Documentation](https://atlasphp.org)** — Full guides, API reference, and examples.

## Contributing

We welcome contributions! Please see our [Contributing Guide](.github/CONTRIBUTING.md) for details.

## License

Atlas is open-sourced software licensed under the [MIT license](LICENSE).
