# Agents

Agents are reusable AI configurations that combine a provider, model, system prompt, and tools into a single class.

## What is an Agent?

An agent can define:
- **Provider** — The AI provider (`openai`, `anthropic`, etc.)
- **Model** — The model to use (`gpt-4o`, `claude-sonnet-4-20250514`, etc.)
- **System Prompt** — Instructions with `{variable}` interpolation support
- **Tools** — Custom tool classes the agent can invoke
- **Provider Tools** — Built-in provider capabilities (web search, code execution)
- **MCP Tools** — External tools from [MCP servers](/capabilities/mcp)
- **Options** — Temperature, max tokens, max steps, client options, provider options
- **Schema** — For structured output responses

All methods have sensible defaults. Override only what you need.

## Example: Basic Agent

```php
use Atlasphp\Atlas\Agents\AgentDefinition;

class CustomerSupportAgent extends AgentDefinition
{
    public function provider(): ?string { return 'openai'; }
    public function model(): ?string { return 'gpt-4o'; }
    public function temperature(): ?float { return 0.7; }

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
            ProcessRefundTool::class,
        ];
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

## Agent Response

When you call `chat()`, Atlas returns an `AgentResponse` that wraps Prism's response with agent context. This provides access to both the AI response and the agent-specific metadata.

### Backward Compatible Property Access

For backward compatibility, you can access Prism response properties directly via magic `__get`:

```php
$response = Atlas::agent('customer-support')->chat('Hello');

// Access AI response properties directly (backward compatible)
echo $response->text;                    // The AI response text
echo $response->usage->promptTokens;     // Token usage
echo $response->finishReason;            // FinishReason enum
```

### Explicit Methods

AgentResponse also provides explicit methods for IDE autocompletion:

```php
$response = Atlas::agent('customer-support')->chat('Hello');

// Explicit method calls
echo $response->text();                  // The AI response text
echo $response->usage()->promptTokens;   // Token usage
echo $response->finishReason();          // FinishReason enum
$response->toolCalls();                  // Tool calls array
$response->toolResults();                // Tool results array
$response->steps();                      // Multi-step loop history
$response->messages();                   // Messages collection
```

### Agent Context Access

Access agent-specific information that was used during execution:

```php
$response = Atlas::agent('customer-support')
    ->withVariables(['customer_name' => 'John'])
    ->withMetadata(['user_id' => 123])
    ->chat('Hello');

// Agent information
echo $response->agentKey();              // 'customer-support'
echo $response->agentName();             // 'Customer Support'
echo $response->agentDescription();      // Agent description

// Execution context
echo $response->systemPrompt;            // The system prompt that was used
$response->metadata();                   // ['user_id' => 123]
$response->variables();                  // ['customer_name' => 'John']

// Full Prism response access
$prismResponse = $response->response;    // PrismResponse|StructuredResponse
```

### Structured Output Detection

```php
$response = Atlas::agent('analyzer')
    ->withSchema($schema)
    ->chat('Analyze this');

if ($response->isStructured()) {
    $data = $response->structured();     // Returns the structured array
}
```

### Streaming Response

Use `stream()` for real-time token streaming. The `AgentStreamResponse` provides agent context immediately, before iteration:

```php
$stream = Atlas::agent('customer-support')
    ->withVariables(['customer_name' => 'Sarah'])
    ->stream('Where is my order?');

// Agent context available before iteration
echo $stream->agentKey();                // 'customer-support'
echo $stream->metadata();                // Pipeline metadata

// Iterate to receive events
foreach ($stream as $event) {
    if ($event instanceof TextDeltaEvent) {
        echo $event->delta;              // Output tokens as they arrive
    }
}

// After iteration
$allEvents = $stream->events();          // All collected events
$stream->isConsumed();                   // true after full iteration
```

### Streaming Responses

Use `stream()` for real-time token streaming:

```php
$stream = Atlas::agent('customer-support')
    ->withVariables(['customer_name' => 'Sarah'])
    ->stream('Where is my order?');

foreach ($stream as $event) {
    echo $event->text; // Output tokens as they arrive
}
```

### Passing Metadata to Tools

Use `withMetadata()` to pass context that tools can access via `ToolContext`:

```php
$response = Atlas::agent('customer-support')
    ->withMetadata([
        'user_id' => auth()->id(),
        'tenant_id' => $tenant->id,
    ])
    ->chat('Look up my recent orders');
```

Inside your tool, access this metadata:

```php
public function handle(array $params, ToolContext $context): ToolResult
{
    $userId = $context->getMeta('user_id');
    $orders = Order::where('user_id', $userId)->get();

    return ToolResult::json($orders);
}
```

This allows tools to access user context, tenant isolation, and other request-specific data without hardcoding it.

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
| `mcpTools()` | `[]` | MCP tools from external servers ([details](/capabilities/mcp)) |
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

## Example: Sales Support Agent

Handles order inquiries, shipment tracking, returns, and promotional discounts. Uses tools to look up real-time order data and process customer requests.

```php
class SalesSupportAgent extends AgentDefinition
{
    public function provider(): ?string { return 'anthropic'; }
    public function model(): ?string { return 'claude-sonnet-4-20250514'; }
    public function temperature(): ?float { return 0.3; }
    public function maxSteps(): ?int { return 8; }

    public function systemPrompt(): ?string
    {
        return <<<PROMPT
        You are a sales support specialist for {company_name}.

        ## Customer Context
        - **Customer:** {customer_name}
        - **Account ID:** {account_id}
        - **Membership:** {membership_tier}

        ## Available Tools
        - **lookup_order** - Retrieve order details by order ID or customer lookup
        - **track_shipment** - Get real-time shipping status and delivery estimates
        - **process_return** - Initiate returns for eligible orders (within 30 days)
        - **apply_discount** - Apply promotional codes or loyalty discounts
        - **escalate_issue** - Transfer complex issues to a human supervisor

        ## Guidelines
        1. Always greet the customer by name and confirm their account
        2. For order inquiries, use `lookup_order` before providing any details
        3. When customers ask "where is my order", use both `lookup_order` and `track_shipment`
        4. Before processing returns, verify eligibility with `lookup_order` first
        5. Apply discounts only when customer provides a valid code or qualifies via membership
        6. Escalate if: refund > $500, suspected fraud, or customer requests supervisor

        ## Response Style
        - Be concise and helpful—customers value quick resolutions
        - Always confirm actions before executing (e.g., "I'll process that return now")
        - Provide order numbers and tracking links when available
        PROMPT;
    }

    public function tools(): array
    {
        return [
            LookupOrderTool::class,
            TrackShipmentTool::class,
            ProcessReturnTool::class,
            ApplyDiscountTool::class,
            EscalateIssueTool::class,
        ];
    }
}
```

## Example: Customer Service Agent

Manages support tickets, searches knowledge bases for answers, and handles account-related requests like password resets and preference updates.

```php
class CustomerServiceAgent extends AgentDefinition
{
    public function provider(): ?string { return 'openai'; }
    public function model(): ?string { return 'gpt-4o'; }
    public function temperature(): ?float { return 0.4; }
    public function maxSteps(): ?int { return 6; }

    public function systemPrompt(): ?string
    {
        return <<<PROMPT
        You are a customer service representative for {company_name}.

        ## Customer Information
        - **Name:** {customer_name}
        - **Email:** {customer_email}
        - **Account Status:** {account_status}

        ## Available Tools
        - **search_knowledge_base** - Search FAQs and help articles for answers
        - **create_ticket** - Create a support ticket for issues requiring follow-up
        - **update_ticket** - Add notes or change status on existing tickets
        - **get_account_info** - Retrieve customer account details and history
        - **reset_password** - Send password reset email to customer
        - **update_preferences** - Modify account settings and preferences

        ## Workflow
        1. First, search the knowledge base for common questions
        2. If the issue requires account access, use `get_account_info` to verify
        3. For unresolved issues, create a ticket with detailed notes
        4. Always provide ticket numbers for tracking

        ## Tone
        - Professional yet friendly
        - Empathetic to customer frustrations
        - Clear and jargon-free explanations
        PROMPT;
    }

    public function tools(): array
    {
        return [
            SearchKnowledgeBaseTool::class,
            CreateTicketTool::class,
            UpdateTicketTool::class,
            GetAccountInfoTool::class,
            ResetPasswordTool::class,
            UpdatePreferencesTool::class,
        ];
    }
}
```

## Example: Code Review Agent

Reviews pull requests and code submissions for bugs, security vulnerabilities, and adherence to coding standards. Provides structured feedback with prioritized issues.

```php
class CodeReviewAgent extends AgentDefinition
{
    public function provider(): ?string { return 'anthropic'; }
    public function model(): ?string { return 'claude-sonnet-4-20250514'; }
    public function temperature(): ?float { return 0.2; }
    public function maxSteps(): ?int { return 10; }

    public function systemPrompt(): ?string
    {
        return <<<PROMPT
        You are a senior software engineer conducting code reviews.

        ## Project Context
        - **Language:** {language}
        - **Framework:** {framework}
        - **Coding Standard:** {coding_standard}

        ## Available Tools
        - **analyze_complexity** - Calculate cyclomatic complexity and identify complex methods
        - **check_security** - Scan for common security vulnerabilities (SQL injection, XSS, etc.)
        - **find_duplicates** - Detect duplicate or similar code blocks
        - **check_dependencies** - Verify dependency versions and known vulnerabilities
        - **run_static_analysis** - Run linting and static analysis checks

        ## Review Checklist
        1. **Security** - Always run `check_security` first for any code handling user input
        2. **Complexity** - Flag methods with complexity > 10 using `analyze_complexity`
        3. **DRY Principle** - Use `find_duplicates` for files > 100 lines
        4. **Dependencies** - Check for outdated or vulnerable packages

        ## Output Format
        - **Critical Issues** - Must fix before merge
        - **Recommendations** - Should fix, not blocking
        - **Suggestions** - Nice to have improvements
        - **Positive Notes** - What was done well
        PROMPT;
    }

    public function tools(): array
    {
        return [
            AnalyzeComplexityTool::class,
            CheckSecurityTool::class,
            FindDuplicatesTool::class,
            CheckDependenciesTool::class,
            RunStaticAnalysisTool::class,
        ];
    }
}
```

## Example: Content Writer Agent

Creates SEO-optimized blog posts and marketing copy. Researches topics, analyzes keywords, and checks readability to produce content tailored to brand voice and target audience.

```php
class ContentWriterAgent extends AgentDefinition
{
    public function provider(): ?string { return 'openai'; }
    public function model(): ?string { return 'gpt-4o'; }
    public function temperature(): ?float { return 0.7; }
    public function maxSteps(): ?int { return 12; }

    public function systemPrompt(): ?string
    {
        return <<<PROMPT
        You are a professional content writer for {brand_name}.

        ## Brand Voice
        - **Tone:** {brand_tone}
        - **Target Audience:** {target_audience}
        - **Industry:** {industry}

        ## Available Tools
        - **research_topic** - Gather current information and statistics on a topic
        - **analyze_keywords** - Get SEO keyword suggestions and search volume
        - **check_readability** - Analyze content for readability score and improvements
        - **find_competitors** - Analyze top-ranking content for the target keyword
        - **generate_outline** - Create a structured outline based on research

        ## Writing Process
        1. Use `analyze_keywords` to identify primary and secondary keywords
        2. Use `research_topic` to gather current facts and statistics
        3. Use `find_competitors` to understand what's ranking well
        4. Use `generate_outline` to structure the content
        5. Write the content incorporating keywords naturally
        6. Use `check_readability` to ensure accessibility

        ## Content Guidelines
        - Include the primary keyword in the title and first paragraph
        - Use headers (H2, H3) to break up content
        - Keep paragraphs short (2-3 sentences)
        - Include a clear call-to-action
        - Aim for {target_word_count} words
        PROMPT;
    }

    public function tools(): array
    {
        return [
            ResearchTopicTool::class,
            AnalyzeKeywordsTool::class,
            CheckReadabilityTool::class,
            FindCompetitorsTool::class,
            GenerateOutlineTool::class,
        ];
    }

    public function providerTools(): array
    {
        return ['web_search'];
    }
}
```

## Example: Data Analyst Agent

Analyzes business data by querying databases, calculating KPIs, and generating visualizations. Provides insights with context and suggests follow-up analyses.

```php
class DataAnalystAgent extends AgentDefinition
{
    public function provider(): ?string { return 'anthropic'; }
    public function model(): ?string { return 'claude-sonnet-4-20250514'; }
    public function temperature(): ?float { return 0.2; }
    public function maxSteps(): ?int { return 8; }

    public function systemPrompt(): ?string
    {
        return <<<PROMPT
        You are a data analyst for {company_name}.

        ## Data Context
        - **Database:** {database_type}
        - **Primary Tables:** {available_tables}
        - **Reporting Period:** {reporting_period}

        ## Available Tools
        - **query_database** - Execute read-only SQL queries against the database
        - **calculate_metrics** - Compute KPIs like growth rate, churn, LTV, etc.
        - **generate_chart** - Create visualizations (bar, line, pie charts)
        - **export_report** - Export results to CSV or PDF format
        - **compare_periods** - Compare metrics across different time periods

        ## Analysis Guidelines
        1. Always use `query_database` with specific columns, never SELECT *
        2. Limit result sets to prevent performance issues (default LIMIT 1000)
        3. Use `calculate_metrics` for derived calculations, not raw SQL
        4. When presenting data, include context (% change, benchmarks)
        5. Offer to `generate_chart` for datasets with trends or comparisons

        ## Security Rules
        - Only read operations are permitted
        - Never expose raw customer PII in outputs
        - Aggregate sensitive data (show counts, not individual records)
        PROMPT;
    }

    public function tools(): array
    {
        return [
            QueryDatabaseTool::class,
            CalculateMetricsTool::class,
            GenerateChartTool::class,
            ExportReportTool::class,
            ComparePeriodsTool::class,
        ];
    }
}
```

## Example: HR Assistant Agent

Provides employees with self-service access to HR information including PTO balances, benefits enrollment, company policies, and organizational data. Connects to internal HR systems.

```php
class HRAssistantAgent extends AgentDefinition
{
    public function provider(): ?string { return 'openai'; }
    public function model(): ?string { return 'gpt-4o'; }
    public function temperature(): ?float { return 0.3; }
    public function maxSteps(): ?int { return 6; }

    public function systemPrompt(): ?string
    {
        return <<<PROMPT
        You are an HR assistant for {company_name}.

        ## Employee Context
        - **Employee:** {employee_name}
        - **Employee ID:** {employee_id}
        - **Department:** {department}
        - **Manager:** {manager_name}

        ## Available Tools
        - **get_pto_balance** - Retrieve remaining PTO, sick days, and personal days
        - **request_time_off** - Submit a new PTO request for manager approval
        - **get_benefits_info** - Look up current benefits enrollment and options
        - **search_policies** - Search the employee handbook and company policies
        - **get_org_chart** - Find team members, reporting structure, and contact info
        - **get_payroll_info** - Retrieve pay stubs, tax documents, and direct deposit info

        ## Guidelines
        1. Always verify employee context before accessing sensitive data
        2. For PTO requests, check balance with `get_pto_balance` before submitting
        3. Direct benefits changes to the HR portal—provide guidance only
        4. For policy questions, always cite the specific policy using `search_policies`

        ## Privacy
        - Only share information the employee is authorized to see
        - Never disclose other employees' compensation or personal data
        PROMPT;
    }

    public function tools(): array
    {
        return [
            GetPtoBalanceTool::class,
            RequestTimeOffTool::class,
            GetBenefitsInfoTool::class,
            SearchPoliciesTool::class,
            GetOrgChartTool::class,
            GetPayrollInfoTool::class,
        ];
    }
}
```

## Example: IT Helpdesk Agent (with MCP Tools)

Handles internal IT support requests using tools from external MCP servers. Demonstrates how to integrate with Jira, ServiceNow, or other IT service management platforms via [MCP](/capabilities/mcp).

```php
use Prism\Relay\Relay;

class ITHelpdeskAgent extends AgentDefinition
{
    public function provider(): ?string { return 'anthropic'; }
    public function model(): ?string { return 'claude-sonnet-4-20250514'; }
    public function temperature(): ?float { return 0.2; }
    public function maxSteps(): ?int { return 8; }

    public function systemPrompt(): ?string
    {
        return <<<PROMPT
        You are an IT helpdesk agent for {company_name}.

        ## User Context
        - **User:** {user_name}
        - **Email:** {user_email}
        - **Department:** {department}
        - **Access Level:** {access_level}

        ## Available Tools
        You have access to tools from our IT service management system:

        - **jira_create_issue** - Create a support ticket in Jira
        - **jira_search_issues** - Search existing tickets by keyword or status
        - **jira_add_comment** - Add a comment to an existing ticket
        - **jira_get_issue** - Get details of a specific ticket
        - **jira_transition_issue** - Update ticket status (open, in progress, resolved)

        ## Troubleshooting Flow
        1. Search existing tickets with `jira_search_issues` to check for known issues
        2. For new issues, create a ticket with `jira_create_issue`
        3. Add diagnostic information using `jira_add_comment`
        4. Update ticket status as you work through the issue

        ## Security Protocols
        - Password resets send links to registered email only
        - Admin access requests must be escalated to IT Security team
        - Never share credentials or bypass access controls
        PROMPT;
    }

    /**
     * MCP tools from external servers via prism-php/relay.
     * See: https://github.com/prism-php/relay
     */
    public function mcpTools(): array
    {
        return [
            ...Relay::server('jira')->tools(),
            ...Relay::server('confluence')->tools(),
        ];
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

## Runtime Middleware

Attach per-request middleware to agent executions without global registration. This is useful for request-specific validation, logging, or error recovery.

```php
use Atlasphp\Atlas\Atlas;

$response = Atlas::agent('my-agent')
    ->middleware([
        'agent.before_execute' => ValidateInputMiddleware::class,
        'agent.after_execute' => LogResponseMiddleware::class,
    ])
    ->chat($input);
```

### Multiple Handlers

```php
->middleware([
    'agent.before_execute' => [
        AuthMiddleware::class,
        RateLimitMiddleware::class,
    ],
])
```

### Handler Instances

Pass configured instances for runtime configuration:

```php
->middleware([
    'agent.after_execute' => new MetricsMiddleware($statsd),
])
```

### Accumulating Middleware

Multiple calls merge handlers:

```php
->middleware(['agent.before_execute' => AuthMiddleware::class])
->middleware(['agent.after_execute' => LogMiddleware::class])
// Both middleware are applied
```

### Clearing Middleware

```php
->withoutMiddleware()
```

Runtime middleware merges with global handlers by priority. Global handlers run first (sorted by their registered priority), followed by runtime handlers (in registration order).

See [Runtime Middleware](/core-concepts/pipelines#runtime-middleware) for complete documentation including available events, execution order, and examples.

## Queue Processing

AgentContext supports serialization for queue-based async processing. This enables dispatching agent jobs to Laravel queues while Atlas handles only the context serialization—consumers manage all persistence.

### Dispatching to Queue

```php
// Build context and serialize for queue transport
$context = new AgentContext(
    variables: [
        'user_name' => $user->name
    ],
    metadata: [
        'task_id' => $task->id,
        'user_id' => $user->id
    ],
);

// You create a job that accepts AgentContext as a constructor argument
ProcessAgentJob::dispatch(
    agentKey: 'my-agent',
    input: 'Generate a report',
    context: $context->toArray(),
);
```

### Processing in Job

Create a processing job

```php
use Atlasphp\Atlas\Agents\Support\AgentContext;

class ProcessAgentJob implements ShouldQueue
{
    public function __construct(
        public string $agentKey,
        public string $input,
        public array $context,
    ) {}

    public function handle(): void
    {
        $context = AgentContext::fromArray($this->context);

        $response = Atlas::agent($this->agentKey)
            ->withContext($context)
            ->chat($this->input);

        // Handle response...
    }
}
```

### Serialization Notes

The following properties are fully serialized:
- `messages` — Conversation history in array format
- `variables` — System prompt variable bindings
- `metadata` — Pipeline metadata
- `providerOverride` / `modelOverride` — Provider and model overrides
- `prismCalls` — Captured Prism method calls
- `tools` — Atlas tool class names
- `middleware` — Runtime middleware (class-strings only; handler instances are excluded)

Runtime-only properties (not serialized):
- `prismMedia` — Media attachments (must be re-attached via `withMedia()`)
- `prismMessages` — Prism message objects (rebuilt at runtime)
- `mcpTools` — MCP tools (must be resolved at runtime)
- Middleware handler instances — Only class-string handlers are serialized

For media attachments, store the file path in metadata and re-attach in your job:

```php
public function handle(): void
{
    $context = AgentContext::fromArray($this->context);
    $imagePath = $context->getMeta('image_path');

    $response = Atlas::agent($this->agentKey)
        ->withContext($context)
        ->withMedia(Image::fromPath($imagePath))
        ->chat($this->input);
}
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
public function type(): AgentType;        // Execution type (AgentType::Api by default)
public function tools(): array;
public function providerTools(): array;
public function mcpTools(): array;  // MCP tools from external servers
public function temperature(): ?float;
public function maxTokens(): ?int;
public function maxSteps(): ?int;
public function clientOptions(): array;
public function providerOptions(): array;
public function schema(): ?PrismSchema;

// Agent execution fluent API (PendingAgentRequest)
Atlas::agent(string|AgentContract $agent)
    ->withMessages(array $messages)              // Conversation history
    ->withVariables(array $variables)            // Replace system prompt variables
    ->mergeVariables(array $variables)           // Merge with existing variables
    ->clearVariables()                           // Clear all variables
    ->withMetadata(array $metadata)              // Replace pipeline metadata
    ->mergeMetadata(array $metadata)             // Merge with existing metadata
    ->clearMetadata()                            // Clear all metadata
    ->withProvider(string $provider, ?string $model = null)  // Override provider
    ->withModel(string $model)                   // Override model
    ->withMedia(Image|Document|Audio|Video|array $media)     // Attach media
    ->withTools(array $tools)                    // Add Atlas tools at runtime
    ->withMcpTools(array $tools)                 // Add MCP tools at runtime
    ->middleware(array $handlers)                // Attach per-request pipeline handlers
    ->withoutMiddleware()                        // Remove all runtime middleware
    ->withSchema(SchemaBuilder|ObjectSchema $schema)         // Structured output
    ->usingAutoMode()                            // Auto schema mode (default)
    ->usingNativeMode()                          // Native JSON schema mode
    ->usingJsonMode()                            // JSON mode (for optional fields)
    ->chat(string $input, array $attachments = []): AgentResponse;
    ->stream(string $input, array $attachments = []): AgentStreamResponse;

// AgentResponse properties and methods
$response->text;              // Text response (via __get magic)
$response->usage;             // Token usage stats (via __get magic)
$response->response;          // Full Prism response (PrismResponse|StructuredResponse)
$response->agent;             // The agent instance
$response->input;             // The input message
$response->systemPrompt;      // The system prompt used
$response->context;           // The AgentContext

// AgentResponse explicit methods
$response->text();            // Text response
$response->usage();           // Usage statistics
$response->toolCalls();       // Tool calls array
$response->toolResults();     // Tool results array
$response->steps();           // Multi-step history
$response->finishReason();    // FinishReason enum
$response->meta();            // Response metadata
$response->messages();        // Messages collection
$response->isStructured();    // Check if structured response
$response->structured();      // Get structured data (or null)
$response->agentKey();        // Agent key
$response->agentName();       // Agent name
$response->agentDescription(); // Agent description
$response->metadata();        // Pipeline metadata from context
$response->variables();       // Variables from context

// AgentStreamResponse (implements IteratorAggregate)
$stream = Atlas::agent($agent)->stream($input);
$stream->agentKey();          // Available before iteration
$stream->agentName();         // Available before iteration
$stream->metadata();          // Available before iteration
$stream->variables();         // Available before iteration
foreach ($stream as $event) { } // Iterate to receive events
$stream->events();            // All events after consumption
$stream->isConsumed();        // Check if stream is consumed

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
- [MCP](/capabilities/mcp) — External tools from MCP servers
- [Pipelines](/core-concepts/pipelines) — Global and per-request middleware for agent execution
