# Atlas v3 — Implementation Plan: Universal Variable Interpolation

## Context

Phase 7 introduced `VariableRegistry` for agent instruction interpolation. It works, but only for agents. Every other modality — text, image, audio, video — accepts `instructions()` as a raw string with no variable support. This means a consumer who builds a multi-modality application has to manually interpolate variables for non-agent calls while agents get it for free.

Additionally, the current implementation has two limitations:

1. **No dot notation.** The regex `/\{(\w+)\}/` matches `{NAME}` but not `{COMPANY.NAME}`. Nested variable access requires dot notation for clean organization.
2. **Agent-only.** The `VariableRegistry` lives in `src/Agents/` and is injected only into `AgentRequest`. It should be a shared concern.

### Reference Documents

- **atlas-v3-implementation-phase7.md** — `VariableRegistry`, agent variable interpolation, `AgentRequest::buildRequest()`
- **atlas-v3-implementation-phase5.md** — Pending request classes, `instructions()` on all modalities
- **atlas-v3-implementation-phase11-meta.md** — `HasMeta` trait pattern (shared concern across all requests)

### What Exists Today

| Modality | Has `instructions()` | Has `withVariables()` | Interpolation |
|---|---|---|---|
| `AgentRequest` | ✓ (via agent class or override) | ✓ | ✓ Global + runtime |
| `TextRequest` | ✓ | ✗ | ✗ Raw string |
| `ImageRequest` | ✓ | ✗ | ✗ Raw string |
| `AudioRequest` | ✓ | ✗ | ✗ Raw string |
| `VideoRequest` | ✓ | ✗ | ✗ Raw string |
| `EmbedRequest` | ✗ (no instructions) | ✗ | N/A |
| `ModerateRequest` | ✗ (no instructions) | ✗ | N/A |

---

## Design Principles

1. **Variables are a shared concern, not an agent concern.** The `VariableRegistry` moves out of `src/Agents/` into `src/Support/` (or `src/Concerns/`). Every pending request with `instructions()` gets interpolation automatically.

2. **Dot notation for nested access.** `{COMPANY.NAME}` resolves from `['COMPANY' => ['NAME' => 'Acme']]` or from a flat key `'COMPANY.NAME' => 'Acme'`. Flat keys take precedence — if you register `COMPANY.NAME` directly, it wins over nested resolution.

3. **Global registry + per-call overrides + config variables.** Three layers, same as middleware stacking: config variables (outermost) → global registry (middle) → per-call `withVariables()` (innermost, highest priority).

4. **Interpolation happens at build time, not registration time.** Closures in the registry are resolved fresh on every call. This means `{DATE}` always returns today's date, `{USER.NAME}` always returns the current user, etc.

5. **Message content interpolation is opt-in.** Instructions are always interpolated (they're templates by nature). Message content is NOT interpolated by default — user input shouldn't have its `{curly braces}` mangled. A `withMessageInterpolation()` method opts in for cases where message templates are needed.

---

## Config

```php
// config/atlas.php — add variables section

'variables' => [
    // Static variables available in all instructions across all modalities.
    // These are the lowest priority — global registry and withVariables() override them.
    //
    // Supports flat keys and nested arrays:
    //   'APP_NAME' => 'My App'
    //   'COMPANY' => ['NAME' => 'Acme', 'SUPPORT_EMAIL' => 'help@acme.com']
    //
    // Access flat: {APP_NAME}
    // Access nested: {COMPANY.NAME}, {COMPANY.SUPPORT_EMAIL}

    'APP_NAME' => env('APP_NAME', 'Laravel'),
    // 'COMPANY' => [
    //     'NAME'          => env('COMPANY_NAME', 'Acme'),
    //     'SUPPORT_EMAIL' => env('COMPANY_SUPPORT_EMAIL'),
    //     'WEBSITE'       => env('COMPANY_WEBSITE'),
    // ],
],
```

**Why config variables?** Most applications have a handful of values that should be available everywhere — company name, app name, support email. Putting them in config means they work without any boot-time registration. The consumer drops values in `config/atlas.php` and every `{COMPANY.NAME}` across every modality resolves automatically.

---

## Consumer API

### Every Modality

```php
// Agent — existing, now with dot notation
Atlas::agent('support')
    ->withVariables(['USER' => ['NAME' => 'Tim', 'PLAN' => 'Pro']])
    ->message('Help with billing')
    ->asText();
// Agent instructions: "You are a support agent for {COMPANY.NAME}. The user is {USER.NAME} on the {USER.PLAN} plan."
// Resolves to: "You are a support agent for Acme. The user is Tim on the Pro plan."

// Text — NEW: variables now work
Atlas::text(Provider::OpenAI, 'gpt-5')
    ->instructions('You are a {COMPANY.NAME} assistant. Respond in {LANGUAGE}.')
    ->withVariables(['LANGUAGE' => 'Spanish'])
    ->message('Hello')
    ->asText();
// "You are a Acme assistant. Respond in Spanish."

// Image — NEW: variables in instructions
Atlas::image(Provider::OpenAI, 'dall-e-3')
    ->instructions('Generate a logo for {COMPANY.NAME} using {BRAND.COLORS}')
    ->withVariables(['BRAND' => ['COLORS' => 'blue and white']])
    ->asImage();

// Audio TTS — NEW: variables in instructions
Atlas::audio(Provider::OpenAI, 'tts-1')
    ->instructions('Welcome to {COMPANY.NAME}. Your account balance is {BALANCE}.')
    ->withVariables(['BALANCE' => '$142.50'])
    ->asAudio();

// Video — NEW: variables in instructions
Atlas::video(Provider::Xai, 'model')
    ->instructions('Create a product demo for {PRODUCT.NAME} showing {PRODUCT.FEATURES}')
    ->withVariables([
        'PRODUCT' => ['NAME' => 'Atlas', 'FEATURES' => 'real-time sync and AI chat'],
    ])
    ->asVideo();
```

### Global Registry

```php
// In a service provider boot() — same as today, just moved from Agents namespace

use Atlasphp\Atlas\Support\VariableRegistry;

$variables = app(VariableRegistry::class);

// Static values
$variables->register('COMPANY.NAME', 'Acme Corp');
$variables->register('SUPPORT_EMAIL', 'help@acme.com');

// Closures — resolved fresh each call
$variables->register('DATE', fn () => now()->toDateString());
$variables->register('DATETIME', fn () => now()->toDateTimeString());

// Nested via array
$variables->register('COMPANY', [
    'NAME'    => 'Acme Corp',
    'WEBSITE' => 'https://acme.com',
    'SUPPORT' => fn () => config('company.support_email'),
]);

// Context-dependent — closures receive the meta array
$variables->register('USER.NAME', fn (array $meta) => $meta['user_name'] ?? 'Guest');
$variables->register('USER.PLAN', fn (array $meta) => $meta['user_plan'] ?? 'Free');
```

### Resolution Priority

```
Per-call withVariables()     ← highest priority (innermost)
  ↓
Global VariableRegistry
  ↓
Config atlas.variables       ← lowest priority (outermost)
```

```php
// Config: 'COMPANY' => ['NAME' => 'Acme']
// Registry: $variables->register('COMPANY.NAME', 'Acme Global');
// Per-call: ->withVariables(['COMPANY' => ['NAME' => 'Acme Override']])

// Result: {COMPANY.NAME} → "Acme Override" (per-call wins)
```

### Message Interpolation (Opt-In)

```php
// By default, only instructions are interpolated.
// Message content is left as-is to avoid mangling user input.

// Opt-in for message templates:
Atlas::text(Provider::OpenAI, 'gpt-5')
    ->instructions('You generate personalized emails.')
    ->message('Write a welcome email for {USER.NAME} at {COMPANY.NAME}')
    ->withVariables(['USER' => ['NAME' => 'Tim']])
    ->withMessageInterpolation()  // ← opt-in
    ->asText();
// Message becomes: "Write a welcome email for Tim at Acme"
```

---

## What Gets Built

```
src/
├── Support/
│   ├── VariableRegistry.php               // MOVED from src/Agents/ + enhanced
│   └── VariableInterpolator.php           // NEW — stateless interpolation engine
│
├── Concerns/
│   └── HasVariables.php                   // NEW — trait for all Pending requests
│
├── Agents/
│   └── VariableRegistry.php               // DEPRECATED — re-exports from Support for BC

tests/
├── Unit/
│   └── Support/
│       ├── VariableRegistryTest.php
│       └── VariableInterpolatorTest.php
│   └── Concerns/
│       └── HasVariablesTest.php
└── Feature/
    └── Variables/
        ├── AgentVariableTest.php
        ├── TextVariableTest.php
        ├── ImageVariableTest.php
        ├── AudioVariableTest.php
        └── ConfigVariableTest.php
```

---

## Implementation

### 1. `VariableInterpolator` — Stateless Engine

Handles the actual string interpolation with dot notation support. Pure function — no state, no registry, no closures. Takes a template and a flat resolved variables array, returns the interpolated string.

```php
// src/Support/VariableInterpolator.php
namespace Atlasphp\Atlas\Support;

class VariableInterpolator
{
    /**
     * Interpolate {PLACEHOLDERS} in a template string.
     *
     * Supports:
     *   {NAME}           → flat key lookup
     *   {COMPANY.NAME}   → dot notation lookup
     *   {COMPANY.NESTED.DEEP} → multi-level dot notation
     *
     * Unknown placeholders are left as-is.
     *
     * @param string $template The template string
     * @param array<string, mixed> $variables Resolved variables (may be nested)
     */
    public static function interpolate(string $template, array $variables): string
    {
        return preg_replace_callback(
            '/\{([A-Za-z_][A-Za-z0-9_.]*)\}/',
            function (array $matches) use ($variables): string {
                $key = $matches[1];

                $value = static::resolve($key, $variables);

                if ($value === null) {
                    return $matches[0]; // Unknown — leave as-is
                }

                return (string) $value;
            },
            $template,
        ) ?? $template;
    }

    /**
     * Resolve a potentially dotted key from a variables array.
     *
     * Resolution order:
     *   1. Exact flat key match: 'COMPANY.NAME' => 'Acme' (wins)
     *   2. Dot notation traversal: ['COMPANY' => ['NAME' => 'Acme']]
     *   3. null if not found
     */
    public static function resolve(string $key, array $variables): mixed
    {
        // 1. Exact flat key match — highest priority
        if (array_key_exists($key, $variables)) {
            $value = $variables[$key];

            // Don't return arrays as values — only scalar/stringable
            if (is_array($value)) {
                return null;
            }

            return $value;
        }

        // 2. Dot notation traversal
        if (str_contains($key, '.')) {
            $segments = explode('.', $key);
            $current = $variables;

            foreach ($segments as $segment) {
                if (! is_array($current) || ! array_key_exists($segment, $current)) {
                    return null;
                }

                $current = $current[$segment];
            }

            // Must resolve to a scalar/stringable, not a nested array
            if (is_array($current)) {
                return null;
            }

            return $current;
        }

        return null;
    }

    /**
     * Check if a template contains any variable placeholders.
     */
    public static function hasPlaceholders(string $template): bool
    {
        return (bool) preg_match('/\{[A-Za-z_][A-Za-z0-9_.]*\}/', $template);
    }
}
```

**Regex: `/\{([A-Za-z_][A-Za-z0-9_.]*)\}/`**

Matches:
- `{NAME}` — simple uppercase
- `{name}` — simple lowercase
- `{COMPANY.NAME}` — dot notation
- `{user.profile.name}` — multi-level dots
- `{APP_NAME}` — underscores
- `{COMPANY.SUPPORT_EMAIL}` — mixed dots and underscores

Does NOT match:
- `{123}` — starts with number
- `{.NAME}` — starts with dot
- `{NAME.}` — ends with dot (the regex requires at least one char after dot)
- `{}` — empty
- `{ NAME }` — spaces

### 2. Updated `VariableRegistry` — Moved and Enhanced

Moves from `src/Agents/VariableRegistry.php` to `src/Support/VariableRegistry.php`. Gains dot notation support for nested registration, closure meta passing, and config layer integration.

```php
// src/Support/VariableRegistry.php
namespace Atlasphp\Atlas\Support;

class VariableRegistry
{
    /** @var array<string, mixed> Flat and nested variables + closures */
    protected array $variables = [];

    /**
     * Register a variable.
     *
     * Accepts:
     *   - Static scalar: register('NAME', 'Acme')
     *   - Closure: register('DATE', fn () => now()->toDateString())
     *   - Closure with meta: register('USER.NAME', fn (array $meta) => $meta['user_name'] ?? 'Guest')
     *   - Nested array: register('COMPANY', ['NAME' => 'Acme', 'URL' => 'https://acme.com'])
     *   - Dot notation key: register('COMPANY.NAME', 'Acme')
     */
    public function register(string $name, mixed $value): void
    {
        $this->variables[$name] = $value;
    }

    /**
     * Register multiple variables at once.
     *
     * @param array<string, mixed> $variables
     */
    public function registerMany(array $variables): void
    {
        foreach ($variables as $name => $value) {
            $this->register($name, $value);
        }
    }

    /**
     * Unregister a variable.
     */
    public function unregister(string $name): void
    {
        unset($this->variables[$name]);
    }

    /**
     * Resolve all registered variables into a flat+nested array.
     * Closures are invoked with the provided meta context.
     *
     * @param array $meta Context passed to closure resolvers
     * @return array<string, mixed> Resolved variables
     */
    public function resolve(array $meta = []): array
    {
        return $this->resolveArray($this->variables, $meta);
    }

    /**
     * Merge config → registry → runtime into a single resolved array.
     * Runtime (per-call) has highest priority.
     *
     * @param array $runtimeVariables Per-call withVariables() values
     * @param array $meta Context for closure resolution
     * @return array<string, mixed> Fully resolved, merged variables
     */
    public function merge(array $runtimeVariables = [], array $meta = []): array
    {
        $config = config('atlas.variables', []);
        $global = $this->resolve($meta);

        return array_replace_recursive($config, $global, $runtimeVariables);
    }

    /**
     * Recursively resolve closures in an array.
     */
    protected function resolveArray(array $items, array $meta): array
    {
        $resolved = [];

        foreach ($items as $key => $value) {
            if ($value instanceof \Closure) {
                $resolved[$key] = $this->invokeClosure($value, $meta);
            } elseif (is_array($value)) {
                $resolved[$key] = $this->resolveArray($value, $meta);
            } else {
                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }

    /**
     * Invoke a closure with meta if it accepts a parameter.
     */
    protected function invokeClosure(\Closure $closure, array $meta): mixed
    {
        $reflection = new \ReflectionFunction($closure);

        if ($reflection->getNumberOfParameters() === 0) {
            return $closure();
        }

        return $closure($meta);
    }
}
```

**Backward compatibility:** Create a re-export at the old location so existing code doesn't break:

```php
// src/Agents/VariableRegistry.php
namespace Atlasphp\Atlas\Agents;

// BC alias — use Atlasphp\Atlas\Support\VariableRegistry instead
class_alias(\Atlasphp\Atlas\Support\VariableRegistry::class, VariableRegistry::class);
```

### 3. `HasVariables` Trait

Applied to all pending request classes that have `instructions()`. Provides `withVariables()`, `withMessageInterpolation()`, and the interpolation logic that runs at build time.

```php
// src/Concerns/HasVariables.php
namespace Atlasphp\Atlas\Concerns;

use Atlasphp\Atlas\Support\VariableInterpolator;
use Atlasphp\Atlas\Support\VariableRegistry;

trait HasVariables
{
    /** @var array<string, mixed> Per-call variable overrides */
    protected array $variables = [];

    /** Whether to interpolate message content (not just instructions) */
    protected bool $interpolateMessages = false;

    /**
     * Set variables for this call.
     * These have the highest priority — override global registry and config.
     *
     * Supports flat and nested arrays:
     *   ->withVariables(['COMPANY' => ['NAME' => 'Acme']])
     *   ->withVariables(['LANGUAGE' => 'Spanish'])
     */
    public function withVariables(array $variables): static
    {
        $this->variables = array_replace_recursive($this->variables, $variables);

        return $this;
    }

    /**
     * Enable variable interpolation in message content.
     * By default, only instructions are interpolated.
     *
     * Use this when the message itself is a template:
     *   ->message('Write an email to {USER.NAME}')
     *   ->withMessageInterpolation()
     */
    public function withMessageInterpolation(bool $enabled = true): static
    {
        $this->interpolateMessages = $enabled;

        return $this;
    }

    /**
     * Resolve and interpolate a template string with all variable layers.
     *
     * Resolution priority: per-call → global registry → config
     */
    protected function interpolate(?string $template): ?string
    {
        if ($template === null) {
            return null;
        }

        // Short-circuit if no placeholders
        if (! VariableInterpolator::hasPlaceholders($template)) {
            return $template;
        }

        $resolved = $this->resolveVariables();

        return VariableInterpolator::interpolate($template, $resolved);
    }

    /**
     * Merge all variable layers: config → global registry → per-call.
     *
     * @return array<string, mixed> Fully resolved variables
     */
    protected function resolveVariables(): array
    {
        $registry = app(VariableRegistry::class);

        // Meta context for closure resolution
        $meta = property_exists($this, 'meta') ? $this->meta : [];

        return $registry->merge($this->variables, $meta);
    }
}
```

### 4. Pending Request Updates

Every pending request with `instructions()` uses `HasVariables` and interpolates at build time.

**`Pending\TextRequest`:**

```php
// src/Pending/TextRequest.php

class TextRequest
{
    use NormalizesMessages;
    use HasMiddleware;
    use HasMeta;
    use HasVariables;     // ← NEW
    use HasQueueDispatch; // from queue plan

    // ... existing fluent methods ...

    protected function buildRequest(): TextRequestObject
    {
        return new TextRequestObject(
            model:           $this->model,
            instructions:    $this->interpolate($this->instructions),  // ← interpolated
            message:         $this->interpolateMessages
                                ? $this->interpolate($this->message)
                                : $this->message,
            messageMedia:    $this->messageMedia,
            messages:        $this->interpolateMessages
                                ? $this->interpolateMessageArray($this->messages)
                                : $this->messages,
            maxTokens:       $this->maxTokens,
            temperature:     $this->temperature,
            schema:          $this->schema,
            tools:           $this->tools,
            providerTools:   $this->providerTools,
            providerOptions: $this->providerOptions,
        );
    }

    /**
     * Interpolate content in a messages array when message interpolation is enabled.
     */
    protected function interpolateMessageArray(array $messages): array
    {
        if (! $this->interpolateMessages) {
            return $messages;
        }

        $resolved = $this->resolveVariables();

        return array_map(function ($message) use ($resolved) {
            if ($message instanceof \Atlasphp\Atlas\Messages\Message && $message->content !== null) {
                // Clone and interpolate — don't mutate the original
                return $message->withContent(
                    VariableInterpolator::interpolate($message->content, $resolved)
                );
            }

            return $message;
        }, $messages);
    }
}
```

**`Pending\ImageRequest`:**

```php
// src/Pending/ImageRequest.php

class ImageRequest
{
    use HasMiddleware;
    use HasMeta;
    use HasVariables;     // ← NEW
    use HasQueueDispatch;

    protected function buildRequest(): ImageRequestObject
    {
        return new ImageRequestObject(
            model:           $this->model,
            instructions:    $this->interpolate($this->instructions),  // ← interpolated
            media:           $this->media,
            size:            $this->size,
            quality:         $this->quality,
            format:          $this->format,
            providerOptions: $this->providerOptions,
        );
    }
}
```

**`Pending\AudioRequest`:**

```php
// src/Pending/AudioRequest.php

class AudioRequest
{
    use HasMiddleware;
    use HasMeta;
    use HasVariables;     // ← NEW
    use HasQueueDispatch;

    protected function buildRequest(): AudioRequestObject
    {
        return new AudioRequestObject(
            model:           $this->model,
            instructions:    $this->interpolate($this->instructions),  // ← interpolated
            // ... remaining fields
        );
    }
}
```

**`Pending\VideoRequest`:** Same pattern — `use HasVariables`, interpolate `$this->instructions` in `buildRequest()`.

**`Pending\EmbedRequest`** and **`Pending\ModerateRequest`:** These have no `instructions()`, so they do NOT use `HasVariables`. No changes needed.

### 5. Updated `Pending\AgentRequest`

The agent request already has variable support. The change is to use the shared `HasVariables` trait instead of inline logic, and update `buildRequest()` to call `$this->interpolate()`.

```php
// src/Pending/AgentRequest.php

class AgentRequest
{
    use NormalizesMessages;
    use HasMiddleware;
    use HasMeta;
    use HasVariables;     // ← REPLACES inline variable handling
    use HasQueueDispatch;

    // Remove: protected array $variables = [];
    // Remove: inline interpolation logic in buildRequest()

    // withVariables() now comes from HasVariables trait

    protected function buildRequest(Agent $agent, array $tools): TextRequest
    {
        $rawInstructions = $this->instructionsOverride ?? $agent->instructions();

        return new TextRequest(
            model:           $this->resolveModel($agent),
            instructions:    $this->interpolate($rawInstructions),  // ← shared method
            message:         $this->interpolateMessages
                                ? $this->interpolate($this->message)
                                : $this->message,
            messageMedia:    $this->messageMedia,
            messages:        $this->messages,
            maxTokens:       $this->maxTokensOverride ?? $agent->maxTokens(),
            temperature:     $this->temperatureOverride ?? $agent->temperature(),
            schema:          $this->schema,
            tools:           $toolDefinitions,
            providerTools:   $providerTools,
            providerOptions: $this->resolveProviderOptions($agent),
        );
    }
}
```

### 6. `AtlasManager` Updates

Non-agent entry points need access to the `VariableRegistry` so the trait can resolve it. Since `HasVariables` uses `app(VariableRegistry::class)` (service location), no constructor changes are needed. The trait resolves the registry from the container at interpolation time.

However, for the `agent()` method which currently passes `VariableRegistry` explicitly, we remove that parameter since the trait handles it:

```php
// src/AtlasManager.php

public function agent(string $key): Pending\AgentRequest
{
    return new Pending\AgentRequest(
        key:               $key,
        agentRegistry:     $this->app->make(AgentRegistry::class),
        providerRegistry:  $this->providers,
        // variableRegistry removed — trait resolves from container
        app:               $this->app,
        events:            $this->app->make(Dispatcher::class),
    );
}
```

### 7. Queue Payload — Variables Survive Serialization

The queue plan's `toQueuePayload()` must include variables so they're available in the worker. Since variables are resolved at build time (inside the terminal method), and `executeFromPayload()` rebuilds the request from scratch, the variables need to be in the payload.

```php
// In every QueueableRequest::toQueuePayload()

public function toQueuePayload(): array
{
    return [
        // ... existing fields ...
        'variables'              => $this->variables,
        'interpolate_messages'   => $this->interpolateMessages,
    ];
}

// In every executeFromPayload()

public static function executeFromPayload(array $payload, string $terminal, ...): mixed
{
    $builder = Atlas::text($payload['provider'], $payload['model'])
        ->instructions($payload['instructions']);

    if (! empty($payload['variables'])) {
        $builder->withVariables($payload['variables']);
    }

    if ($payload['interpolate_messages'] ?? false) {
        $builder->withMessageInterpolation();
    }

    // ... build and execute
}
```

**Note:** Closures in the global registry are resolved at interpolation time inside the worker. Config variables are available in the worker via `config()`. Per-call variables (from `withVariables()`) are static arrays that serialize naturally. So all three layers work in queue context.

---

## Interpolation Flow

### Build Time (in `buildRequest()`)

```
1. Raw instructions string from consumer or agent class
     "You are {COMPANY.NAME}'s support agent. Today is {DATE}."

2. HasVariables::interpolate() called
     ├── Check hasPlaceholders() → true
     ├── resolveVariables() merges three layers:
     │     Config:   { COMPANY: { NAME: 'Acme' } }
     │     Registry: { DATE: fn() => '2025-03-20', USER: { NAME: fn($meta) => $meta['user'] ?? 'Guest' } }
     │     Per-call: { USER: { NAME: 'Tim' } }
     │
     │     Merged: { COMPANY: { NAME: 'Acme' }, DATE: '2025-03-20', USER: { NAME: 'Tim' } }
     │
     └── VariableInterpolator::interpolate(template, merged)
           ├── {COMPANY.NAME} → dot traversal → 'Acme'
           ├── {DATE} → flat key → '2025-03-20'
           └── Result: "You are Acme's support agent. Today is 2025-03-20."

3. Interpolated string passed to request object → sent to provider
```

### What Doesn't Get Interpolated

```
Messages (by default):
  ->message('The user said {something}')
  → Sent as-is: "The user said {something}"
  → Only interpolated if ->withMessageInterpolation() is called

Embed input:
  ->fromInput('text with {curly} braces')
  → Sent as-is — EmbedRequest has no instructions and no HasVariables

Moderation input:
  →  Same — no instructions, no interpolation
```

---

## Built-in Variables

The `VariableRegistry` ships with a set of built-in variables registered in the service provider. These are available across all modalities automatically.

```php
// In AtlasServiceProvider::boot()

$registry = $this->app->make(VariableRegistry::class);

// Date/Time — always fresh
$registry->register('DATE', fn () => now()->toDateString());
$registry->register('DATETIME', fn () => now()->toDateTimeString());
$registry->register('TIME', fn () => now()->format('H:i:s'));
$registry->register('TIMEZONE', fn () => config('app.timezone', 'UTC'));

// App
$registry->register('APP_NAME', fn () => config('app.name', 'Laravel'));
$registry->register('APP_ENV', fn () => config('app.env', 'production'));
$registry->register('APP_URL', fn () => config('app.url', 'http://localhost'));
```

Consumers override or extend in their own service provider:

```php
// In AppServiceProvider::boot()

$registry = app(VariableRegistry::class);

$registry->register('COMPANY', [
    'NAME'    => config('company.name'),
    'WEBSITE' => config('company.website'),
    'SUPPORT' => config('company.support_email'),
]);

// Context-dependent — receives meta array
$registry->register('USER', [
    'NAME' => fn (array $meta) => $meta['user_name'] ?? 'Guest',
    'EMAIL' => fn (array $meta) => $meta['user_email'] ?? null,
    'PLAN'  => fn (array $meta) => $meta['user_plan'] ?? 'Free',
]);
```

Then in any modality:

```php
Atlas::text(Provider::OpenAI, 'gpt-5')
    ->instructions('You are {COMPANY.NAME} support. The user is {USER.NAME} on {USER.PLAN}.')
    ->withMeta(['user_name' => 'Tim', 'user_plan' => 'Pro'])
    ->message('Help me with billing')
    ->asText();
// "You are Acme support. The user is Tim on Pro."
```

**Meta flows to variable resolution.** The `HasVariables` trait reads `$this->meta` (from `HasMeta`) and passes it to `VariableRegistry::merge()`. Closures in the registry receive the meta array. This means `withMeta()` does double duty — it provides context for middleware AND for variable resolution.

---

## Tests

### Unit Tests

**VariableInterpolatorTest:**
- `{NAME}` → flat key lookup
- `{COMPANY.NAME}` → dot notation traversal
- `{A.B.C.D}` → multi-level dot notation
- Unknown `{UNKNOWN}` → left as-is
- `{COMPANY.NAME}` with flat key `'COMPANY.NAME' => 'flat'` → flat key wins
- `{COMPANY}` where value is an array → left as-is (arrays aren't stringable)
- No placeholders → string returned unchanged
- `hasPlaceholders()` returns true/false correctly
- Mixed case: `{CompanyName}` works
- Underscores: `{SUPPORT_EMAIL}` works
- Dot + underscore: `{COMPANY.SUPPORT_EMAIL}` works
- Invalid patterns not matched: `{123}`, `{.NAME}`, `{}`, `{ NAME }`
- Null template → null returned
- Empty string → empty string returned

**VariableRegistryTest:**
- `register()` stores static value
- `register()` stores closure
- `register()` stores nested array
- `register('COMPANY.NAME', 'flat')` stores flat dotted key
- `registerMany()` registers multiple
- `unregister()` removes
- `resolve()` invokes closures with empty meta
- `resolve(['user' => 'Tim'])` passes meta to closures
- Closure with 0 params → called without args
- Closure with 1 param → receives meta array
- Nested array with closure values → closures resolved recursively
- `merge()` priority: per-call → registry → config
- `merge()` uses `array_replace_recursive` (nested keys merge correctly)
- Config values loaded from `atlas.variables`
- Registry values override config
- Runtime values override registry

**HasVariablesTest:**
- `withVariables()` stores values, returns `$this`
- `withVariables()` called twice → merges recursively
- `withMessageInterpolation()` sets flag, returns `$this`
- `interpolate()` calls `VariableInterpolator::interpolate()` with merged variables
- `interpolate(null)` returns null
- `interpolate('no placeholders')` returns string unchanged (short-circuit)
- `resolveVariables()` merges config + registry + per-call
- Meta from `HasMeta` trait flows to closure resolution

### Feature Tests

**AgentVariableTest:**
- Agent instructions with `{COMPANY.NAME}` resolved from config
- Agent instructions with `{USER.NAME}` resolved from `withVariables()`
- Agent instructions with `{DATE}` resolved from built-in registry closure
- `withVariables()` overrides registry value
- Nested: `{COMPANY.SUPPORT_EMAIL}` from config nested array
- Agent class instructions + variable override + config all merge correctly
- Variables work with `asText()`, `asStream()`, `asStructured()`

**TextVariableTest:**
- `Atlas::text()->instructions('{COMPANY.NAME}...')` interpolated
- `withVariables()` overrides config and registry
- Message NOT interpolated by default
- Message interpolated when `withMessageInterpolation()` called
- Message array interpolated when `withMessageInterpolation()` called
- `withMeta()` values available to closure variables

**ImageVariableTest:**
- `Atlas::image()->instructions('{COMPANY.NAME} logo')` interpolated
- Variables passed to image generation prompt
- `withVariables()` works on ImageRequest

**AudioVariableTest:**
- `Atlas::audio()->instructions('Welcome to {COMPANY.NAME}')` interpolated
- TTS instructions with variables

**ConfigVariableTest:**
- Config `atlas.variables` values available in all modalities
- Nested config arrays accessible via dot notation
- Config overridden by registry
- Config overridden by per-call
- Empty config → no errors

**QueueVariableTest:**
- Variables included in `toQueuePayload()`
- Variables applied correctly in `executeFromPayload()` (worker)
- Config variables available in worker context
- Registry closures resolve in worker context
- `withMessageInterpolation` flag survives queue serialization

---

## Checklist

### Core
- [ ] `VariableInterpolator` at `src/Support/VariableInterpolator.php`
- [ ] Regex `/\{([A-Za-z_][A-Za-z0-9_.]*)\}/` — supports dots, underscores, mixed case
- [ ] Dot notation traversal in `resolve()` — multi-level
- [ ] Flat key priority over dot traversal
- [ ] Arrays not returned as values (left as-is in template)
- [ ] `hasPlaceholders()` for short-circuit optimization
- [ ] Null input returns null

### VariableRegistry
- [ ] Moved from `src/Agents/` to `src/Support/`
- [ ] BC alias at `src/Agents/VariableRegistry.php`
- [ ] `register()` supports scalar, closure, array, dotted key
- [ ] `registerMany()` bulk registration
- [ ] `unregister()` removal
- [ ] `resolve(array $meta)` — invokes closures with meta
- [ ] Closure meta passing: 0-param and 1-param signatures
- [ ] Nested arrays with closures resolved recursively
- [ ] `merge(array $runtime, array $meta)` — config → registry → runtime
- [ ] Uses `array_replace_recursive` for nested merge

### HasVariables Trait
- [ ] `withVariables(array)` — per-call overrides, merges recursively
- [ ] `withMessageInterpolation(bool)` — opt-in message interpolation
- [ ] `interpolate(?string)` — resolves variables, interpolates template
- [ ] `resolveVariables()` — merges all three layers with meta
- [ ] Reads `$this->meta` from `HasMeta` trait for closure context
- [ ] Short-circuits when no placeholders detected

### Pending Request Updates
- [ ] `Pending\TextRequest` uses `HasVariables`, interpolates in `buildRequest()`
- [ ] `Pending\ImageRequest` uses `HasVariables`, interpolates in `buildRequest()`
- [ ] `Pending\AudioRequest` uses `HasVariables`, interpolates in `buildRequest()`
- [ ] `Pending\VideoRequest` uses `HasVariables`, interpolates in `buildRequest()`
- [ ] `Pending\AgentRequest` uses `HasVariables` (replaces inline logic)
- [ ] `Pending\EmbedRequest` does NOT use `HasVariables` (no instructions)
- [ ] `Pending\ModerateRequest` does NOT use `HasVariables` (no instructions)
- [ ] Message interpolation only when `$this->interpolateMessages = true`
- [ ] Messages array interpolation handles typed `Message` objects

### Config
- [ ] `atlas.variables` section in `config/atlas.php`
- [ ] Supports flat keys and nested arrays
- [ ] Documented examples in config comments

### Built-in Variables
- [ ] `DATE`, `DATETIME`, `TIME`, `TIMEZONE` registered in service provider
- [ ] `APP_NAME`, `APP_ENV`, `APP_URL` registered in service provider
- [ ] All registered as closures (resolve fresh each call)

### Service Provider
- [ ] `VariableRegistry` registered as singleton
- [ ] Built-in variables registered in `boot()`
- [ ] BC alias for `Atlasphp\Atlas\Agents\VariableRegistry`

### Queue Integration
- [ ] `variables` included in `toQueuePayload()` on all queueable requests
- [ ] `interpolate_messages` included in `toQueuePayload()`
- [ ] `executeFromPayload()` calls `withVariables()` and `withMessageInterpolation()` when rebuilding
- [ ] Config variables available in worker context
- [ ] Registry closures resolve correctly in worker

### AgentRequest Migration
- [ ] Remove inline `$this->variables` property (comes from trait)
- [ ] Remove inline interpolation in `buildRequest()` (calls `$this->interpolate()`)
- [ ] Remove `VariableRegistry` constructor parameter (trait resolves from container)
- [ ] `AtlasManager::agent()` no longer passes `VariableRegistry`

### Tests
- [ ] All unit tests pass
- [ ] All feature tests pass
- [ ] Existing agent variable tests unbroken
- [ ] All tests pass via `./vendor/bin/pest`
