# Agents

Agents are the core building blocks in Atlas. An agent is a stateless execution unit that combines a provider, model, system prompt, and tools into a reusable configuration.

## What is an Agent?

An agent defines:
- **Provider** — The AI service (OpenAI, Anthropic, etc.)
- **Model** — The specific model to use
- **System Prompt** — Instructions that shape the agent's behavior (supports variable interpolation)
- **Tools** — Custom functions the agent can call during execution
- **Provider Tools** — Built-in provider capabilities (web search, code execution, etc.)
- **Settings** — Temperature, token limits, and execution constraints

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
        return <<<'PROMPT'
        You are a senior customer support specialist for {user_name}.

        ## Customer Context
        - **Name:** {user_name}
        - **Account Tier:** {account_tier}
        - **Customer Since:** {customer_since}

        ## Your Responsibilities
        - Resolve customer inquiries efficiently and empathetically
        - Look up order information when customers ask about purchases
        - Process refunds according to company policy (30-day window, valid reason required)
        - Escalate complex issues you cannot resolve directly

        ## Guidelines
        - Always greet the customer by name and maintain a warm, professional tone
        - For order inquiries, use the `lookup_order` tool to retrieve current status
        - Before processing refunds, verify eligibility using order data
        - If a request falls outside policy, explain clearly and offer alternatives
        - Never share internal system details or other customers' information

        ## Response Style
        - Be concise but thorough—customers value their time
        - Use bullet points for multiple pieces of information
        - End interactions by asking if there's anything else you can help with
        PROMPT;
    }

    public function tools(): array
    {
        return [
            LookupOrderTool::class,
            RefundTool::class,
        ];
    }

    public function temperature(): ?float
    {
        return 0.7;
    }

    public function maxTokens(): ?int
    {
        return 2000;
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
| `tools()` | `[]` | Custom tool classes available to agent |
| `providerTools()` | `[]` | Provider-specific tools (web search, code execution) |
| `temperature()` | `null` | Sampling temperature (0-2) |
| `maxTokens()` | `null` | Maximum response tokens |
| `maxSteps()` | `null` | Maximum tool use iterations |
| `settings()` | `[]` | Additional provider settings |

### Provider Tools

Provider tools are built-in capabilities offered by AI providers. They can be specified as simple strings or with configuration options:

```php
public function providerTools(): array
{
    return [
        // Simple string format
        'web_search',

        // With options
        ['type' => 'code_execution', 'container' => 'python'],

        // With name override
        ['type' => 'web_search', 'name' => 'search', 'max_results' => 5],
    ];
}
```

#### OpenAI Web Search with Domain Restrictions

Restrict web search to specific domains for more controlled results:

```php
public function providerTools(): array
{
    return [
        [
            'type' => 'web_search_preview',
            'search_context_size' => 'medium',
            'user_location' => [
                'type' => 'approximate',
                'country' => 'US',
            ],
            'allowed_domains' => [
                'laravel.com',
                'php.net',
                'stackoverflow.com',
                'github.com',
            ],
        ],
    ];
}
```

Common provider tools include:
- `web_search` / `web_search_preview` — Search the web for current information
- `code_execution` — Execute code in a sandboxed environment
- `file_search` — Search through uploaded files

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
class ResearchAgent extends AgentDefinition
{
    public function key(): string
    {
        return 'research-assistant';
    }

    public function name(): string
    {
        return 'Research Assistant';
    }

    public function description(): ?string
    {
        return 'Researches topics using web search and analyzes findings';
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
        You are a research assistant for {user_name}.
        Your role is to:
        - Search for current information on requested topics
        - Analyze and summarize findings
        - Provide citations for sources
        - Answer follow-up questions accurately
        PROMPT;
    }

    public function tools(): array
    {
        return [
            SaveNoteTool::class,
            CreateReportTool::class,
        ];
    }

    public function providerTools(): array
    {
        return [
            'web_search',
            ['type' => 'code_execution', 'container' => 'python'],
        ];
    }

    public function temperature(): ?float
    {
        return 0.3; // Lower for more accurate research
    }

    public function maxTokens(): ?int
    {
        return 4000;
    }

    public function maxSteps(): ?int
    {
        return 10; // Allow multiple tool iterations
    }
}
```

## Next Steps

- [System Prompts](/core-concepts/system-prompts) — Variable interpolation in prompts
- [Tools](/core-concepts/tools) — Add callable tools to agents
- [Creating Agents](/guides/creating-agents) — Step-by-step guide
