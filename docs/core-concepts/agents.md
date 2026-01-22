# Agents

Agents are the core building blocks in Atlas. An agent is a stateless execution unit that combines a provider, model, system prompt, and tools into a reusable configuration.

## What is an Agent?

An agent defines:
- **Provider** — The AI service (OpenAI, Anthropic, etc.)
- **Model** — The specific model to use
- **System Prompt** — Instructions that shape the agent's behavior
- **Tools** — Functions the agent can call during execution

```php
use Atlasphp\Atlas\Agents\AgentDefinition;

class CustomerSupportAgent extends AgentDefinition
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
        return 'You are a helpful customer support agent for {company_name}.';
    }

    public function tools(): array
    {
        return [
            LookupOrderTool::class,
            RefundTool::class,
        ];
    }
}
```

## Stateless Design

Atlas agents are **stateless execution units**. They don't store conversation history or user context internally. Your application:

1. Stores conversation history in your preferred storage
2. Passes history to Atlas on each request via `ExecutionContext`
3. Receives the response and updates storage

This gives you complete control over persistence, trimming, summarization, and replay logic.

## Agent Registry

Register agents for lookup by key:

```php
use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;

$registry = app(AgentRegistryContract::class);

// Register by class
$registry->register(CustomerSupportAgent::class);

// Register with override
$registry->register(CustomerSupportAgent::class, override: true);

// Register an instance directly
$registry->registerInstance(new CustomerSupportAgent());
```

## Using Agents

Agents can be referenced three ways:

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

// By registry key
$response = Atlas::chat('customer-support', 'Hello');

// By class name
$response = Atlas::chat(CustomerSupportAgent::class, 'Hello');

// By instance
$response = Atlas::chat(new CustomerSupportAgent(), 'Hello');
```

## Agent Resolution

Atlas resolves agents in this order:

1. **Instance** — If you pass an agent instance, it's used directly
2. **Registry** — Looks up by key in the agent registry
3. **Container** — Instantiates the class via Laravel's container

## Configuration Options

### Required Methods

| Method | Description |
|--------|-------------|
| `provider()` | AI provider name (`openai`, `anthropic`) |
| `model()` | Model identifier (`gpt-4o`, `claude-3-sonnet`) |
| `systemPrompt()` | The system prompt template |

### Optional Methods

| Method | Default | Description |
|--------|---------|-------------|
| `key()` | Class name in kebab-case | Unique identifier for registry |
| `name()` | Class name with spaces | Display name |
| `description()` | `null` | Agent description |
| `tools()` | `[]` | Tool classes available to agent |
| `providerTools()` | `[]` | Provider-specific tools |
| `temperature()` | `null` | Sampling temperature (0-2) |
| `maxTokens()` | `null` | Maximum response tokens |
| `maxSteps()` | `null` | Maximum tool use iterations |
| `settings()` | `[]` | Additional provider settings |

## Agent Types

```php
use Atlasphp\Atlas\Agents\Enums\AgentType;

public function type(): AgentType
{
    return AgentType::Api; // Default
}
```

| Type | Description |
|------|-------------|
| `Api` | Standard API-based execution (default) |
| `Cli` | Command-line interface execution (reserved) |

## Example: Complete Agent

```php
class AnalysisAgent extends AgentDefinition
{
    public function key(): string
    {
        return 'sentiment-analyzer';
    }

    public function name(): string
    {
        return 'Sentiment Analyzer';
    }

    public function description(): ?string
    {
        return 'Analyzes text sentiment and emotions';
    }

    public function provider(): string
    {
        return 'anthropic';
    }

    public function model(): string
    {
        return 'claude-3-sonnet';
    }

    public function systemPrompt(): string
    {
        return <<<PROMPT
        You are an expert sentiment analyst.
        Analyze the given text and identify:
        - Overall sentiment (positive, negative, neutral)
        - Key emotions present
        - Confidence level
        PROMPT;
    }

    public function tools(): array
    {
        return [];
    }

    public function temperature(): ?float
    {
        return 0.3; // Lower for more consistent analysis
    }

    public function maxTokens(): ?int
    {
        return 500;
    }
}
```

## Next Steps

- [System Prompts](/core-concepts/system-prompts) — Variable interpolation in prompts
- [Tools](/core-concepts/tools) — Add callable tools to agents
- [Creating Agents](/guides/creating-agents) — Step-by-step guide
