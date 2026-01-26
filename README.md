<p align="center">
  <a href="https://atlasphp.org">
    <img src="./atlas-logo-3.png" alt="Atlas logo" height="180">
  </a>
</p>
<p align="center">
    <a href="https://github.com/atlas-php/atlas/actions"><img src="https://github.com/atlas-php/atlas/workflows/Automated%20Tests/badge.svg" alt="Automated Tests"></a>
    <a href="https://codecov.io/gh/atlas-php/atlas"><img src="https://codecov.io/gh/atlas-php/atlas/branch/main/graph/badge.svg" alt="Code Coverage"></a>
    <img src="https://img.shields.io/badge/php-8.2%2B-blue?style=flat-square" alt="PHP Version">
    <img src="https://img.shields.io/badge/laravel-11%2B-orange?style=flat-square" alt="Laravel">
    <img src="https://img.shields.io/badge/license-MIT-green?style=flat-square" alt="License">
</p>
<p align="center">
    📚 <a href="https://atlasphp.org"><strong>Official Documentation</strong></a>
</p>

# 🪐 Atlas

Atlas is a thin Laravel package that brings structure to AI development. It organizes your AI logic into reusable agents, typed tools, and middleware pipelines so you can build production applications without scattering prompts and API calls throughout your codebase.

Built on [Prism PHP](https://prismphp.com), Atlas adds the application layer you need: agent definitions, tool management, prompt templating, and execution pipelines, while Prism handles your LLM communication through provider APIs.

## ✨ Features

- **Reusable Agents** – Define your AI agent behavior and configurations
- **Typed Tools** – Connect agents to your services with validated parameters and structured results
- **MCP Tools** – Integrate external tools from MCP servers via [Prism Relay](https://github.com/prism-php/relay)
- **Dynamic Prompts** – Inject context `{variables}` into system prompts at runtime for personalized interactions
- **Pipelines** – Add logging, auth, rate limiting, or metrics without coupling the codebase
- **Full Prism Access** – Use embeddings, images, speech, moderation, and structured output without limits

## Quick Start

```bash
composer require atlas-php/atlas

# Publish Atlas configuration
php artisan vendor:publish --tag=atlas-config

# Publish Prism configuration (if not already published)
php artisan vendor:publish --tag=prism-config
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
        return <<<PROMPT
        You are a customer support specialist for {company_name}.

        ## Customer Context
        - **Name:** {customer_name}
        - **Account Tier:** {account_tier}

        ## Available Tools
        - **lookup_order** - Retrieve order details by order ID
        - **process_refund** - Process refunds for eligible orders

        ## Guidelines
        - Always greet the customer by name
        - For order inquiries, use `lookup_order` before providing details
        - Before processing refunds, verify eligibility using order data
        PROMPT;
    }

    public function tools(): array
    {
        return [
            LookupOrderTool::class, 
            RefundTool::class
        ];
    }
}
```

### Build a Tool

```php
use Atlasphp\Atlas\Tools\ToolDefinition;
use Atlasphp\Atlas\Tools\Support\ToolContext;
use Atlasphp\Atlas\Tools\Support\ToolParameter;
use Atlasphp\Atlas\Tools\Support\ToolResult;

class LookupOrderTool extends ToolDefinition
{
    public function __construct(
        private OrderService $orders
    ) {}

    public function name(): string
    {
        return 'lookup_order';
    }

    public function description(): string
    {
        return 'Look up order details by order ID';
    }

    public function parameters(): array
    {
        return [
            ToolParameter::string('order_id', 'The order ID to look up', required: true),
        ];
    }

    public function handle(array $params, ToolContext $context): ToolResult
    {
        $order = $this->orders->find($params['order_id']);

        if (! $order) {
            return ToolResult::error('Order not found');
        }

        return ToolResult::json($order->toArray());
    }
}
```

### Chat with the Agent

```php
$response = Atlas::agent(SupportAgent::class)
    ->withVariables([
        'company_name' => 'Acme', 
        'customer_name' => 'Sarah',
        'account_tier' => 'Premium',
    ])
    ->chat('Where is my order #12345?');

echo $response->text;
```

## Why Atlas?

**The problem:** Prompts scattered across controllers, duplicated configurations, businesses logic tightly coupled with tools, and no consistent way to add logging, validation or even proper error handling.

**Atlas decouples your businesses logic:**

- **Agents** - AI configurations live in dedicated classes, not inline across your codebase.
- **Tools** - Business logic stays in tool classes with typed parameters. Agents call tools; tools call your services.
- **Pipelines** - Add logging, auth, or metrics to all Prism/Atlas operations without coupling the codebase.
- **Testable** - Mock agents and fake tool responses with standard Laravel testing patterns.

Atlas doesn't replace Prism. It organizes how you use Prism in real applications.

## Documentation

📚 **[atlasphp.org](https://atlasphp.org)** - Full guides, API reference, and examples.

- [Getting Started](https://atlasphp.org/getting-started/installation.html) - Installation and configuration
- [Agents](https://atlasphp.org/core-concepts/agents.html) - Define reusable AI configurations
- [Tools](https://atlasphp.org/core-concepts/tools.html) - Connect agents to your application
- [MCP Integration](https://atlasphp.org/capabilities/mcp.html) - External tools from MCP servers
- [Pipelines](https://atlasphp.org/core-concepts/pipelines.html) - Extend with middleware

## Testing and Code Quality

Atlas uses several tools to maintain high code quality:

```bash
composer check
```

| Tool                                             | Purpose                                                                                                                |
|--------------------------------------------------|------------------------------------------------------------------------------------------------------------------------|
| [Pest](https://pestphp.com)                      | Testing framework                                                                                                      |
| [Larastan](https://github.com/larastan/larastan) | Static analysis                                                                                                        |
| [Laravel Pint](https://laravel.com/docs/pint)    | Code style                                                                                                             |
| [Codecov](https://codecov.io/gh/atlas-php/atlas) | [![codecov](https://codecov.io/gh/atlas-php/atlas/branch/main/graph/badge.svg)](https://codecov.io/gh/atlas-php/atlas) |

## Contributing

We welcome contributions!

Support the community by giving a GitHub star ⭐️. Thank you!

Please see our [Contributing Guide](.github/CONTRIBUTING.md) for details.

## License

Atlas is open-sourced software licensed under the [MIT license](LICENSE).
