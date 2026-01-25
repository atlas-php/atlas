# System Prompts

System prompts define your agent's behavior, personality, and capabilities. Atlas supports variable interpolation for dynamic prompts.

## Basic System Prompt

```php
public function systemPrompt(): ?string
{
    return 'You are a helpful assistant.';
}
```

## Variable Interpolation

Use `{variable_name}` placeholders in system prompts:

```php
public function systemPrompt(): ?string
{
    return <<<PROMPT
    You are a customer support agent for {user_name}.
    The customer's name is {customer_name}.
    Their account tier is {account_tier}.

    Be professional, helpful, and concise.
    PROMPT;
}
```

### Providing Variables

Variables are passed via `withVariables()`:

```php
use Atlasphp\Atlas\Atlas;

$response = Atlas::agent('support-agent')
    ->withMessages($messages)
    ->withVariables([
        'user_name' => 'Acme Inc',
        'customer_name' => 'Jane Doe',
        'account_tier' => 'premium',
    ])
    ->chat('I need help');
```

### Variable Naming

- Use `snake_case` or `camelCase`
- Names are case-sensitive
- Undefined variables remain as-is (`{undefined}` stays literal)

## Global Variables

Register variables that apply to all prompts:

```php
use Atlasphp\Atlas\Agents\Services\SystemPromptBuilder;

$builder = app(SystemPromptBuilder::class);

// Register global variables
$builder->registerVariable('current_date', date('Y-m-d'));
$builder->registerVariable('app_name', config('app.name'));

// Unregister a variable
$builder->unregisterVariable('current_date');
```

::: warning Singleton State
`SystemPromptBuilder` is a singleton. In long-running processes (Octane, queues), registered variables persist across requests. For request-scoped variables, prefer `withVariables()` over `registerVariable()`.
:::

## Prompt Sections

Add reusable sections that are appended to all prompts:

```php
use Atlasphp\Atlas\Agents\Services\SystemPromptBuilder;

$builder = app(SystemPromptBuilder::class);

// Add sections (appended after the main prompt)
$builder->addSection('rules', "## Guidelines\nFollow these rules...");
$builder->addSection('context', "## Current Context\n{context_data}");

// Remove a specific section
$builder->removeSection('context');

// Clear all sections
$builder->clearSections();
```

Sections are appended to the prompt after variable interpolation, and can contain `{variable}` placeholders that will be interpolated.

## Best Practices

### 1. Be Specific

```php
// Good - specific instructions
public function systemPrompt(): ?string
{
    return <<<PROMPT
    You are a technical support specialist for {product_name}.

    Your responsibilities:
    - Answer questions about product features
    - Help troubleshoot common issues
    - Escalate complex problems to human support

    Do NOT:
    - Make promises about features not yet released
    - Share internal company information
    - Process refunds (direct to billing team)
    PROMPT;
}
```

### 2. Include Context

```php
public function systemPrompt(): ?string
{
    return <<<PROMPT
    You help users with {product_name}.

    Current user context:
    - Name: {user_name}
    - Plan: {subscription_plan}
    - Member since: {member_since}

    Use this context to personalize your responses.
    PROMPT;
}
```

### 3. Define Output Expectations

```php
public function systemPrompt(): ?string
{
    return <<<PROMPT
    Analyze the sentiment of user messages.

    Always respond with:
    1. Sentiment (positive/negative/neutral)
    2. Confidence (high/medium/low)
    3. Key phrases that influenced your analysis
    PROMPT;
}
```

### 4. Handle Edge Cases

```php
public function systemPrompt(): ?string
{
    return <<<PROMPT
    You are a code review assistant.

    If the code is:
    - Too long: Ask the user to share specific sections
    - In an unsupported language: Explain what languages you support
    - Missing context: Ask clarifying questions
    PROMPT;
}
```

## Dynamic Prompts via Middleware

Modify prompts at runtime using pipeline middleware:

```php
use Atlasphp\Atlas\Contracts\PipelineContract;
use Closure;

class AddTimestampToPrompt implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        $timestamp = now()->toDateTimeString();
        $data['prompt'] .= "\n\nCurrent time: {$timestamp}";

        return $next($data);
    }
}

// Register in a service provider
use Atlasphp\Atlas\Pipelines\PipelineRegistry;

$registry = app(PipelineRegistry::class);
$registry->register('agent.system_prompt.after_build', AddTimestampToPrompt::class);
```

## Pipeline Hooks

Intercept and modify the system prompt build process using pipeline middleware.

<div class="full-width-table">

| Pipeline | Description |
|----------|-------------|
| `agent.system_prompt.before_build` | Before building the prompt |
| `agent.system_prompt.after_build` | After building (can modify final prompt) |

</div>

### Before Build Hook

Receives: `$data['agent']`, `$data['context']`, `$data['variables']`

```php
use Atlasphp\Atlas\Contracts\PipelineContract;
use Closure;

class BeforeBuildHandler implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        // Add or modify variables before interpolation
        $data['variables']['extra_info'] = 'Dynamic value';

        return $next($data);
    }
}
```

### After Build Hook

Receives: `$data['agent']`, `$data['context']`, `$data['prompt']`

```php
use Atlasphp\Atlas\Contracts\PipelineContract;
use Closure;

class AfterBuildHandler implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        // Modify the built prompt string
        $data['prompt'] .= "\n\nAdditional context here.";

        return $next($data);
    }
}
```

## API Reference

```php
// SystemPromptBuilder methods
$builder = app(SystemPromptBuilder::class);

$builder->registerVariable(string $name, string $value): void;
$builder->unregisterVariable(string $name): void;
$builder->addSection(string $name, string $content): void;
$builder->removeSection(string $name): void;
$builder->clearSections(): void;
$builder->build(AgentContract $agent, ExecutionContext $context): string;

// ExecutionContext variable passing (via AgentExecutor)
Atlas::agent('agent')
    ->withVariables(array $variables)
    ->chat(string $input);
```

## Next Steps

- [Agents](/core-concepts/agents) — Agent configuration
- [Pipelines](/core-concepts/pipelines) — Extend prompt building
