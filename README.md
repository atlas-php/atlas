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

Atlas is a Laravel package for building AI-powered applications with structure and scale. Define agents once, use them everywhere—with tools, dynamic prompts, and middleware that keeps your AI logic clean and testable.

Built on [Prism PHP](https://prismphp.com), Atlas adds the application layer: agent registries, tool management, prompt templating, and execution pipelines. Prism handles the LLM communication; Atlas handles your business logic.

## Features

- **Reusable Agents** — Define AI configurations once, resolve by key or class anywhere in your app
- **Typed Tools** — Connect agents to your services with validated parameters and structured results
- **Dynamic Prompts** — Interpolate `{variables}` at runtime for personalized, context-aware interactions
- **Pipelines** — Add logging, auth, rate limiting, or metrics without touching agent code
- **Full Prism Access** — Use embeddings, images, speech, moderation, and all Prism features directly

## Quick Start

```bash
composer require atlas-php/atlas

php artisan vendor:publish --tag=atlas-config
```

### Define an Agent

```php
use Atlasphp\Atlas\Agents\AgentDefinition;

class SupportAgent extends AgentDefinition
{
    public function provider(): ?string
    {
        return 'anthropic';
    }

    public function model(): ?string
    {
        return 'claude-sonnet-4-20250514';
    }

    public function systemPrompt(): ?string
    {
        return 'You are a support agent for {company}. Help {user_name} with their questions.';
    }

    public function tools(): array
    {
        return [LookupOrderTool::class, RefundTool::class];
    }
}
```

### Use It

```php
$response = Atlas::agent(SupportAgent::class)
    ->withVariables(['company' => 'Acme', 'user_name' => 'Sarah'])
    ->chat('Where is my order #12345?');

echo $response->text;
```

## Why Atlas?

| You want to... | Atlas provides... |
|----------------|-------------------|
| Reuse AI configurations across your app | Agent registry with key/class/instance resolution |
| Give agents access to your services | Typed tools with parameter validation |
| Personalize prompts per user/request | Variable interpolation in system prompts |
| Add observability without coupling | Pipeline middleware for logging, metrics, auth |
| Use embeddings, images, or speech | Direct passthrough to all Prism capabilities |

## Documentation

📚 **[atlasphp.org](https://atlasphp.org)** — Full guides, API reference, and examples.

- [Getting Started](https://atlasphp.org/getting-started/installation.html) — Installation and configuration
- [Agents](https://atlasphp.org/core-concepts/agents.html) — Define reusable AI configurations
- [Tools](https://atlasphp.org/core-concepts/tools.html) — Connect agents to your application
- [Pipelines](https://atlasphp.org/core-concepts/pipelines.html) — Extend with middleware

## Contributing

We welcome contributions! Please see our [Contributing Guide](.github/CONTRIBUTING.md) for details.

## License

Atlas is open-sourced software licensed under the [MIT license](LICENSE).
