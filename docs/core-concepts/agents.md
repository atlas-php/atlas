# Agents

Agents are reusable AI configurations that combine a provider, model, system prompt, and tools into a single class.

## What is an Agent?

An agent can define:
- **Provider** — The AI provider (`openai`, `anthropic`, etc.) - optional, uses config default
- **Model** — The model to use (`gpt-4o`, `claude-sonnet-4-20250514`, etc.)
- **System Prompt** — Instructions with `{variable}` interpolation support
- **Tools** — Custom tool classes the agent can invoke
- **Provider Tools** — Built-in provider capabilities (web search, code execution)
- **Options** — Temperature, max tokens, max steps, client options, provider options
- **Schema** — For structured output responses

All methods have sensible defaults. Override only what you need.

## Example: Basic Agent

```php
use Atlasphp\Atlas\Agents\AgentDefinition;

class CustomerSupportAgent extends AgentDefinition
{
    public function provider(): ?string
    {
        return 'openai';
    }

    public function model(): ?string
    {
        return 'gpt-4o';
    }

    public function systemPrompt(): ?string
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

## Agent Registry

Agents are automatically discovered and registered from your configured directory (default: `app/Agents`). Just create your agent class and it's ready to use:

```php
// app/Agents/CustomerSupportAgent.php
class CustomerSupportAgent extends AgentDefinition
{
    // ... agent definition
}

// Use immediately - no manual registration needed
$response = Atlas::agent('customer-support')->chat('Hello');
```

Configure auto-discovery in `config/atlas.php`:

```php
'agents' => [
    'path' => app_path('Agents'),
    'namespace' => 'App\\Agents',
],
```

## Manual Registration

If you prefer manual control or need to register agents from other locations:

```php
use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;

$registry = app(AgentRegistryContract::class);

// Register by class
$registry->register(CustomerSupportAgent::class);

// Register with override
$registry->register(CustomerSupportAgent::class, override: true);

// Register an instance directly
$registry->registerInstance(new CustomerSupportAgent());

// Query agents
$registry->has('customer-support');
$registry->get('customer-support');
$registry->all();
```

## Using Agents

Agents can be referenced three ways:

```php
use Atlasphp\Atlas\Atlas;

// By registry key
$response = Atlas::agent('customer-support')->chat('Hello');

// By class name
$response = Atlas::agent(CustomerSupportAgent::class)->chat('Hello');

// By instance
$response = Atlas::agent(new CustomerSupportAgent())->chat('Hello');
```

## Configuration Options

All methods have sensible defaults. Override only what you need.

<div class="full-width-table">

| Method | Default | Description |
|--------|---------|-------------|
| `provider()` | `null` (uses config default) | AI provider name (`openai`, `anthropic`) |
| `model()` | `null` | Model identifier (`gpt-4o`, `claude-sonnet-4-20250514`) |
| `systemPrompt()` | `null` | The system prompt template with `{variable}` support |
| `key()` | Class name in kebab-case | Unique identifier for registry |
| `name()` | Class name with spaces | Display name |
| `description()` | `null` | Agent description |
| `tools()` | `[]` | Custom tool classes available to agent |
| `providerTools()` | `[]` | Provider-specific tools (web search, code execution) |
| `temperature()` | `null` | Sampling temperature (0-2) |
| `maxTokens()` | `null` | Maximum response tokens |
| `maxSteps()` | `null` | Maximum tool use iterations |
| `clientOptions()` | `[]` | HTTP client options (timeout, retries) |
| `providerOptions()` | `[]` | Provider-specific options |
| `schema()` | `null` | Schema for structured output |

</div>

## Provider Options

Use `providerOptions()` to configure provider-specific features that aren't part of the standard API.

### Anthropic Cache Control

Enable prompt caching to reduce costs for repeated system prompts:

```php
public function providerOptions(): array
{
    return [
        'cacheType' => 'ephemeral',
    ];
}
```

### Anthropic Extended Thinking

Enable Claude's extended thinking for complex reasoning tasks:

```php
public function providerOptions(): array
{
    return [
        'thinking' => [
            'enabled' => true,
            'budget_tokens' => 5000,
        ],
    ];
}
```

### OpenAI Specific Options

```php
public function providerOptions(): array
{
    return [
        'response_format' => ['type' => 'json_object'],
        'seed' => 12345,  // For reproducible outputs
    ];
}
```

Provider options are passed directly to the underlying Prism provider. See your provider's documentation for available options.

## Provider Tools

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

### OpenAI Web Search with Domain Restrictions

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

## Structured Output with Schema

Define a `schema()` method to always return structured data from the agent:

```php
use Atlasphp\Atlas\Agents\AgentDefinition;
use Atlasphp\Atlas\Schema\Schema;
use Prism\Prism\Contracts\Schema as PrismSchema;

class SentimentAnalyzerAgent extends AgentDefinition
{
    public function provider(): ?string
    {
        return 'openai';
    }

    public function model(): ?string
    {
        return 'gpt-4o';
    }

    public function systemPrompt(): ?string
    {
        return 'Analyze the sentiment of the provided text.';
    }

    public function schema(): ?PrismSchema
    {
        return Schema::object('sentiment_analysis', 'Sentiment analysis result')
            ->enum('sentiment', 'The detected sentiment', ['positive', 'negative', 'neutral'])
            ->number('confidence', 'Confidence score from 0 to 1')
            ->string('reasoning', 'Brief explanation of the sentiment')
            ->build();
    }
}
```

Usage:

```php
$response = Atlas::agent('sentiment-analyzer')->chat('I absolutely love this product!');

$response->structured['sentiment'];   // "positive"
$response->structured['confidence'];  // 0.95
$response->structured['reasoning'];   // "The text expresses strong enthusiasm..."
```

You can also use Prism schema classes directly:

```php
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\EnumSchema;

public function schema(): ?PrismSchema
{
    return new ObjectSchema(
        name: 'sentiment_analysis',
        description: 'Sentiment analysis result',
        properties: [
            new EnumSchema('sentiment', 'The detected sentiment', ['positive', 'negative', 'neutral']),
            new NumberSchema('confidence', 'Confidence score from 0 to 1'),
            new StringSchema('reasoning', 'Brief explanation'),
        ],
        requiredFields: ['sentiment', 'confidence', 'reasoning'],
    );
}
```

See [Structured Output](/capabilities/structured-output) for more schema options.

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

    public function provider(): ?string
    {
        return 'openai';
    }

    public function model(): ?string
    {
        return 'gpt-4o';
    }

    public function systemPrompt(): ?string
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

## Agent Decorators

Decorators allow you to dynamically modify agent behavior at runtime without changing agent classes. This is useful for:
- Adding logging to specific agents
- Injecting request-specific tools
- Applying feature flags
- Adding cross-cutting concerns

### Creating a Decorator

Extend `AgentDecorator` and override the methods you want to modify:

```php
use Atlasphp\Atlas\Agents\Support\AgentDecorator;
use Atlasphp\Atlas\Agents\Contracts\AgentContract;

class LoggingDecorator extends AgentDecorator
{
    /**
     * Determine which agents this decorator applies to.
     */
    public function appliesTo(AgentContract $agent): bool
    {
        return true; // Apply to all agents
    }

    /**
     * Optionally define a priority (higher runs first).
     */
    public function priority(): int
    {
        return 100;
    }

    /**
     * Override any AgentContract method to modify behavior.
     */
    public function systemPrompt(): ?string
    {
        $original = $this->agent->systemPrompt();
        return $original . "\n\n[Logging enabled for this session]";
    }
}
```

### Registering Decorators

Register decorators in a service provider:

```php
use Atlasphp\Atlas\Agents\Services\AgentExtensionRegistry;

public function boot(): void
{
    $registry = app(AgentExtensionRegistry::class);

    $registry->registerDecorator(new LoggingDecorator());
    $registry->registerDecorator(new PremiumToolsDecorator());
}
```

### Selective Application

Apply decorators only to specific agents:

```php
class PremiumOnlyDecorator extends AgentDecorator
{
    public function appliesTo(AgentContract $agent): bool
    {
        return str_starts_with($agent->key(), 'premium-');
    }

    public function tools(): array
    {
        return array_merge($this->agent->tools(), [
            AdvancedAnalysisTool::class,
            PriorityQueueTool::class,
        ]);
    }
}
```

### Decorator Priority

Decorators are applied in priority order (highest first). When multiple decorators apply, they wrap the agent in layers:

```php
// Priority 100 decorator wraps first
// Priority 50 decorator wraps second (wraps the first wrapper)
// Final key: "outer:inner:original-agent"
```

### Querying the Extension Registry

```php
$registry = app(AgentExtensionRegistry::class);

$registry->hasDecorators();   // true if any decorators registered
$registry->decoratorCount();  // number of registered decorators
$registry->clearDecorators(); // remove all decorators
```

## API Reference

```php
// AgentDefinition methods (override in your agent class)
public function provider(): ?string;
public function model(): ?string;
public function systemPrompt(): ?string;
public function key(): string;
public function name(): string;
public function description(): ?string;
public function tools(): array;
public function providerTools(): array;
public function temperature(): ?float;
public function maxTokens(): ?int;
public function maxSteps(): ?int;
public function clientOptions(): array;
public function providerOptions(): array;
public function schema(): ?PrismSchema;

// Agent execution fluent API (PendingAgentRequest)
Atlas::agent(string|AgentContract $agent)
    ->withMessages(array $messages)              // Conversation history
    ->withVariables(array $variables)            // System prompt variables
    ->withMetadata(array $metadata)              // Pipeline metadata
    ->withProvider(string $provider, ?string $model = null)  // Override provider
    ->withModel(string $model)                   // Override model
    ->withMedia(Image|Document|Audio|Video|array $media)     // Attach media
    ->withSchema(SchemaBuilder|ObjectSchema $schema)         // Structured output
    ->usingAutoMode()                            // Auto schema mode (default)
    ->usingNativeMode()                          // Native JSON schema mode
    ->usingJsonMode()                            // JSON mode (for optional fields)
    ->chat(string $input, array $attachments = []): PrismResponse|StructuredResponse;
    ->stream(string $input, array $attachments = []): Generator<StreamEvent>;

// Response properties (PrismResponse)
$response->text;          // Text response
$response->usage;         // Token usage stats
$response->steps;         // Multi-step agentic loop history
$response->toolCalls;     // Tool calls as ToolCall objects
$response->toolResults;   // Tool results
$response->finishReason;  // FinishReason enum
$response->meta;          // Request metadata

// Response properties (StructuredResponse - when using withSchema)
$response->structured;    // The structured data array
$response->text;          // Raw text (if available)
$response->usage;         // Token usage stats

// AgentRegistryContract methods
$registry->register(string $class, bool $override = false): void;
$registry->registerInstance(AgentContract $agent, bool $override = false): void;
$registry->has(string $key): bool;
$registry->get(string $key): AgentContract;
$registry->all(): array;

// AgentExtensionRegistry methods
$registry->registerDecorator(AgentDecorator $decorator): void;
$registry->hasDecorators(): bool;
$registry->decoratorCount(): int;
$registry->clearDecorators(): void;
```

## Next Steps

- [Chat](/capabilities/chat) — Use agents in conversations
- [System Prompts](/core-concepts/system-prompts) — Variable interpolation in prompts
- [Tools](/core-concepts/tools) — Add callable tools to agents
- [Pipelines](/core-concepts/pipelines) — Add middleware for agent execution
