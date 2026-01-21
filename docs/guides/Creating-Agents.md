# Creating Agents

## Goal

Create custom AI agents with specific configurations, system prompts, and tools.

## Prerequisites

- Atlas installed and configured
- Understanding of AI provider concepts (models, system prompts)

## Steps

### 1. Create an Agent Class

Create a new class extending `AgentDefinition`:

```php
<?php

namespace App\Agents;

use Atlasphp\Atlas\Agents\AgentDefinition;

class CustomerSupportAgent extends AgentDefinition
{
    public function key(): string
    {
        return 'customer-support';
    }

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
        return <<<PROMPT
        You are a helpful customer support agent for {company_name}.
        The customer's name is {customer_name}.
        Their account tier is {account_tier}.

        Be professional, helpful, and concise.
        PROMPT;
    }

    public function tools(): array
    {
        return [
            \App\Tools\LookupOrderTool::class,
            \App\Tools\CheckInventoryTool::class,
        ];
    }
}
```

### 2. Register the Agent

In a service provider:

```php
use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use App\Agents\CustomerSupportAgent;

public function boot(): void
{
    $registry = app(AgentRegistryContract::class);
    $registry->register(CustomerSupportAgent::class);
}
```

### 3. Use the Agent

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

// Simple chat
$response = Atlas::chat('customer-support', 'Hello, I need help with my order');
echo $response->text;

// With conversation history
$response = Atlas::chat(
    'customer-support',
    'What about my refund?',
    messages: $previousMessages,
);

// With variables (for system prompt interpolation)
$response = Atlas::forMessages($messages)
    ->withVariables([
        'company_name' => 'Acme Inc',
        'customer_name' => 'Jane Doe',
        'account_tier' => 'premium',
    ])
    ->chat('customer-support', 'I need assistance');
```

## Agent Configuration Options

### Required Methods

| Method | Description |
|--------|-------------|
| `provider()` | AI provider name (e.g., `openai`, `anthropic`) |
| `model()` | Model identifier (e.g., `gpt-4o`, `claude-3-sonnet`) |
| `systemPrompt()` | The system prompt template |

### Optional Methods

| Method | Default | Description |
|--------|---------|-------------|
| `key()` | Generated from class name | Unique identifier for registry |
| `name()` | Generated from class name | Display name |
| `description()` | `null` | Agent description |
| `tools()` | `[]` | Tool classes available to agent |
| `providerTools()` | `[]` | Provider-specific tools |
| `temperature()` | `null` | Sampling temperature |
| `maxTokens()` | `null` | Maximum response tokens |
| `maxSteps()` | `null` | Maximum tool use iterations |
| `settings()` | `[]` | Additional provider settings |

### System Prompt Variables

Use `{variable_name}` placeholders in system prompts:

```php
public function systemPrompt(): string
{
    return 'Hello {user_name}, today is {current_date}.';
}
```

Variables are provided via `ExecutionContext` or `MessageContextBuilder`.

## Common Issues

### Agent Not Found

If you get "Agent not found" errors:
1. Ensure the agent is registered in a service provider's `boot()` method
2. Verify the key matches exactly (case-sensitive)
3. Check that the service provider is loaded

### Variables Not Interpolated

If `{variable}` placeholders appear in output:
1. Ensure variables are passed via context
2. Variable names must match exactly (snake_case)
3. Check for typos in variable names

## Next Steps

- [Creating Tools](./Creating-Tools.md) - Add tools for your agents
- [Multi-Turn Conversations](./Multi-Turn-Conversations.md) - Handle conversation context
- [SPEC-Agents](../spec/SPEC-Agents.md) - Agent module specification
