# Instructions

Instructions define agent behavior through system prompts with variable interpolation. Atlas supports `{VARIABLE}` placeholders that are resolved at runtime from multiple sources.

## Basic Instructions

Define instructions in your agent's `instructions()` method:

```php
public function instructions(): ?string
{
    return 'You are a helpful assistant for {COMPANY_NAME}.';
}
```

Variables are interpolated before the prompt is sent to the provider. Unknown placeholders are left as-is.

## Variable Interpolation

### Syntax

- `{VARIABLE}` -- flat key lookup
- `{COMPANY.NAME}` -- dot notation for nested arrays

The interpolator matches the pattern `{[A-Za-z_][A-Za-z0-9_.]*}` and resolves against the merged variable pool.

### Three-Layer Priority

Variables are resolved by merging three layers. Higher layers override lower ones:

1. **Config variables** (lowest) -- static values in `config/atlas.php`
2. **Global registry** -- runtime values registered via `VariableRegistry`
3. **Per-call** (highest) -- values passed with `->withVariables()`

### Config Variables

```php
// config/atlas.php
'variables' => [
    'APP_NAME' => env('APP_NAME', 'Laravel'),
    'COMPANY' => [
        'NAME' => 'Acme Inc',
        'SUPPORT_EMAIL' => 'help@acme.com',
    ],
],
```

Access in instructions: `{APP_NAME}`, `{COMPANY.NAME}`, `{COMPANY.SUPPORT_EMAIL}`

### Global Registry

Register variables that apply to all requests at runtime. The registry is a singleton, so values persist for the process lifetime:

```php
use Atlasphp\Atlas\Support\VariableRegistry;

$registry = app(VariableRegistry::class);

// Register a static value
$registry->register('CURRENT_DATE', now()->toDateString());

// Register a closure (resolved fresh each time)
$registry->register('CURRENT_TIME', fn () => now()->toTimeString());

// Register a closure that receives meta context
$registry->register('USER_NAME', fn (array $meta) => User::find($meta['user_id'])?->name ?? 'Guest');

// Register multiple at once
$registry->registerMany([
    'APP_VERSION' => '2.1.0',
    'ENVIRONMENT' => app()->environment(),
]);

// Remove a variable
$registry->unregister('CURRENT_DATE');
```

### Per-Call Variables

Pass variables directly on a request for the highest priority override:

```php
Atlas::agent('support')
    ->withVariables([
        'user_name' => 'Sarah',
        'account_tier' => 'premium',
    ])
    ->message('Help me')
    ->asText();
```

## All Modalities

Variable interpolation is available on all Atlas modalities, not just agents. Any builder that uses the `HasVariables` trait supports `withVariables()` and `withMessageInterpolation()`:

```php
// Text generation
Atlas::text('openai', 'gpt-4o')
    ->instructions('You are {ASSISTANT_NAME}, a helpful assistant.')
    ->withVariables(['ASSISTANT_NAME' => 'Atlas Bot'])
    ->message('Hello')
    ->asText();

// Image generation
Atlas::image('openai', 'dall-e-3')
    ->instructions('A {STYLE} painting of a mountain')
    ->withVariables(['STYLE' => 'watercolor'])
    ->asImage();
```

## Message Interpolation

By default, only instructions (system prompts) are interpolated. Enable `withMessageInterpolation()` to also interpolate variables in user message content:

```php
Atlas::agent('support')
    ->withVariables(['product' => 'Atlas PHP'])
    ->withMessageInterpolation()
    ->message('Tell me about {product}')
    ->asText();
```

Without `withMessageInterpolation()`, the literal string `{product}` would be sent to the model.

## Unresolved Variables

Placeholders that don't match any variable in the merged pool are left in the output unchanged. This is intentional -- it prevents silent data loss and makes debugging easier:

```php
// If UNKNOWN is not defined anywhere:
'Hello {UNKNOWN}'  // → 'Hello {UNKNOWN}'
```

## Best Practices

### Be Specific

```php
public function instructions(): ?string
{
    return <<<PROMPT
    You are a technical support specialist for {PRODUCT_NAME}.

    Your responsibilities:
    - Answer questions about product features
    - Help troubleshoot common issues
    - Escalate complex problems to human support

    Do NOT:
    - Make promises about unreleased features
    - Share internal company information
    - Process refunds (direct to billing team)
    PROMPT;
}
```

### Include User Context

```php
public function instructions(): ?string
{
    return <<<PROMPT
    You help users with {PRODUCT_NAME}.

    Current user context:
    - Name: {USER_NAME}
    - Plan: {SUBSCRIPTION_PLAN}
    - Member since: {MEMBER_SINCE}

    Use this context to personalize your responses.
    PROMPT;
}
```

## API Reference

### Variable Syntax

| Syntax | Example | Description |
|--------|---------|-------------|
| `{KEY}` | `{APP_NAME}` | Flat variable lookup |
| `{KEY.SUB}` | `{COMPANY.NAME}` | Dot notation for nested arrays |

### Variable Priority (highest wins)

| Source | Set Via | Priority |
|--------|---------|----------|
| Config variables | `config/atlas.php` → `variables` | Lowest |
| Global registry | `VariableRegistry::register()` | Medium |
| Per-call | `->withVariables([...])` | Highest |

### Builder Methods

| Method | Description |
|--------|-------------|
| `->withVariables(array $variables)` | Set per-call variable overrides |
| `->withMessageInterpolation()` | Also interpolate variables in user messages |

## Next Steps

- [Agents](/core-concepts/agents) — Agent configuration
- [Schema](/core-concepts/schema) — Field types for structured output
- [Middleware](/core-concepts/pipelines) — Extend behavior with middleware
