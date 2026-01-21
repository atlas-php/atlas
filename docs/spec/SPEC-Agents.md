# Agents Module Specification

> **Module:** `Atlasphp\Atlas\Agents`
> **Status:** Implemented (Phase 2)

---

## Overview

The Agents module provides a stateless infrastructure for defining and executing AI agents. Key features:

- Agent definitions with configurable provider, model, system prompt, and tools
- Registry for managing agent instances
- Resolver for flexible agent lookup
- Executor for running agents via Prism
- System prompt builder with variable interpolation

**Design Principle:** Atlas agents are stateless execution units. Consumer applications manage all persistence (conversations, user context, etc.) and pass data via `ExecutionContext`.

---

## Contracts

### AgentContract

Interface that all agent definitions must implement.

```php
interface AgentContract
{
    public function key(): string;
    public function name(): string;
    public function type(): AgentType;
    public function provider(): string;
    public function model(): string;
    public function systemPrompt(): string;
    public function description(): ?string;
    public function tools(): array;
    public function providerTools(): array;
    public function temperature(): ?float;
    public function maxTokens(): ?int;
    public function maxSteps(): ?int;
    public function settings(): array;
}
```

### AgentRegistryContract

Interface for registering and retrieving agents.

```php
interface AgentRegistryContract
{
    public function register(string $agentClass, bool $override = false): void;
    public function registerInstance(AgentContract $agent, bool $override = false): void;
    public function get(string $key): AgentContract;
    public function has(string $key): bool;
    public function all(): array;
}
```

### AgentExecutorContract

Interface for executing agents.

```php
interface AgentExecutorContract
{
    public function execute(
        AgentContract $agent,
        string $input,
        ?ExecutionContext $context = null,
        ?Schema $schema = null,
    ): AgentResponse;
}
```

---

## Enums

### AgentType

Defines the agent execution type.

```php
enum AgentType: string
{
    case Api = 'api';   // Standard API-based execution (default)
    case Cli = 'cli';   // Command-line interface execution (reserved)
}
```

---

## Support Classes

### ExecutionContext

Immutable context for agent execution. Carries conversation history, variables, and metadata.

```php
$context = new ExecutionContext(
    messages: [
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi there!'],
    ],
    variables: ['user_name' => 'John', 'account_type' => 'premium'],
    metadata: ['session_id' => 'abc123'],
);

// Immutable updates
$newContext = $context->withVariables(['user_name' => 'Jane']);
$newContext = $context->mergeMetadata(['trace_id' => 'xyz']);

// Accessors
$context->getVariable('user_name', 'default');
$context->getMeta('session_id');
$context->hasMessages();
```

### AgentResponse

Immutable response from agent execution.

```php
// Factory methods
$response = AgentResponse::text('Hello world');
$response = AgentResponse::structured(['name' => 'John']);
$response = AgentResponse::withToolCalls([...]);
$response = AgentResponse::empty();

// Accessors
$response->hasText();
$response->hasStructured();
$response->hasToolCalls();
$response->totalTokens();
$response->promptTokens();
$response->completionTokens();
$response->get('metadata_key', 'default');

// Immutable updates
$response = $response->withMetadata(['key' => 'value']);
$response = $response->withUsage(['total_tokens' => 100]);
```

---

## Services

### AgentRegistry

Manages agent registrations keyed by agent key.

```php
$registry = app(AgentRegistryContract::class);

// Register by class
$registry->register(MyAgent::class);

// Register instance
$registry->registerInstance(new MyAgent);

// Allow override
$registry->register(MyAgent::class, override: true);

// Query
$registry->has('my-agent');
$registry->get('my-agent');
$registry->all();
$registry->keys();
$registry->count();

// Management
$registry->unregister('my-agent');
$registry->clear();
```

### AgentResolver

Resolves agents from multiple sources.

```php
$resolver = app(AgentResolver::class);

// From instance (passthrough)
$agent = $resolver->resolve(new MyAgent);

// From registry key
$agent = $resolver->resolve('my-agent');

// From class name
$agent = $resolver->resolve(MyAgent::class);
```

Resolution order:
1. Instance passthrough
2. Registry lookup
3. Container instantiation

### SystemPromptBuilder

Builds system prompts with variable interpolation.

```php
$builder = app(SystemPromptBuilder::class);

// Register global variables
$builder->registerVariable('current_date', date('Y-m-d'));

// Add sections
$builder->addSection('rules', '## Rules\nFollow these guidelines...');

// Build prompt
$prompt = $builder->build($agent, $context);
```

**Variable Pattern:** `{variable_name}` - Supports snake_case and camelCase.

### AgentExecutor

Executes agents via Prism with pipeline support.

```php
$executor = app(AgentExecutorContract::class);

// Simple execution
$response = $executor->execute($agent, 'Hello');

// With context
$context = new ExecutionContext(
    messages: $previousMessages,
    variables: ['user_name' => 'John'],
);
$response = $executor->execute($agent, 'Continue our chat', $context);

// Structured output
$schema = new ObjectSchema('person', 'A person', [...]);
$response = $executor->execute($agent, 'Extract the person', null, $schema);
```

---

## AgentDefinition Base Class

Abstract base class providing sensible defaults.

```php
class MyAgent extends AgentDefinition
{
    public function key(): string
    {
        return 'my-agent';
    }

    public function provider(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return 'gpt-4';
    }

    public function systemPrompt(): string
    {
        return 'You are {agent_name}, helping {user_name}.';
    }

    public function tools(): array
    {
        return [SearchTool::class, CalculatorTool::class];
    }

    // Optional overrides
    public function temperature(): ?float
    {
        return 0.7;
    }
}
```

**Defaults provided by AgentDefinition:**
- `key()` - Generated from class name in kebab-case
- `name()` - Generated from class name with spaces
- `type()` - Returns `AgentType::Api`
- `description()` - Returns `null`
- `tools()` - Returns `[]`
- `providerTools()` - Returns `[]`
- `temperature()` - Returns `null`
- `maxTokens()` - Returns `null`
- `maxSteps()` - Returns `null`
- `settings()` - Returns `[]`

---

## Pipeline Hooks

The following pipelines are available for customization:

| Pipeline | Description |
|----------|-------------|
| `agent.before_execute` | Runs before agent execution |
| `agent.after_execute` | Runs after agent execution completes |
| `agent.system_prompt.before_build` | Runs before building system prompt |
| `agent.system_prompt.after_build` | Runs after building system prompt |

---

## Usage Examples

### Basic Agent Definition

```php
class CustomerSupportAgent extends AgentDefinition
{
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
        You are a helpful customer support agent for {company_name}.
        The customer's name is {customer_name}.
        Their account tier is {account_tier}.
        PROMPT;
    }

    public function tools(): array
    {
        return [
            LookupOrderTool::class,
            RefundTool::class,
        ];
    }

    public function maxSteps(): ?int
    {
        return 10;
    }
}
```

### Executing with Context

```php
$executor = app(AgentExecutorContract::class);

$context = new ExecutionContext(
    messages: $conversationHistory,
    variables: [
        'company_name' => 'Acme Inc',
        'customer_name' => 'Jane Doe',
        'account_tier' => 'premium',
    ],
    metadata: [
        'ticket_id' => 'TKT-12345',
    ],
);

$response = $executor->execute(
    new CustomerSupportAgent,
    $userMessage,
    $context,
);

echo $response->text;
```
