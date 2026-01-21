# Phase 2: Agents & Tools

> **Purpose:** Implement agent definitions, tool definitions, registries, and execution logic.
>
> **Prerequisites:** Phase 1 complete (Foundation & Providers modules)
>
> **Deliverables:** Complete Agents module, Tools module, with full test coverage.

---

## Overview

Phase 2 builds the core agent and tool system:

1. **Agents Module** - Agent definitions, registry, executor, system prompt builder
2. **Tools Module** - Tool definitions, registry, executor, parameter builder
3. **Value Objects** - ExecutionContext, AgentResponse, ToolContext, ToolResult

**Critical Design Constraint:** Atlas is stateless. Unlike Nexus:
- No Process/Step recording
- No Thread/Message models
- No User property in contexts
- No AssetService integration
- No async/queue dispatch

All state management is the consumer's responsibility.

---

## 1. Agents Module

**Namespace:** `Atlasphp\Atlas\Agents`

### 1.1 Directory Structure

```
src/Agents/
├── AgentDefinition.php
├── Contracts/
│   ├── AgentContract.php
│   ├── AgentRegistryContract.php
│   └── AgentExecutorContract.php
├── Enums/
│   └── AgentType.php
├── Exceptions/
│   ├── AgentException.php
│   ├── AgentNotFoundException.php
│   └── InvalidAgentException.php
├── Services/
│   ├── AgentRegistry.php
│   ├── AgentExecutor.php
│   ├── AgentResolver.php
│   ├── SystemPromptBuilder.php
│   └── AgentExtensionRegistry.php
└── Support/
    ├── ExecutionContext.php
    └── AgentResponse.php
```

### 1.2 Contracts

**File:** `src/Agents/Contracts/AgentContract.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Contracts;

/**
 * Contract defining agent configuration.
 *
 * Agents are stateless definitions that specify LLM provider, model,
 * system prompt, and available tools for execution.
 */
interface AgentContract
{
    /**
     * Get the unique identifier for this agent.
     */
    public function key(): string;

    /**
     * Get the display name.
     */
    public function name(): string;

    /**
     * Get the LLM provider name.
     */
    public function provider(): string;

    /**
     * Get the model identifier.
     */
    public function model(): string;

    /**
     * Get the system prompt template.
     *
     * May contain {variable} placeholders for interpolation.
     */
    public function systemPrompt(): string;

    /**
     * Get the agent description.
     *
     * Used for agent orchestration and documentation.
     */
    public function description(): ?string;

    /**
     * Get the tool class names available to this agent.
     *
     * @return array<int, class-string>
     */
    public function tools(): array;

    /**
     * Get provider-native tool names.
     *
     * @return array<int, string>
     */
    public function providerTools(): array;

    /**
     * Get the model temperature setting.
     */
    public function temperature(): ?float;

    /**
     * Get the maximum tokens limit.
     */
    public function maxTokens(): ?int;

    /**
     * Get the maximum tool execution steps.
     */
    public function maxSteps(): ?int;

    /**
     * Get custom extensibility settings.
     *
     * @return array<string, mixed>
     */
    public function settings(): array;
}
```

---

**File:** `src/Agents/Contracts/AgentRegistryContract.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Contracts;

/**
 * Contract for agent registry.
 */
interface AgentRegistryContract
{
    /**
     * Register an agent by class name.
     *
     * @param  class-string<AgentContract>  $agentClass
     */
    public function register(string $agentClass, bool $override = false): void;

    /**
     * Register an agent instance.
     */
    public function registerInstance(AgentContract $agent, bool $override = false): void;

    /**
     * Get an agent by key.
     *
     * @throws \Atlasphp\Atlas\Agents\Exceptions\AgentNotFoundException
     */
    public function get(string $key): AgentContract;

    /**
     * Check if an agent exists.
     */
    public function has(string $key): bool;

    /**
     * Get all registered agents.
     *
     * @return array<string, AgentContract>
     */
    public function all(): array;
}
```

---

**File:** `src/Agents/Contracts/AgentExecutorContract.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Contracts;

use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Prism\Prism\Schema\Schema;

/**
 * Contract for agent execution.
 */
interface AgentExecutorContract
{
    /**
     * Execute an agent with the given input.
     */
    public function execute(
        AgentContract $agent,
        string $input,
        ?ExecutionContext $context = null,
        ?Schema $schema = null,
    ): AgentResponse;
}
```

### 1.3 Enums

**File:** `src/Agents/Enums/AgentType.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Enums;

/**
 * Agent execution types.
 */
enum AgentType: string
{
    case Api = 'api';
    case Cli = 'cli';
}
```

**Note:** For Atlas, this enum may be simplified or removed since we focus on API execution. Include for future extensibility.

### 1.4 Exceptions

**File:** `src/Agents/Exceptions/AgentException.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Exceptions;

use Atlasphp\Atlas\Foundation\Exceptions\AtlasException;

/**
 * Base exception for agent-related errors.
 */
class AgentException extends AtlasException
{
    /**
     * Create exception for execution failure.
     */
    public static function executionFailed(string $agentKey, string $reason): self
    {
        return new self("Agent '{$agentKey}' execution failed: {$reason}");
    }

    /**
     * Create exception for invalid configuration.
     */
    public static function invalidConfiguration(string $agentKey, string $reason): self
    {
        return new self("Agent '{$agentKey}' has invalid configuration: {$reason}");
    }
}
```

---

**File:** `src/Agents/Exceptions/AgentNotFoundException.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Exceptions;

/**
 * Exception thrown when an agent is not found.
 */
class AgentNotFoundException extends AgentException
{
    /**
     * Create exception for missing agent.
     */
    public static function forKey(string $key): self
    {
        return new self("Agent with key '{$key}' not found.");
    }

    /**
     * Create exception for missing class.
     */
    public static function forClass(string $class): self
    {
        return new self("Agent class '{$class}' not found or not registered.");
    }
}
```

---

**File:** `src/Agents/Exceptions/InvalidAgentException.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Exceptions;

/**
 * Exception thrown when an agent is invalid.
 */
class InvalidAgentException extends AgentException
{
    /**
     * Create exception for missing contract implementation.
     */
    public static function doesNotImplementContract(string $class): self
    {
        return new self("Class '{$class}' does not implement AgentContract.");
    }

    /**
     * Create exception for instantiation failure.
     */
    public static function cannotInstantiate(string $class, string $reason): self
    {
        return new self("Cannot instantiate agent class '{$class}': {$reason}");
    }

    /**
     * Create exception for duplicate registration.
     */
    public static function duplicateKey(string $key): self
    {
        return new self("Agent with key '{$key}' is already registered.");
    }
}
```

### 1.5 Base Class

**File:** `src/Agents/AgentDefinition.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;

/**
 * Base class for agent definitions.
 *
 * Extend this class to create custom agents with sensible defaults
 * for optional methods.
 */
abstract class AgentDefinition implements AgentContract
{
    /**
     * Get the unique identifier for this agent.
     */
    abstract public function key(): string;

    /**
     * Get the display name.
     */
    abstract public function name(): string;

    /**
     * Get the LLM provider name.
     */
    abstract public function provider(): string;

    /**
     * Get the model identifier.
     */
    abstract public function model(): string;

    /**
     * Get the system prompt template.
     */
    abstract public function systemPrompt(): string;

    /**
     * Get the agent description.
     */
    public function description(): ?string
    {
        return null;
    }

    /**
     * Get the tool class names available to this agent.
     *
     * @return array<int, class-string>
     */
    public function tools(): array
    {
        return [];
    }

    /**
     * Get provider-native tool names.
     *
     * @return array<int, string>
     */
    public function providerTools(): array
    {
        return [];
    }

    /**
     * Get the model temperature setting.
     */
    public function temperature(): ?float
    {
        return null;
    }

    /**
     * Get the maximum tokens limit.
     */
    public function maxTokens(): ?int
    {
        return null;
    }

    /**
     * Get the maximum tool execution steps.
     */
    public function maxSteps(): ?int
    {
        return null;
    }

    /**
     * Get custom extensibility settings.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return [];
    }
}
```

### 1.6 Support Classes (Value Objects)

**File:** `src/Agents/Support/ExecutionContext.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Support;

/**
 * Immutable execution context for agent execution.
 *
 * Contains messages for conversation history, variables for system
 * prompt interpolation, and metadata for pipeline middleware.
 *
 * Note: There is no user property. If consumers need user context,
 * they pass it via variables or metadata.
 */
final readonly class ExecutionContext
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $variables
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public array $messages = [],
        public array $variables = [],
        public array $metadata = [],
    ) {}

    /**
     * Create new context with updated messages.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function withMessages(array $messages): self
    {
        return new self($messages, $this->variables, $this->metadata);
    }

    /**
     * Create new context with merged variables.
     *
     * @param  array<string, mixed>  $variables
     */
    public function withVariables(array $variables): self
    {
        return new self(
            $this->messages,
            array_merge($this->variables, $variables),
            $this->metadata,
        );
    }

    /**
     * Create new context with merged metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            $this->messages,
            $this->variables,
            array_merge($this->metadata, $metadata),
        );
    }

    /**
     * Get a variable value.
     */
    public function getVariable(string $key, mixed $default = null): mixed
    {
        return $this->variables[$key] ?? $default;
    }

    /**
     * Get a metadata value.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Check if context has messages.
     */
    public function hasMessages(): bool
    {
        return count($this->messages) > 0;
    }
}
```

---

**File:** `src/Agents/Support/AgentResponse.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Support;

/**
 * Immutable response from agent execution.
 *
 * Contains text output, structured data, tool calls, usage statistics,
 * and arbitrary metadata.
 */
final readonly class AgentResponse
{
    /**
     * @param  array<int, array<string, mixed>>  $toolCalls
     * @param  array<string, int>  $usage
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ?string $text = null,
        public mixed $structured = null,
        public array $toolCalls = [],
        public array $usage = [],
        public array $metadata = [],
    ) {}

    /**
     * Create a text-only response.
     */
    public static function text(string $text): self
    {
        return new self(text: $text);
    }

    /**
     * Create a structured data response.
     */
    public static function structured(mixed $data): self
    {
        return new self(structured: $data);
    }

    /**
     * Create a response with tool calls.
     *
     * @param  array<int, array<string, mixed>>  $toolCalls
     */
    public static function withToolCalls(array $toolCalls): self
    {
        return new self(toolCalls: $toolCalls);
    }

    /**
     * Create an empty response.
     */
    public static function empty(): self
    {
        return new self();
    }

    /**
     * Check if response has text content.
     */
    public function hasText(): bool
    {
        return $this->text !== null && $this->text !== '';
    }

    /**
     * Check if response has structured data.
     */
    public function hasStructured(): bool
    {
        return $this->structured !== null;
    }

    /**
     * Check if response has tool calls.
     */
    public function hasToolCalls(): bool
    {
        return count($this->toolCalls) > 0;
    }

    /**
     * Check if response has usage data.
     */
    public function hasUsage(): bool
    {
        return count($this->usage) > 0;
    }

    /**
     * Get total tokens used.
     */
    public function totalTokens(): int
    {
        return ($this->usage['input'] ?? 0) + ($this->usage['output'] ?? 0);
    }

    /**
     * Get a metadata value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Create new response with merged metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            $this->text,
            $this->structured,
            $this->toolCalls,
            $this->usage,
            array_merge($this->metadata, $metadata),
        );
    }

    /**
     * Create new response with usage data.
     *
     * @param  array<string, int>  $usage
     */
    public function withUsage(array $usage): self
    {
        return new self(
            $this->text,
            $this->structured,
            $this->toolCalls,
            $usage,
            $this->metadata,
        );
    }
}
```

### 1.7 Services

**File:** `src/Agents/Services/AgentRegistry.php`

**Purpose:** Register and retrieve agents by key or class.

**Constructor Dependencies:**
- `Illuminate\Contracts\Container\Container $container`

**Properties:**
- `private array $agents = []` - Agents keyed by their key()

**Methods:**
```php
public function register(string $agentClass, bool $override = false): void
{
    // 1. Check class implements AgentContract
    // 2. Instantiate via container
    // 3. Check for duplicate key (unless override)
    // 4. Store in $agents by key()
}

public function registerInstance(AgentContract $agent, bool $override = false): void
{
    // 1. Check for duplicate key (unless override)
    // 2. Store in $agents by key()
}

public function get(string $key): AgentContract
{
    // Throw AgentNotFoundException if not found
}

public function has(string $key): bool

public function all(): array

public function keys(): array

public function unregister(string $key): bool

public function count(): int

public function clear(): void
```

**Implementation Notes:**
- Uses container for class instantiation (enables DI in agent constructors)
- Throws `InvalidAgentException` for non-contract classes
- Throws `InvalidAgentException` for duplicate keys (unless override=true)
- Throws `AgentNotFoundException` when key not found

**Reference:** `nexus/src/Agents/Services/AgentRegistry.php`

---

**File:** `src/Agents/Services/AgentResolver.php`

**Purpose:** Resolve agent from key, class name, or instance.

**Constructor Dependencies:**
- `AgentRegistryContract $registry`
- `Illuminate\Contracts\Container\Container $container`

**Methods:**
```php
public function resolve(string|AgentContract $agent): AgentContract
{
    // If AgentContract instance, return as-is
    // If class-string, check registry first, then instantiate via container
    // If string (key), lookup in registry
    // Throw AgentNotFoundException if not resolvable
}
```

**Implementation Notes:**
- Enables flexible agent specification (key, class, or instance)
- Prioritizes registry lookup over container instantiation

---

**File:** `src/Agents/Services/AgentExecutor.php`

**Purpose:** Execute agents via Prism (stateless, no Process/Step recording).

**Constructor Dependencies:**
- `PrismBuilder $prismBuilder`
- `ToolBuilder $toolBuilder`
- `SystemPromptBuilder $systemPromptBuilder`
- `PipelineRunner $pipelineRunner`
- `UsageExtractorRegistry $usageExtractorRegistry`

**Methods:**
```php
public function execute(
    AgentContract $agent,
    string $input,
    ?ExecutionContext $context = null,
    ?Schema $schema = null,
): AgentResponse
```

**Execution Flow:**

1. **Create context if null:**
   ```php
   $context ??= new ExecutionContext();
   ```

2. **Pipeline: before_execute:**
   ```php
   $data = $this->pipelineRunner->runIfActive(
       'agent.before_execute',
       ['agent' => $agent, 'context' => $context, 'input' => $input],
   );
   ```

3. **Build system prompt:**
   ```php
   $systemPrompt = $this->systemPromptBuilder->build($agent, $context);
   ```

4. **Build tools (if no schema):**
   ```php
   $tools = $schema === null
       ? $this->toolBuilder->buildForAgent($agent, $this->createToolContext($context))
       : [];
   ```

5. **Build and execute Prism request:**
   ```php
   if ($schema !== null) {
       $request = $this->prismBuilder->forStructured($agent, $schema, $input, $systemPrompt);
   } elseif ($context->hasMessages()) {
       // Append user input to messages
       $messages = array_merge($context->messages, [['role' => 'user', 'content' => $input]]);
       $request = $this->prismBuilder->forMessages($agent, $messages, $systemPrompt, $tools);
   } else {
       $request = $this->prismBuilder->forPrompt($agent, $input, $systemPrompt, $tools);
   }

   $prismResponse = $request->generate();
   ```

6. **Extract usage and build response:**
   ```php
   $usage = $this->usageExtractorRegistry->extract($prismResponse, $agent->provider());

   $response = new AgentResponse(
       text: $prismResponse->text ?? null,
       structured: $prismResponse->structured ?? null,
       toolCalls: $this->extractToolCalls($prismResponse),
       usage: $usage,
   );
   ```

7. **Pipeline: after_execute:**
   ```php
   $data = $this->pipelineRunner->runIfActive(
       'agent.after_execute',
       ['agent' => $agent, 'context' => $context, 'input' => $input, 'response' => $response],
   );

   return $data['response'];
   ```

**Implementation Notes:**
- No Process/Step creation (unlike Nexus)
- No async support (synchronous only)
- Tool execution handled by Prism's multi-step execution
- Pipeline hooks allow consumers to extend behavior

**Reference:** `nexus/src/Agents/Services/AgentExecutor.php` (simplified for stateless)

---

**File:** `src/Agents/Services/SystemPromptBuilder.php`

**Purpose:** Build system prompts with variable interpolation and sections.

**Constructor Dependencies:**
- `PipelineRunner $pipelineRunner`

**Properties:**
- `private array $variables = []` - Custom variable resolvers
- `private array $sections = []` - Named sections with priorities

**Methods:**
```php
public function build(AgentContract $agent, ExecutionContext $context): string
{
    // 1. Get raw prompt from agent
    $prompt = $agent->systemPrompt();

    // 2. Pipeline: before_build
    $prompt = $this->pipelineRunner->runIfActive(
        'agent.system_prompt.before_build',
        ['prompt' => $prompt, 'agent' => $agent, 'context' => $context],
    )['prompt'];

    // 3. Interpolate variables from context
    $prompt = $this->interpolateVariables($prompt, $agent, $context);

    // 4. Append sections by priority
    $prompt = $this->appendSections($prompt, $agent, $context);

    // 5. Pipeline: after_build
    $prompt = $this->pipelineRunner->runIfActive(
        'agent.system_prompt.after_build',
        ['prompt' => $prompt, 'agent' => $agent, 'context' => $context],
    )['prompt'];

    return $prompt;
}

public function registerVariable(string $name, callable $resolver): void
{
    // Register custom variable resolver
    // Resolver signature: fn(?AgentContract $agent, ?ExecutionContext $context): string
}

public function unregisterVariable(string $name): void

public function addSection(string $name, string|callable $content, int $priority = 50): void
{
    // Add named section with priority (higher = later in prompt)
}

public function removeSection(string $name): void

public function clearSections(): void

private function interpolateVariables(
    string $prompt,
    AgentContract $agent,
    ExecutionContext $context,
): string
{
    // 1. Replace context variables: {variable_name} -> $context->getVariable('variable_name')
    // 2. Replace custom registered variables
    // 3. Pattern: /\{([a-zA-Z_][a-zA-Z0-9_]*)\}/
}

private function appendSections(
    string $prompt,
    AgentContract $agent,
    ExecutionContext $context,
): string
{
    // Sort sections by priority ascending
    // Append each section's content (resolve callables)
}
```

**Variable Interpolation:**
- Pattern: `{variable_name}` (snake_case or camelCase)
- Source priority: context variables > custom resolvers
- Unmatched variables left as-is or removed (configurable)

**Important Difference from Nexus:**
- No built-in variables (`{AGENT_NAME}`, `{USER_NAME}`, etc.)
- All variables must be passed via `ExecutionContext::$variables`
- Consumers provide everything they need

**Reference:** `nexus/src/Agents/Services/SystemPromptBuilder.php` (simplified)

---

**File:** `src/Agents/Services/AgentExtensionRegistry.php`

**Purpose:** Extension registry for agent-specific extensions.

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Services;

use Atlasphp\Atlas\Foundation\Services\AbstractExtensionRegistry;

/**
 * Registry for agent extensions.
 */
class AgentExtensionRegistry extends AbstractExtensionRegistry
{
    // Inherits all functionality from AbstractExtensionRegistry
}
```

---

## 2. Tools Module

**Namespace:** `Atlasphp\Atlas\Tools`

### 2.1 Directory Structure

```
src/Tools/
├── ToolDefinition.php
├── Contracts/
│   ├── ToolContract.php
│   └── ToolRegistryContract.php
├── Exceptions/
│   ├── ToolException.php
│   └── ToolNotFoundException.php
├── Services/
│   ├── ToolRegistry.php
│   ├── ToolExecutor.php
│   ├── ToolBuilder.php
│   └── ToolExtensionRegistry.php
└── Support/
    ├── ToolContext.php
    ├── ToolParameter.php
    └── ToolResult.php
```

### 2.2 Contracts

**File:** `src/Tools/Contracts/ToolContract.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Contracts;

use Atlasphp\Atlas\Tools\Support\ToolContext;
use Atlasphp\Atlas\Tools\Support\ToolParameter;
use Atlasphp\Atlas\Tools\Support\ToolResult;

/**
 * Contract defining a tool that agents can use.
 */
interface ToolContract
{
    /**
     * Get the tool name (used by LLM to invoke).
     */
    public function name(): string;

    /**
     * Get the tool description (helps LLM understand when to use).
     */
    public function description(): string;

    /**
     * Get the tool parameters.
     *
     * @return array<int, ToolParameter>
     */
    public function parameters(): array;

    /**
     * Execute the tool with given arguments.
     *
     * @param  array<string, mixed>  $args
     */
    public function handle(array $args, ToolContext $context): ToolResult;
}
```

---

**File:** `src/Tools/Contracts/ToolRegistryContract.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Contracts;

/**
 * Contract for tool registry.
 */
interface ToolRegistryContract
{
    /**
     * Register a tool by class name.
     *
     * @param  class-string<ToolContract>  $toolClass
     */
    public function register(string $toolClass): void;

    /**
     * Register a tool instance.
     */
    public function registerInstance(ToolContract $tool): void;

    /**
     * Get a tool by name.
     *
     * @throws \Atlasphp\Atlas\Tools\Exceptions\ToolNotFoundException
     */
    public function get(string $name): ToolContract;

    /**
     * Check if a tool exists.
     */
    public function has(string $name): bool;

    /**
     * Get all registered tools.
     *
     * @return array<string, ToolContract>
     */
    public function all(): array;

    /**
     * Get only specified tools.
     *
     * @param  array<int, string>  $names
     * @return array<string, ToolContract>
     */
    public function only(array $names): array;
}
```

### 2.3 Exceptions

**File:** `src/Tools/Exceptions/ToolException.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Exceptions;

use Atlasphp\Atlas\Foundation\Exceptions\AtlasException;

/**
 * Base exception for tool-related errors.
 */
class ToolException extends AtlasException
{
    /**
     * Create exception for execution failure.
     */
    public static function executionFailed(string $toolName, string $reason): self
    {
        return new self("Tool '{$toolName}' execution failed: {$reason}");
    }

    /**
     * Create exception for invalid tool.
     */
    public static function invalid(string $class, string $reason): self
    {
        return new self("Invalid tool '{$class}': {$reason}");
    }

    /**
     * Create exception for duplicate registration.
     */
    public static function duplicate(string $name): self
    {
        return new self("Tool '{$name}' is already registered.");
    }
}
```

---

**File:** `src/Tools/Exceptions/ToolNotFoundException.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Exceptions;

/**
 * Exception thrown when a tool is not found.
 */
class ToolNotFoundException extends ToolException
{
    /**
     * Create exception for missing tool.
     */
    public static function forName(string $name): self
    {
        return new self("Tool '{$name}' not found.");
    }
}
```

### 2.4 Base Class

**File:** `src/Tools/ToolDefinition.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools;

use Atlasphp\Atlas\Tools\Contracts\ToolContract;
use Atlasphp\Atlas\Tools\Support\ToolContext;
use Atlasphp\Atlas\Tools\Support\ToolParameter;
use Atlasphp\Atlas\Tools\Support\ToolResult;
use Prism\Prism\Tool;

/**
 * Base class for tool definitions.
 *
 * Extend this class to create custom tools with sensible defaults.
 */
abstract class ToolDefinition implements ToolContract
{
    /**
     * Get the tool name.
     */
    abstract public function name(): string;

    /**
     * Get the tool description.
     */
    abstract public function description(): string;

    /**
     * Execute the tool.
     *
     * @param  array<string, mixed>  $args
     */
    abstract public function handle(array $args, ToolContext $context): ToolResult;

    /**
     * Get the tool parameters.
     *
     * @return array<int, ToolParameter>
     */
    public function parameters(): array
    {
        return [];
    }

    /**
     * Convert to a Prism Tool.
     *
     * @param  callable|null  $handler  Optional wrapper handler
     */
    public function toPrismTool(?callable $handler = null): Tool
    {
        $tool = new Tool(
            name: $this->name(),
            description: $this->description(),
            parameters: $this->buildParameterSchema(),
        );

        if ($handler !== null) {
            $tool->using($handler);
        }

        return $tool;
    }

    /**
     * Build JSON schema from parameters.
     *
     * @return array<string, mixed>
     */
    protected function buildParameterSchema(): array
    {
        $properties = [];
        $required = [];

        foreach ($this->parameters() as $param) {
            $properties[$param->name] = $param->toSchema();
            if ($param->required) {
                $required[] = $param->name;
            }
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ];
    }
}
```

### 2.5 Support Classes (Value Objects)

**File:** `src/Tools/Support/ToolContext.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Support;

/**
 * Immutable context for tool execution.
 *
 * Contains metadata that consumers can pass to tools.
 * Unlike Nexus, there is no process/step/thread/user - Atlas is stateless.
 */
final readonly class ToolContext
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public array $metadata = [],
    ) {}

    /**
     * Get a metadata value.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Check if metadata key exists.
     */
    public function hasMeta(string $key): bool
    {
        return array_key_exists($key, $this->metadata);
    }

    /**
     * Create new context with merged metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self(array_merge($this->metadata, $metadata));
    }
}
```

---

**File:** `src/Tools/Support/ToolParameter.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Support;

/**
 * Tool parameter definition.
 *
 * Immutable value object representing a tool parameter with
 * type information for JSON schema generation.
 */
final readonly class ToolParameter
{
    /**
     * @param  array<int, string>|null  $enum
     * @param  array<string, mixed>|null  $items
     * @param  array<string, ToolParameter>|null  $properties
     */
    public function __construct(
        public string $name,
        public string $type,
        public string $description,
        public bool $required = true,
        public mixed $default = null,
        public ?array $enum = null,
        public ?array $items = null,
        public ?array $properties = null,
    ) {}

    /**
     * Create a string parameter.
     */
    public static function string(
        string $name,
        string $description,
        bool $required = true,
        ?string $default = null,
    ): self {
        return new self($name, 'string', $description, $required, $default);
    }

    /**
     * Create an integer parameter.
     */
    public static function integer(
        string $name,
        string $description,
        bool $required = true,
        ?int $default = null,
    ): self {
        return new self($name, 'integer', $description, $required, $default);
    }

    /**
     * Create a number (float) parameter.
     */
    public static function number(
        string $name,
        string $description,
        bool $required = true,
        ?float $default = null,
    ): self {
        return new self($name, 'number', $description, $required, $default);
    }

    /**
     * Create a boolean parameter.
     */
    public static function boolean(
        string $name,
        string $description,
        bool $required = true,
        ?bool $default = null,
    ): self {
        return new self($name, 'boolean', $description, $required, $default);
    }

    /**
     * Create an enum parameter.
     *
     * @param  array<int, string>  $values
     */
    public static function enum(
        string $name,
        string $description,
        array $values,
        bool $required = true,
        ?string $default = null,
    ): self {
        return new self($name, 'string', $description, $required, $default, $values);
    }

    /**
     * Create an array parameter.
     *
     * @param  array<string, mixed>|null  $items  Item schema
     */
    public static function array(
        string $name,
        string $description,
        ?array $items = null,
        bool $required = true,
    ): self {
        return new self($name, 'array', $description, $required, null, null, $items);
    }

    /**
     * Create an object parameter.
     *
     * @param  array<string, ToolParameter>  $properties
     */
    public static function object(
        string $name,
        string $description,
        array $properties,
        bool $required = true,
    ): self {
        return new self($name, 'object', $description, $required, null, null, null, $properties);
    }

    /**
     * Convert to JSON schema format.
     *
     * @return array<string, mixed>
     */
    public function toSchema(): array
    {
        $schema = [
            'type' => $this->type,
            'description' => $this->description,
        ];

        if ($this->default !== null) {
            $schema['default'] = $this->default;
        }

        if ($this->enum !== null) {
            $schema['enum'] = $this->enum;
        }

        if ($this->items !== null) {
            $schema['items'] = $this->items;
        }

        if ($this->properties !== null) {
            $schema['properties'] = [];
            $required = [];
            foreach ($this->properties as $name => $param) {
                $schema['properties'][$name] = $param->toSchema();
                if ($param->required) {
                    $required[] = $name;
                }
            }
            if (count($required) > 0) {
                $schema['required'] = $required;
            }
        }

        return $schema;
    }
}
```

---

**File:** `src/Tools/Support/ToolResult.php`

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Support;

/**
 * Result of tool execution.
 *
 * Immutable value object containing the tool's text output
 * and error status.
 */
final readonly class ToolResult
{
    public function __construct(
        public string $text,
        public bool $isError = false,
    ) {}

    /**
     * Create a successful text result.
     */
    public static function text(string $text): self
    {
        return new self($text, false);
    }

    /**
     * Create an error result.
     */
    public static function error(string $message): self
    {
        return new self($message, true);
    }

    /**
     * Create a JSON result.
     *
     * @param  array<string, mixed>  $data
     */
    public static function json(array $data): self
    {
        return new self(json_encode($data, JSON_THROW_ON_ERROR), false);
    }

    /**
     * Convert to array.
     *
     * @return array{text: string, is_error: bool}
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'is_error' => $this->isError,
        ];
    }
}
```

### 2.6 Services

**File:** `src/Tools/Services/ToolRegistry.php`

**Purpose:** Register and retrieve tools by name.

**Constructor Dependencies:**
- `Illuminate\Contracts\Container\Container $container`

**Properties:**
- `private array $tools = []` - Tools keyed by name()

**Methods:**
```php
public function register(string $toolClass): void
{
    // 1. Check class implements ToolContract
    // 2. Instantiate via container
    // 3. Check for duplicate name
    // 4. Store in $tools by name()
}

public function registerInstance(ToolContract $tool): void
{
    // 1. Check for duplicate name
    // 2. Store in $tools by name()
}

public function get(string $name): ToolContract
{
    // Throw ToolNotFoundException if not found
}

public function has(string $name): bool

public function all(): array

public function only(array $names): array
{
    // Return subset of tools matching names
    // Throw ToolNotFoundException for any missing name
}

public function names(): array

public function unregister(string $name): bool

public function count(): int

public function clear(): void
```

**Reference:** `nexus/src/Tools/Services/ToolRegistry.php`

---

**File:** `src/Tools/Services/ToolExecutor.php`

**Purpose:** Execute tools with pipeline hooks (no ProcessStepTool recording).

**Constructor Dependencies:**
- `PipelineRunner $pipelineRunner`

**Methods:**
```php
public function execute(
    ToolContract $tool,
    array $args,
    ToolContext $context,
): ToolResult
{
    // 1. Pipeline: before_execute
    $data = $this->pipelineRunner->runIfActive(
        'tool.before_execute',
        ['tool' => $tool, 'args' => $args, 'context' => $context],
    );

    // 2. Execute tool
    try {
        $result = $tool->handle($data['args'], $data['context']);
    } catch (\Throwable $e) {
        $result = ToolResult::error($e->getMessage());
    }

    // 3. Pipeline: after_execute
    $data = $this->pipelineRunner->runIfActive(
        'tool.after_execute',
        ['tool' => $tool, 'args' => $args, 'context' => $context, 'result' => $result],
    );

    return $data['result'];
}
```

**Implementation Notes:**
- No ProcessStepTool recording (unlike Nexus)
- No AssetService integration
- Simple execution with pipeline hooks for extensibility
- Catches exceptions and converts to error results

**Reference:** `nexus/src/Tools/Services/ToolExecutor.php` (simplified)

---

**File:** `src/Tools/Services/ToolBuilder.php`

**Purpose:** Build Prism tools from agent tool definitions.

**Constructor Dependencies:**
- `ToolRegistryContract $toolRegistry`
- `ToolExecutor $toolExecutor`
- `Illuminate\Contracts\Container\Container $container`

**Methods:**
```php
public function buildForAgent(
    AgentContract $agent,
    ToolContext $context,
): array
{
    $prismTools = [];

    foreach ($agent->tools() as $toolClass) {
        $tool = $this->resolveTool($toolClass);
        $prismTools[] = $this->wrapTool($tool, $context);
    }

    return $prismTools;
}

private function resolveTool(string $toolClass): ToolContract
{
    // 1. Check registry first
    // 2. Fall back to container instantiation
}

private function wrapTool(ToolContract $tool, ToolContext $context): Tool
{
    // Convert to Prism Tool with executor wrapper
    return $tool->toPrismTool(
        fn (array $args) => $this->toolExecutor->execute($tool, $args, $context)->text
    );
}
```

**Reference:** `nexus/src/Tools/Services/ToolBuilder.php`

---

**File:** `src/Tools/Services/ToolExtensionRegistry.php`

**Purpose:** Extension registry for tool-specific extensions.

```php
<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Services;

use Atlasphp\Atlas\Foundation\Services\AbstractExtensionRegistry;

/**
 * Registry for tool extensions.
 */
class ToolExtensionRegistry extends AbstractExtensionRegistry
{
    // Inherits all functionality from AbstractExtensionRegistry
}
```

---

## 3. Service Provider Updates

Update `AtlasServiceProvider` to register Phase 2 services:

```php
// In register() method:

// Agent services
$this->app->singleton(AgentRegistryContract::class, AgentRegistry::class);
$this->app->singleton(AgentExecutorContract::class, AgentExecutor::class);
$this->app->singleton(AgentResolver::class);
$this->app->singleton(SystemPromptBuilder::class);
$this->app->singleton(AgentExtensionRegistry::class);

// Tool services
$this->app->singleton(ToolRegistryContract::class, ToolRegistry::class);
$this->app->singleton(ToolExecutor::class);
$this->app->singleton(ToolBuilder::class);
$this->app->singleton(ToolExtensionRegistry::class);
```

---

## 4. Tests Required

### 4.1 Unit Tests

**Agents Module:**

```
tests/Unit/Agents/
├── AgentDefinitionTest.php
│   ├── it implements agent contract
│   ├── it returns key and name
│   ├── it returns provider and model
│   ├── it returns system prompt
│   ├── it returns default values for optional methods
│   └── it returns custom settings
│
├── AgentRegistryTest.php
│   ├── it registers agent by class
│   ├── it registers agent instance
│   ├── it throws on duplicate key
│   ├── it allows override
│   ├── it retrieves agent by key
│   ├── it throws when agent not found
│   ├── it checks if agent exists
│   ├── it returns all agents
│   └── it unregisters agents
│
├── AgentResolverTest.php
│   ├── it resolves from agent instance
│   ├── it resolves from registry key
│   ├── it resolves from class name
│   └── it throws for unresolvable agent
│
├── AgentExecutorTest.php
│   ├── it executes agent with input
│   ├── it executes with message history
│   ├── it executes with structured output
│   ├── it interpolates system prompt variables
│   ├── it runs before_execute pipeline
│   ├── it runs after_execute pipeline
│   ├── it extracts usage from response
│   └── it handles tool calls
│
├── SystemPromptBuilderTest.php
│   ├── it returns raw prompt when no variables
│   ├── it interpolates context variables
│   ├── it registers custom variables
│   ├── it adds sections with priority
│   ├── it runs before_build pipeline
│   └── it runs after_build pipeline
│
├── ExecutionContextTest.php
│   ├── it creates with messages
│   ├── it creates with variables
│   ├── it creates with metadata
│   ├── it returns immutable with updated messages
│   ├── it returns immutable with merged variables
│   ├── it returns immutable with merged metadata
│   └── it retrieves variable values
│
└── AgentResponseTest.php
    ├── it creates text response
    ├── it creates structured response
    ├── it creates response with tool calls
    ├── it checks for text content
    ├── it checks for structured content
    ├── it calculates total tokens
    └── it returns immutable with metadata
```

**Tools Module:**

```
tests/Unit/Tools/
├── ToolDefinitionTest.php
│   ├── it implements tool contract
│   ├── it returns name and description
│   ├── it returns parameters
│   ├── it converts to prism tool
│   └── it builds parameter schema
│
├── ToolRegistryTest.php
│   ├── it registers tool by class
│   ├── it registers tool instance
│   ├── it throws on duplicate name
│   ├── it retrieves tool by name
│   ├── it throws when tool not found
│   ├── it checks if tool exists
│   ├── it returns all tools
│   ├── it returns only specified tools
│   └── it unregisters tools
│
├── ToolExecutorTest.php
│   ├── it executes tool with args
│   ├── it runs before_execute pipeline
│   ├── it runs after_execute pipeline
│   ├── it catches exceptions as errors
│   └── it passes context to tool
│
├── ToolBuilderTest.php
│   ├── it builds tools for agent
│   ├── it resolves from registry
│   ├── it resolves from container
│   └── it wraps with executor
│
├── ToolParameterTest.php
│   ├── it creates string parameter
│   ├── it creates integer parameter
│   ├── it creates number parameter
│   ├── it creates boolean parameter
│   ├── it creates enum parameter
│   ├── it creates array parameter
│   ├── it creates object parameter
│   ├── it converts to json schema
│   └── it handles required flag
│
├── ToolResultTest.php
│   ├── it creates text result
│   ├── it creates error result
│   ├── it creates json result
│   └── it converts to array
│
└── ToolContextTest.php
    ├── it creates with metadata
    ├── it retrieves metadata values
    ├── it checks metadata existence
    └── it returns immutable with metadata
```

### 4.2 Feature Tests

```
tests/Feature/
├── AgentExecutionTest.php
│   ├── it executes agent end to end (mocked Prism)
│   ├── it handles multi-turn conversation
│   ├── it produces structured output
│   └── it executes with tools (mocked)
│
└── ToolExecutionTest.php
    ├── it registers and executes tool
    ├── it handles tool errors gracefully
    └── it passes context through execution
```

### 4.3 Test Fixtures

Create test fixtures in `tests/Fixtures/`:

**File:** `tests/Fixtures/TestAgent.php`

```php
<?php

namespace Atlasphp\Atlas\Tests\Fixtures;

use Atlasphp\Atlas\Agents\AgentDefinition;

class TestAgent extends AgentDefinition
{
    public function key(): string
    {
        return 'test-agent';
    }

    public function name(): string
    {
        return 'Test Agent';
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
        return 'You are {agent_name}. Help {user_name}.';
    }

    public function tools(): array
    {
        return [TestTool::class];
    }
}
```

**File:** `tests/Fixtures/TestTool.php`

```php
<?php

namespace Atlasphp\Atlas\Tests\Fixtures;

use Atlasphp\Atlas\Tools\ToolDefinition;
use Atlasphp\Atlas\Tools\Support\ToolContext;
use Atlasphp\Atlas\Tools\Support\ToolParameter;
use Atlasphp\Atlas\Tools\Support\ToolResult;

class TestTool extends ToolDefinition
{
    public function name(): string
    {
        return 'test_tool';
    }

    public function description(): string
    {
        return 'A test tool for unit tests';
    }

    public function parameters(): array
    {
        return [
            ToolParameter::string('input', 'Test input'),
        ];
    }

    public function handle(array $args, ToolContext $context): ToolResult
    {
        return ToolResult::text('Result: ' . $args['input']);
    }
}
```

---

## 5. Documentation Required

### 5.1 SPEC Documents

**File:** `docs/spec/SPEC-Agents.md`

Contents:
- AgentContract interface
- AgentDefinition base class
- AgentRegistry service
- AgentExecutor execution flow
- SystemPromptBuilder variable interpolation
- ExecutionContext value object
- AgentResponse value object
- Pipeline hooks (before/after execute, prompt building)
- Extension registry

**File:** `docs/spec/SPEC-Tools.md`

Contents:
- ToolContract interface
- ToolDefinition base class
- ToolRegistry service
- ToolExecutor execution flow
- ToolBuilder service
- ToolParameter factory methods and schema generation
- ToolResult value object
- ToolContext value object
- Pipeline hooks (before/after execute)
- Extension registry

---

## 6. Reference Files (Nexus)

Extract patterns and implementations from:

| Atlas File | Nexus Reference |
|------------|-----------------|
| `AgentContract.php` | `nexus/src/Agents/Contracts/AgentContract.php` |
| `AgentDefinition.php` | `nexus/src/Agents/AgentDefinition.php` |
| `AgentRegistry.php` | `nexus/src/Agents/Services/AgentRegistry.php` |
| `AgentExecutor.php` | `nexus/src/Agents/Services/AgentExecutor.php` |
| `SystemPromptBuilder.php` | `nexus/src/Agents/Services/SystemPromptBuilder.php` |
| `AgentResponse.php` | `nexus/src/Agents/Support/AgentResponse.php` |
| `ToolContract.php` | `nexus/src/Tools/Contracts/ToolContract.php` |
| `ToolDefinition.php` | `nexus/src/Tools/ToolDefinition.php` |
| `ToolRegistry.php` | `nexus/src/Tools/Services/ToolRegistry.php` |
| `ToolBuilder.php` | `nexus/src/Tools/Services/ToolBuilder.php` |
| `ToolParameter.php` | `nexus/src/Tools/Support/ToolParameter.php` |
| `ToolResult.php` | `nexus/src/Tools/Support/ToolResult.php` |

**When extracting from Nexus:**
- Remove database/model dependencies (Process, Step, Thread, Message)
- Remove User/Authenticatable references
- Remove AssetService integration
- Remove ProcessStepTool recording
- Remove async/queue dispatch
- Keep pure execution logic only

---

## 7. Acceptance Criteria

### 7.1 Agents Module

- [ ] `AgentContract` defines all required methods
- [ ] `AgentDefinition` provides sensible defaults
- [ ] `AgentRegistry` registers and retrieves agents
- [ ] `AgentResolver` resolves from key, class, or instance
- [ ] `AgentExecutor` executes agents via Prism (mocked)
- [ ] `SystemPromptBuilder` interpolates variables from context
- [ ] `ExecutionContext` is immutable with fluent updates
- [ ] `AgentResponse` captures text, structured, tool calls, usage
- [ ] Pipeline hooks fire at correct points

### 7.2 Tools Module

- [ ] `ToolContract` defines required methods
- [ ] `ToolDefinition` converts to Prism Tool
- [ ] `ToolRegistry` registers and retrieves tools
- [ ] `ToolExecutor` executes with pipeline hooks
- [ ] `ToolBuilder` builds tools for agents
- [ ] `ToolParameter` generates JSON schema
- [ ] `ToolResult` supports text, error, json
- [ ] `ToolContext` provides consumer metadata

### 7.3 Code Quality

- [ ] All classes have PHPDoc blocks
- [ ] All exceptions have static factory methods
- [ ] No database access (stateless)
- [ ] No user/session management
- [ ] Strict types in all files
- [ ] PSR-12 compliant (Pint passes)
- [ ] PHPStan level 6 passes

### 7.4 Tests

- [ ] Unit tests for all services
- [ ] Feature tests for integration
- [ ] Test fixtures provided
- [ ] No real API calls (all mocked)
- [ ] `composer check` passes

---

## 8. File Checklist

Phase 2 creates these files:

```
src/
├── Agents/
│   ├── AgentDefinition.php
│   ├── Contracts/
│   │   ├── AgentContract.php
│   │   ├── AgentRegistryContract.php
│   │   └── AgentExecutorContract.php
│   ├── Enums/
│   │   └── AgentType.php
│   ├── Exceptions/
│   │   ├── AgentException.php
│   │   ├── AgentNotFoundException.php
│   │   └── InvalidAgentException.php
│   ├── Services/
│   │   ├── AgentRegistry.php
│   │   ├── AgentExecutor.php
│   │   ├── AgentResolver.php
│   │   ├── SystemPromptBuilder.php
│   │   └── AgentExtensionRegistry.php
│   └── Support/
│       ├── ExecutionContext.php
│       └── AgentResponse.php
└── Tools/
    ├── ToolDefinition.php
    ├── Contracts/
    │   ├── ToolContract.php
    │   └── ToolRegistryContract.php
    ├── Exceptions/
    │   ├── ToolException.php
    │   └── ToolNotFoundException.php
    ├── Services/
    │   ├── ToolRegistry.php
    │   ├── ToolExecutor.php
    │   ├── ToolBuilder.php
    │   └── ToolExtensionRegistry.php
    └── Support/
        ├── ToolContext.php
        ├── ToolParameter.php
        └── ToolResult.php

tests/
├── Fixtures/
│   ├── TestAgent.php
│   └── TestTool.php
├── Unit/
│   ├── Agents/
│   │   ├── AgentDefinitionTest.php
│   │   ├── AgentRegistryTest.php
│   │   ├── AgentResolverTest.php
│   │   ├── AgentExecutorTest.php
│   │   ├── SystemPromptBuilderTest.php
│   │   ├── ExecutionContextTest.php
│   │   └── AgentResponseTest.php
│   └── Tools/
│       ├── ToolDefinitionTest.php
│       ├── ToolRegistryTest.php
│       ├── ToolExecutorTest.php
│       ├── ToolBuilderTest.php
│       ├── ToolParameterTest.php
│       ├── ToolResultTest.php
│       └── ToolContextTest.php
└── Feature/
    ├── AgentExecutionTest.php
    └── ToolExecutionTest.php

docs/
└── spec/
    ├── SPEC-Agents.md
    └── SPEC-Tools.md
```

---

## 9. Implementation Order

Recommended order for implementing Phase 2:

1. **Agent Contracts & Exceptions**
   - `AgentContract.php`
   - `AgentRegistryContract.php`
   - `AgentExecutorContract.php`
   - All exceptions

2. **Agent Value Objects**
   - `ExecutionContext.php` (with tests)
   - `AgentResponse.php` (with tests)

3. **Agent Base Class**
   - `AgentDefinition.php` (with tests)
   - `AgentType.php` enum

4. **Agent Services**
   - `AgentRegistry.php` (with tests)
   - `AgentResolver.php` (with tests)
   - `SystemPromptBuilder.php` (with tests)
   - `AgentExtensionRegistry.php`

5. **Tool Contracts & Exceptions**
   - `ToolContract.php`
   - `ToolRegistryContract.php`
   - All exceptions

6. **Tool Value Objects**
   - `ToolContext.php` (with tests)
   - `ToolParameter.php` (with tests)
   - `ToolResult.php` (with tests)

7. **Tool Base Class**
   - `ToolDefinition.php` (with tests)

8. **Tool Services**
   - `ToolRegistry.php` (with tests)
   - `ToolExecutor.php` (with tests)
   - `ToolBuilder.php` (with tests)
   - `ToolExtensionRegistry.php`

9. **Agent Executor**
   - `AgentExecutor.php` (with tests)
   - Update `AtlasServiceProvider.php`

10. **Test Fixtures**
    - `TestAgent.php`
    - `TestTool.php`

11. **Feature Tests**
    - `AgentExecutionTest.php`
    - `ToolExecutionTest.php`

12. **Documentation**
    - `docs/spec/SPEC-Agents.md`
    - `docs/spec/SPEC-Tools.md`

13. **Final Verification**
    - Run `composer check`
    - Verify all acceptance criteria
