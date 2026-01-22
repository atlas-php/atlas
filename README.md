<p align="center">
    <a href="https://github.com/atlas-php/atlas"><img src="https://github.com/atlas-php/atlas/workflows/Build/badge.svg" alt="Tests"></a>
    <img src="https://img.shields.io/badge/php-8.4%2B-blue?style=flat-square" alt="PHP Version">
    <img src="https://img.shields.io/badge/laravel-12.x-orange?style=flat-square" alt="Laravel">
    <img src="https://img.shields.io/badge/license-MIT-green?style=flat-square" alt="License">
</p>

# Atlas

Atlas is a Laravel package for building AI-powered applications with structure and scale. It provides reusable agents, typed tools, system prompt templating, and execution pipelines—all through a clean, stateless API. Built on [Prism PHP](https://github.com/prism-php/prism), Atlas lets you focus on application logic instead of wiring AI infrastructure.

```php
$response = Atlas::chat('support-agent', 'I need help with my order');
```

---

## Why Atlas?

Atlas handles **application-level AI concerns** while Prism handles **LLM communication**.

* Build reusable, composable agents—not one-off prompts
* Keep AI logic stateless, testable, and framework-native.
* Extend behavior (logging, auth, metrics) without touching the core

### Note from the Author
> _Atlas has gone through many iterations over the past year. This RC4 release is stable, battle-tested, and already running in large-scale production. Atlas is intentionally stateless; persistence and orchestration will live in Nexus, a companion package currently in development. Feedback and issues are always welcome. — TM_

---

## Table of Contents

* [What You Get](#what-you-get)
* [Installation & Quick Start](#installation--quick-start)
* [Tools](#tools)
* [Pipelines](#pipelines)
* [Conversations](#conversations)
* [Embeddings](#embeddings)
* [Images](#images)
* [Speech](#speech)
* [Configuration](#configuration)
* [Testing](#testing)
* [Documentation](#documentation)

---

## What You Get

| Feature             | What it does                                                   | Learn more                                             |
|---------------------|----------------------------------------------------------------|--------------------------------------------------------|
| **Agent Registry**  | Define agents once, use anywhere by key, class, or instance    | [Guide](docs/guides/Creating-Agents.md)                |
| **Tool Registry**   | Connect agents to your business services with typed parameters | [Guide](docs/guides/Creating-Tools.md)                 |
| **Dynamic Prompts** | Variables like `{user_name}` interpolate at runtime            | [Guide](docs/guides/Creating-Agents.md#system-prompts) |
| **Pipelines**       | Extend Atlas for logging, auth, metrics—without coupling       | [Guide](docs/guides/Extending-Atlas.md)                |
| **Multi-Provider**  | OpenAI, Anthropic, others. Swap via config                     | [Guide](docs/guides/Installation.md#configuration)     |

Beyond chat: [Embeddings](#embeddings) · [Images](#images) · [Speech](#speech)

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
    public function provider(): string { return 'openai'; }
    
    public function model(): string { return 'gpt-4o'; }
    
    public function systemPrompt(): string {
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

$response = Atlas::withVariables(['company' => 'Acme'])
    ->chat('support', 'Where is my order?');
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

$response = Atlas::forMessages($messages)
    ->chat('support', 'Where is my package?');
```

---

## Embeddings

```php
$vector = Atlas::embed('Hello world');
```

---

## Images

```php
$result = Atlas::image('openai', 'dall-e-3')->generate('A sunset over mountains');
```

---

## Speech

```php
$result = Atlas::speech()->transcribe('/path/to/audio.mp3');
```

---

## Configuration

```php
return [
    'providers' => [
        'openai' => ['api_key' => env('OPENAI_API_KEY')],
        'anthropic' => ['api_key' => env('ANTHROPIC_API_KEY')],
    ],
];
```

---

## Testing

```bash
composer test
composer check
```

---

## Documentation

| Guide                                                               | Description                 |
|---------------------------------------------------------------------|-----------------------------|
| [Installation](docs/guides/Installation.md)                         | Setup and configuration     |
| [Creating Agents](docs/guides/Creating-Agents.md)                   | Agent definitions           |
| [Creating Tools](docs/guides/Creating-Tools.md)                     | Tool parameters             |
| [Multi-Turn Conversations](docs/guides/Multi-Turn-Conversations.md) | Conversation handling       |
| [Extending Atlas](docs/guides/Extending-Atlas.md)                   | Pipelines                   |


---

## Requirements

* PHP 8.4+
* Laravel 12.x

## License

MIT
