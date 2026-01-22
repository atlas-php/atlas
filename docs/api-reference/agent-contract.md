# AgentContract

The `AgentContract` interface defines the required methods for all agent implementations.

## Interface Definition

```php
namespace Atlasphp\Atlas\Agents\Contracts;

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

## Required Methods

### provider()

Returns the AI provider name.

```php
public function provider(): string
```

**Returns:** Provider identifier (`openai`, `anthropic`, etc.)

### model()

Returns the model identifier.

```php
public function model(): string
```

**Returns:** Model name (`gpt-4o`, `claude-3-sonnet`, etc.)

### systemPrompt()

Returns the system prompt template.

```php
public function systemPrompt(): string
```

**Returns:** System prompt string (may contain `{variable}` placeholders)

## Optional Methods

### key()

Returns the unique identifier for registry lookup.

```php
public function key(): string
```

**Returns:** Kebab-case identifier
**Default:** Generated from class name

### name()

Returns the display name.

```php
public function name(): string
```

**Returns:** Human-readable name
**Default:** Generated from class name

### type()

Returns the agent execution type.

```php
public function type(): AgentType
```

**Returns:** `AgentType::Api` or `AgentType::Cli`
**Default:** `AgentType::Api`

### description()

Returns the agent description.

```php
public function description(): ?string
```

**Returns:** Description string or `null`
**Default:** `null`

### tools()

Returns tool classes available to the agent.

```php
public function tools(): array
```

**Returns:** Array of tool class names
**Default:** `[]`

### providerTools()

Returns provider-specific tools.

```php
public function providerTools(): array
```

**Returns:** Array of provider tool configurations
**Default:** `[]`

### temperature()

Returns the sampling temperature.

```php
public function temperature(): ?float
```

**Returns:** Temperature value (0-2) or `null` for default
**Default:** `null`

### maxTokens()

Returns the maximum response tokens.

```php
public function maxTokens(): ?int
```

**Returns:** Token limit or `null` for default
**Default:** `null`

### maxSteps()

Returns the maximum tool use iterations.

```php
public function maxSteps(): ?int
```

**Returns:** Step limit or `null` for no limit
**Default:** `null`

### settings()

Returns additional provider settings.

```php
public function settings(): array
```

**Returns:** Key-value array of settings
**Default:** `[]`

## AgentDefinition Base Class

The `AgentDefinition` abstract class provides sensible defaults:

```php
use Atlasphp\Atlas\Agents\AgentDefinition;

class MyAgent extends AgentDefinition
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
        return 'You are a helpful assistant.';
    }
}
```

### Default Implementations

| Method | Default Behavior |
|--------|------------------|
| `key()` | Class name in kebab-case |
| `name()` | Class name with spaces |
| `type()` | `AgentType::Api` |
| `description()` | `null` |
| `tools()` | `[]` |
| `providerTools()` | `[]` |
| `temperature()` | `null` |
| `maxTokens()` | `null` |
| `maxSteps()` | `null` |
| `settings()` | `[]` |

## AgentType Enum

```php
namespace Atlasphp\Atlas\Agents\Enums;

enum AgentType: string
{
    case Api = 'api';   // Standard API-based execution
    case Cli = 'cli';   // Command-line execution (reserved)
}
```

## AgentRegistryContract

Interface for the agent registry:

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

### Registry Methods

**register()** — Register an agent class:

```php
$registry->register(MyAgent::class);
$registry->register(MyAgent::class, override: true);
```

**registerInstance()** — Register an agent instance:

```php
$registry->registerInstance(new MyAgent());
```

**get()** — Retrieve an agent by key:

```php
$agent = $registry->get('my-agent');
```

**has()** — Check if agent exists:

```php
if ($registry->has('my-agent')) {
    // ...
}
```

**all()** — Get all registered agents:

```php
$agents = $registry->all();
```

## Complete Example

```php
<?php

namespace App\Agents;

use Atlasphp\Atlas\Agents\AgentDefinition;
use Atlasphp\Atlas\Agents\Enums\AgentType;

class CustomerSupportAgent extends AgentDefinition
{
    public function key(): string
    {
        return 'customer-support';
    }

    public function name(): string
    {
        return 'Customer Support Agent';
    }

    public function description(): ?string
    {
        return 'Handles customer inquiries and support requests';
    }

    public function type(): AgentType
    {
        return AgentType::Api;
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
        You are a customer support agent for {company_name}.
        Help the customer with their inquiry.
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

    public function temperature(): ?float
    {
        return 0.7;
    }

    public function maxTokens(): ?int
    {
        return 2048;
    }

    public function maxSteps(): ?int
    {
        return 5;
    }

    public function settings(): array
    {
        return [
            'top_p' => 0.9,
        ];
    }
}
```

## Next Steps

- [ToolContract](/api-reference/tool-contract) — Tool interface
- [Response Objects](/api-reference/response-objects) — AgentResponse
- [Creating Agents](/guides/Creating-Agents) — Step-by-step guide
