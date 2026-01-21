# Foundation Module Specification

> **Module:** `Atlasphp\Atlas\Foundation`
> **Status:** Implemented (Phase 1)

---

## Overview

The Foundation module provides core infrastructure for the Atlas package, including:
- Pipeline system for extensible middleware processing
- Extension registry base classes for pluggable functionality
- Base exception classes with static factory methods

---

## Pipeline System

### Contracts

#### PipelineContract

Interface for pipeline handlers that process data through a middleware chain.

```php
interface PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed;
}
```

**Methods:**
- `handle(mixed $data, Closure $next): mixed` - Process data and pass to next handler

### Services

#### PipelineRegistry

Registry for defining and managing pipeline handlers with priority-based ordering.

```php
$registry = app(PipelineRegistry::class);

// Define a pipeline
$registry->define('agent.before_execute', 'Runs before agent execution');

// Register handlers
$registry->register('agent.before_execute', MyHandler::class, priority: 100);
$registry->register('agent.before_execute', AnotherHandler::class, priority: 50);
```

**Methods:**
- `define(string $name, string $description = '', bool $active = true): static`
- `register(string $name, string|PipelineContract $handler, int $priority = 0): static`
- `get(string $name): array` - Returns handlers sorted by priority (highest first)
- `has(string $name): bool`
- `definitions(): array`
- `active(string $name): bool`
- `setActive(string $name, bool $active): static`
- `pipelines(): array`

#### PipelineRunner

Executes registered pipeline handlers in priority order.

```php
$runner = app(PipelineRunner::class);

// Run a pipeline
$result = $runner->run('agent.before_execute', $context);

// Run only if active
$result = $runner->runIfActive('agent.before_execute', $context);

// With destination handler
$result = $runner->run('agent.before_execute', $context, fn($data) => $data);
```

**Methods:**
- `run(string $name, mixed $data, ?Closure $destination = null): mixed`
- `runIfActive(string $name, mixed $data, ?Closure $destination = null): mixed`

---

## Extension System

### Contracts

#### ExtensionResolverContract

Interface for classes that resolve extensions by key.

```php
interface ExtensionResolverContract
{
    public function key(): string;
    public function resolve(): mixed;
    public function supports(string $key): bool;
}
```

### Services

#### AbstractExtensionRegistry

Base class for extension registries providing common registration and retrieval functionality.

```php
class MyExtensionRegistry extends AbstractExtensionRegistry
{
    // Inherits all base functionality
}

$registry = new MyExtensionRegistry();
$registry->register($resolver);
$value = $registry->get('my-key');
```

**Methods:**
- `register(ExtensionResolverContract $resolver): static`
- `get(string $key): mixed`
- `supports(string $key): bool`
- `registered(): array`
- `hasResolvers(): bool`
- `count(): int`

---

## Exceptions

### AtlasException

Base exception with static factory methods.

```php
throw AtlasException::duplicateRegistration('handler', 'my-handler');
throw AtlasException::notFound('resolver', 'unknown-key');
throw AtlasException::invalidConfiguration('API key is required');
```

**Factory Methods:**
- `duplicateRegistration(string $type, string $key): self`
- `notFound(string $type, string $key): self`
- `invalidConfiguration(string $message): self`

---

## Core Pipelines

The following pipelines are defined at boot:

| Pipeline | Description |
|----------|-------------|
| `agent.before_execute` | Runs before agent execution |
| `agent.after_execute` | Runs after agent completes |
| `agent.system_prompt.before_build` | Before building system prompt |
| `agent.system_prompt.after_build` | After building system prompt |
| `tool.before_execute` | Before tool execution |
| `tool.after_execute` | After tool completes |

---

## Usage Example

```php
use Atlasphp\Atlas\Foundation\Contracts\PipelineContract;
use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;
use Atlasphp\Atlas\Foundation\Services\PipelineRunner;

// Create a custom handler
class LoggingHandler implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        logger()->info('Pipeline executing', ['data' => $data]);

        $result = $next($data);

        logger()->info('Pipeline completed', ['result' => $result]);

        return $result;
    }
}

// Register the handler
$registry = app(PipelineRegistry::class);
$registry->register('agent.before_execute', LoggingHandler::class, priority: 1000);

// The handler will now run for all agent executions
```
