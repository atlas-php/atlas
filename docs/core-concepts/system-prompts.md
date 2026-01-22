# System Prompts

System prompts define your agent's behavior, personality, and capabilities. Atlas supports variable interpolation for dynamic prompts.

## Basic System Prompt

```php
public function systemPrompt(): string
{
    return 'You are a helpful assistant.';
}
```

## Variable Interpolation

Use `{variable_name}` placeholders in system prompts:

```php
public function systemPrompt(): string
{
    return <<<PROMPT
    You are a customer support agent for {company_name}.
    The customer's name is {customer_name}.
    Their account tier is {account_tier}.

    Be professional, helpful, and concise.
    PROMPT;
}
```

### Providing Variables

Variables are passed via `withVariables()`:

```php
$response = Atlas::forMessages($messages)
    ->withVariables([
        'company_name' => 'Acme Inc',
        'customer_name' => 'Jane Doe',
        'account_tier' => 'premium',
    ])
    ->chat('support-agent', 'I need help');
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
```

## Prompt Sections

Add reusable sections to prompts:

```php
$builder->addSection('rules', '## Guidelines\nFollow these rules...');
$builder->addSection('context', '## Current Context\n{context_data}');
```

## Best Practices

### 1. Be Specific

```php
// Good - specific instructions
public function systemPrompt(): string
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
public function systemPrompt(): string
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
public function systemPrompt(): string
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
public function systemPrompt(): string
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
use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;

$registry = app(PipelineRegistry::class);

$registry->register('agent.system_prompt.after_build', function (array $data, $next) {
    // Add dynamic content to the built prompt
    $timestamp = now()->toDateTimeString();
    $data['prompt'] .= "\n\nCurrent time: {$timestamp}";

    return $next($data);
});
```

## Pipeline Hooks

| Pipeline | Description |
|----------|-------------|
| `agent.system_prompt.before_build` | Before building the prompt |
| `agent.system_prompt.after_build` | After building (can modify final prompt) |

### Before Build Hook

```php
$registry->register('agent.system_prompt.before_build', function (array $data, $next) {
    // Access: $data['agent'], $data['context']
    // Add variables or modify context before build
    return $next($data);
});
```

### After Build Hook

```php
$registry->register('agent.system_prompt.after_build', function (array $data, $next) {
    // Access: $data['agent'], $data['context'], $data['prompt']
    // Modify the built prompt string
    return $next($data);
});
```

## Troubleshooting

### Variables Not Interpolated

If `{variable}` placeholders appear in output:

1. Ensure variable names match exactly (case-sensitive)
2. Use snake_case for consistency
3. Verify `withVariables()` is called before `chat()`

```php
// Check variable names match
$response = Atlas::forMessages([])
    ->withVariables([
        'user_name' => 'John',  // Matches {user_name}
        'userName' => 'John',   // Would match {userName}
    ])
    ->chat('agent', 'Hello');
```

### Prompt Too Long

If prompts exceed token limits:

1. Move static content to documentation/knowledge base
2. Use RAG to fetch relevant context
3. Keep prompts focused on current task

## Next Steps

- [Agents](/core-concepts/agents) — Agent configuration
- [Creating Agents](/guides/creating-agents) — Step-by-step guide
- [Pipelines](/core-concepts/pipelines) — Extend prompt building
