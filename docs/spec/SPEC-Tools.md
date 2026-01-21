# Tools Module Specification

> **Module:** `Atlasphp\Atlas\Tools`
> **Status:** Implemented (Phase 2)

---

## Overview

The Tools module provides infrastructure for defining and executing AI function tools. Key features:

- Tool definitions with typed parameters
- Registry for managing tool instances
- Executor with pipeline middleware support
- Builder for converting tools to Prism format
- Support for all JSON Schema parameter types

**Design Principle:** Tools are stateless functions that agents can invoke. Consumer applications control what tools are available and how they access external resources.

---

## Contracts

### ToolContract

Interface that all tool definitions must implement.

```php
interface ToolContract
{
    public function name(): string;
    public function description(): string;
    public function parameters(): array;
    public function handle(array $args, ToolContext $context): ToolResult;
}
```

### ToolRegistryContract

Interface for registering and retrieving tools.

```php
interface ToolRegistryContract
{
    public function register(string $toolClass, bool $override = false): void;
    public function registerInstance(ToolContract $tool, bool $override = false): void;
    public function get(string $name): ToolContract;
    public function has(string $name): bool;
    public function all(): array;
    public function only(array $names): array;
}
```

---

## Support Classes

### ToolContext

Immutable context for tool execution.

```php
$context = new ToolContext(['key' => 'value']);

// Accessors
$context->getMeta('key', 'default');
$context->hasMeta('key');

// Immutable updates
$newContext = $context->withMetadata(['new_key' => 'value']);
$newContext = $context->mergeMetadata(['additional' => 'data']);
```

### ToolParameter

Defines a tool parameter with type information.

```php
// String parameter
ToolParameter::string('query', 'The search query', required: true);

// Integer parameter
ToolParameter::integer('limit', 'Max results', required: false, default: 10);

// Number parameter (float)
ToolParameter::number('threshold', 'Confidence threshold');

// Boolean parameter
ToolParameter::boolean('verbose', 'Enable verbose output', required: false, default: false);

// Enum parameter
ToolParameter::enum('format', 'Output format', ['json', 'xml', 'csv']);

// Array parameter
ToolParameter::array('tags', 'List of tags', items: ['type' => 'string']);

// Object parameter
ToolParameter::object('filter', 'Search filters', [
    ToolParameter::string('field', 'Field to filter'),
    ToolParameter::string('value', 'Filter value'),
]);
```

### ToolResult

Result returned from tool execution.

```php
// Success result
return ToolResult::text('Operation completed successfully');

// JSON result
return ToolResult::json(['status' => 'ok', 'count' => 42]);

// Error result
return ToolResult::error('Failed to process: invalid input');

// Check status
$result->succeeded();
$result->failed();

// Convert to array
$result->toArray();  // ['text' => '...', 'is_error' => false]
```

---

## Services

### ToolRegistry

Manages tool registrations keyed by tool name.

```php
$registry = app(ToolRegistryContract::class);

// Register by class
$registry->register(SearchTool::class);

// Register instance
$registry->registerInstance(new SearchTool);

// Query
$registry->has('search');
$registry->get('search');
$registry->all();
$registry->only(['search', 'calculator']);
$registry->names();
$registry->count();

// Management
$registry->unregister('search');
$registry->clear();
```

### ToolExecutor

Executes tools with pipeline middleware support.

```php
$executor = app(ToolExecutor::class);

$result = $executor->execute(
    $tool,
    ['query' => 'search term'],
    $context,
);

if ($result->succeeded()) {
    echo $result->text;
}
```

Errors are caught and returned as `ToolResult::error()`.

### ToolBuilder

Builds Prism Tool objects for agent execution.

```php
$builder = app(ToolBuilder::class);

// Build from agent's tool list
$prismTools = $builder->buildForAgent($agent, $toolContext);

// Build from class names
$prismTools = $builder->buildFromClasses([SearchTool::class], $toolContext);

// Build from instances
$prismTools = $builder->buildFromInstances([$tool1, $tool2], $toolContext);
```

---

## ToolDefinition Base Class

Abstract base class providing conversion to Prism format.

```php
class SearchTool extends ToolDefinition
{
    public function name(): string
    {
        return 'search_web';
    }

    public function description(): string
    {
        return 'Search the web for information.';
    }

    public function parameters(): array
    {
        return [
            ToolParameter::string('query', 'The search query'),
            ToolParameter::integer('limit', 'Max results', false, 10),
        ];
    }

    public function handle(array $args, ToolContext $context): ToolResult
    {
        $query = $args['query'];
        $limit = $args['limit'] ?? 10;

        // Perform search...
        $results = $this->search($query, $limit);

        return ToolResult::json($results);
    }
}
```

---

## Pipeline Hooks

The following pipelines are available for customization:

| Pipeline | Description |
|----------|-------------|
| `tool.before_execute` | Runs before tool execution |
| `tool.after_execute` | Runs after tool execution completes |

### Pipeline Data Structure

**before_execute:**
```php
[
    'tool' => ToolContract,
    'args' => array,
    'context' => ToolContext,
]
```

**after_execute:**
```php
[
    'tool' => ToolContract,
    'args' => array,
    'context' => ToolContext,
    'result' => ToolResult,
]
```

---

## Usage Examples

### Complete Tool Definition

```php
class CalculatorTool extends ToolDefinition
{
    public function name(): string
    {
        return 'calculator';
    }

    public function description(): string
    {
        return 'Perform basic arithmetic calculations.';
    }

    public function parameters(): array
    {
        return [
            ToolParameter::enum('operation', 'The operation', ['add', 'subtract', 'multiply', 'divide']),
            ToolParameter::number('a', 'First number'),
            ToolParameter::number('b', 'Second number'),
        ];
    }

    public function handle(array $args, ToolContext $context): ToolResult
    {
        $a = $args['a'];
        $b = $args['b'];

        $result = match ($args['operation']) {
            'add' => $a + $b,
            'subtract' => $a - $b,
            'multiply' => $a * $b,
            'divide' => $b !== 0.0 ? $a / $b : null,
        };

        if ($result === null) {
            return ToolResult::error('Cannot divide by zero');
        }

        return ToolResult::text("Result: {$result}");
    }
}
```

### Tool with External Dependencies

```php
class DatabaseQueryTool extends ToolDefinition
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function name(): string
    {
        return 'query_database';
    }

    public function description(): string
    {
        return 'Query the database for records.';
    }

    public function parameters(): array
    {
        return [
            ToolParameter::string('table', 'Table name'),
            ToolParameter::object('where', 'Query conditions', [
                ToolParameter::string('column', 'Column name'),
                ToolParameter::string('value', 'Value to match'),
            ]),
            ToolParameter::integer('limit', 'Max records', false, 100),
        ];
    }

    public function handle(array $args, ToolContext $context): ToolResult
    {
        try {
            $results = $this->db
                ->table($args['table'])
                ->where($args['where']['column'], $args['where']['value'])
                ->limit($args['limit'] ?? 100)
                ->get();

            return ToolResult::json($results->toArray());
        } catch (\Exception $e) {
            return ToolResult::error("Query failed: {$e->getMessage()}");
        }
    }
}
```

### Using Tools with Agents

```php
class AssistantAgent extends AgentDefinition
{
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
        return 'You are a helpful assistant with access to tools.';
    }

    public function tools(): array
    {
        return [
            CalculatorTool::class,
            SearchTool::class,
            DatabaseQueryTool::class,
        ];
    }

    public function maxSteps(): ?int
    {
        return 5; // Allow up to 5 tool calls
    }
}
```
