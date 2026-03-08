# AGENTS

This document defines the coding conventions, architecture standards, and quality rules for this Laravel package. All agents (human or AI) must follow these rules — non-compliant contributions will be rejected.

For workflow, task management, and Claude Code-specific behavioral rules, see `CLAUDE.md`.

---

## Critical Rules

1. **Read documentation first** – Before working on any module, read the relevant documentation
2. **Documentation is the source of truth** – Documentation overrides all assumptions
3. **Run `composer check` before submitting code changes** – All lint, analyse, and test checks must pass (not required for documentation-only changes)
4. **Update documentation when code changes** – Keep docs in sync with implementation
5. **No function-level namespace imports** – Never use `use function json_decode;`
6. **Include PHPDoc on every class** – Document purpose and usage
7. **Respect layer boundaries** – Never violate the layer responsibility model
8. **Use dependency injection** – Never directly instantiate services; use contracts where appropriate
9. **Earn your abstractions** – Do not introduce indirection without concrete justification

---

## Atlas and Prism Philosophy

Atlas is a **Prism complement** that adds application-level AI concerns. It does NOT replace Prism—it enhances it.

### Key Principles

1. **Defer to Prism** — Atlas wraps Prism, never replaces it
2. **Users access Prism directly** — All Prism methods remain available through Atlas
3. **No feature duplication** — If Prism does it, don't rebuild it

### Atlas Unique Value (document fully)

| Feature        | Description                                              |
|----------------|----------------------------------------------------------|
| Agent Registry | Define agents once, resolve by key/class/instance        |
| Tool Registry  | Register tools, resolve by name, attach to agents        |
| System Prompts | Variable interpolation ({var_name}), SystemPromptBuilder |
| Pipelines      | Before/after hooks for observability and extension        |
| AgentContext   | Stateless context carrier with media support             |
| Testing        | AtlasFake for agent testing without API calls            |

### Prism Handles (link, don't document)

- Text generation, Chat responses
- Tool/function calling syntax and execution
- Structured output and schemas
- Streaming implementation
- Embeddings, Images, Audio, Moderation
- Provider configuration
- Error handling and rate limits

---

## Prism Compatibility

Atlas depends on Prism (`prism-php/prism`). Periodically review Prism releases for breaking changes.

### Review Process

1. **Check releases**: https://github.com/prism-php/prism/releases
2. **Check last review**: See `NOTES.md` "Prism Compatibility Tracking" for last reviewed version
3. **Assess impact**: Focus on changes to terminal methods, Tool API, Response/Request structure, streaming events
4. **Update NOTES.md**: Record the review date, versions, and findings
5. **If changes needed**: Create tasks for code updates

### Why Atlas is Resilient

Atlas uses a thin proxy pattern — captures Prism method calls via `__call()` for later replay, wraps terminal methods with pipeline hooks, converts Atlas tools to Prism tools. Never re-implements Prism internals. Most Prism changes are transparent to Atlas.

### Key Integration Files

| File                                         | Purpose                                |
|----------------------------------------------|----------------------------------------|
| `src/Agents/Services/AgentExecutor.php`      | Calls terminal methods                 |
| `src/Tools/Services/ToolBuilder.php`         | Converts Atlas tools to Prism tools    |
| `src/PrismProxy.php`                         | Pipeline hooks around terminal methods |
| `src/Agents/Support/PendingAgentRequest.php` | Captures Prism method calls            |

---

## Core Principles

1. Follow **PSR-12** and **Laravel Pint** formatting
2. Use **strict types** and modern **PHP 8.2+** syntax
3. All code must be **stateless**, **framework-aware**, and **application-agnostic**
4. Keep everything **self-contained** with no hard dependency on a consuming app
5. Always reference **documentation** for functional requirements and naming accuracy
6. Write clear, testable, deterministic code
7. Every class must include a **PHPDoc block** summarizing its purpose
8. **Program to interfaces, not implementations**, when multiple implementations or testing seams are required
9. **Single responsibility** – each class does one thing well
10. **Dependencies flow downward** – higher layers depend on lower layers only
11. **Earn your abstractions** – every layer must provide real value

**Example PHPDoc:**
```php
/**
 * Class UserWebhookService
 *
 * Handles webhook registration, processing, and retry logic for user-related events.
 */
```

---

## Package Structure

Each package must follow this layout. **No new top-level directories are allowed.**

```
package-root/
├── composer.json
├── AGENTS.md
├── README.md
├── docs/                 # VitePress documentation
│   ├── getting-started/
│   ├── core-concepts/
│   ├── capabilities/
│   ├── guides/
│   └── api-reference/
├── src/
│   ├── Agents/
│   ├── Contracts/
│   ├── Conversations/
│   ├── Delegation/
│   ├── Foundation/
│   ├── Logging/
│   ├── Memory/
│   ├── Processes/
│   ├── Providers/
│   ├── Streaming/
│   └── Tools/
├── config/
├── database/
│   ├── factories/
│   └── migrations/
├── tests/
│   ├── Unit/
│   └── Feature/
└── sandbox/
```

---

## Module Organization

Each domain module is self-contained with its own enums, models, and services.

### Module Structure

```
src/
├── ExampleModule/
│   ├── Enums/
│   ├── Models/
│   └── Services/
├── AnotherModule/
│   ├── Enums/
│   ├── Models/
│   ├── Services/
│   ├── Events/
│   ├── Jobs/
│   └── Exceptions/
└── Foundation/
    └── PackageServiceProvider.php
```

### Module Rules

1. **Self-contained modules** – Each module contains its own `Enums/`, `Models/`, `Services/`, `Events/`, `Jobs/`, and `Exceptions/` subdirectories as needed
2. **No top-level shared directories** – Do NOT create `src/Enums/`, `src/Models/`, or `src/Services/` directories; these belong inside their respective modules
3. **Namespacing follows structure** – e.g., `Vendor\Package\Processes\Models\Process`
4. **Cross-module references are allowed** – Modules may reference models/enums from other modules where needed
5. **Contracts remain centralized** – Interfaces that span modules live in `src/Contracts/`
6. **Foundation is infrastructure** – Service providers, configuration, and package setup live in `src/Foundation/`

### Adding to a Module

| Adding...                | Location                 |
|--------------------------|--------------------------|
| New enum for a module    | `src/{Module}/Enums/`    |
| New model for a module   | `src/{Module}/Models/`   |
| New service for a module | `src/{Module}/Services/` |
| New event for a module   | `src/{Module}/Events/`   |
| Cross-module contract    | `src/Contracts/`         |

### Creating a New Module

1. Create the module directory under `src/`
2. Add subdirectories as needed: `Enums/`, `Models/`, `Services/`, `Events/`, etc.
3. Register services in the package service provider
4. Create corresponding test directories under `tests/Unit/{Module}/` and `tests/Feature/`
5. Document the module in the appropriate VitePress docs section

---

## Layer Responsibilities

### Dependency Direction

Dependencies must flow downward only:

```
Controllers / Commands
         ↓
   Services/Domain (Business Layer)
         ↓
   Services/Models (Model Layer)
         ↓
      Integrations (External Layer)
         ↓
       Support (Utility Layer)
```

**Never allow upward dependencies.** A lower layer must never import from a higher layer.

---

### Services/Models (Model Layer)

**Purpose:** Single point of truth for all persistence operations on a model.

**Allowed:** Create, update, delete operations. Model-specific query helpers. Data normalization pre-persistence. Returning models or collections.

**Forbidden:** Orchestrating workflows. Calling other domain services. Calling integrations directly. Cross-domain logic. Event dispatching.

**Naming:** `{Model}ModelService` (e.g., `AgentModelService`)

```php
// ✅ Correct
class AgentModelService
{
    public function create(array $data): Agent
    {
        return Agent::create($data);
    }

    public function findByName(string $name): ?Agent
    {
        return Agent::where('name', $name)->first();
    }
}
```

```php
// ❌ Violation — orchestrating workflow in model layer
class AgentModelService
{
    public function createAndNotify(array $data): Agent
    {
        $agent = Agent::create($data);
        $this->notificationService->send($agent);
        event(new AgentCreated($agent));
        return $agent;
    }
}
```

---

### Services/Domain (Business Layer)

**Purpose:** Implements business logic and orchestrates workflows.

**Allowed:** Orchestrating multiple model services. Managing transactions. Dispatching events and jobs. Calling integrations through contracts. Cross-domain coordination.

**Forbidden:** Direct Eloquent queries (use model services). Direct model creation/updates. Containing integration implementation details.

**Naming:** Named by intent (e.g., `CreateAgentService`, `ProcessToolCallService`)

```php
// ✅ Correct
class CreateAgentService
{
    public function __construct(
        private AgentModelServiceContract $agentModelService,
        private AuditLoggerContract $auditLogger,
    ) {}

    public function execute(array $data): Agent
    {
        return DB::transaction(function () use ($data) {
            $agent = $this->agentModelService->create($data);
            $this->auditLogger->log('agent.created', $agent);
            event(new AgentCreated($agent));
            return $agent;
        });
    }
}
```

```php
// ❌ Violation — direct Eloquent and direct instantiation
class CreateAgentService
{
    public function execute(array $data): Agent
    {
        $agent = Agent::create($data);
        $logger = new AuditLogger();
        $logger->log('agent.created', $agent);
        return $agent;
    }
}
```

---

### Integrations (External Layer)

**Purpose:** Low-level clients for external APIs and services.

**Allowed:** API/SDK calls. Authentication handling. Request/response transformation. Retry logic and error handling. Returning DTOs or primitives.

**Forbidden:** Business logic or decisions. Database access. Workflow orchestration. Depending on domain services.

**Naming:** `{Vendor}Client` (e.g., `OpenAiClient`)

```php
// ✅ Correct
class OpenAiClient implements LlmClientContract
{
    public function complete(array $messages): CompletionResponse
    {
        $response = Http::post('https://api.openai.com/v1/chat/completions', [
            'messages' => $messages,
        ]);

        return new CompletionResponse($response->json());
    }
}
```

```php
// ❌ Violation — business logic and database access in integration
class OpenAiClient
{
    public function completeAndSave(Agent $agent, array $messages): CompletionResponse
    {
        $response = $this->complete($messages);
        $agent->conversations()->create(['response' => $response->content]);
        return $response;
    }
}
```

---

### Support (Utility Layer)

**Purpose:** Pure utilities with no side effects.

**Allowed:** Helper functions, traits, value objects, data transformers, pure functions.

**Forbidden:** Database access, external API calls, service dependencies, side effects, state mutation.

```php
// ✅ Correct
class TokenCounter
{
    public static function count(string $text): int
    {
        return (int) ceil(strlen($text) / 4);
    }
}
```

```php
// ❌ Violation — side effect via caching
class TokenCounter
{
    public function __construct(private CacheContract $cache) {}

    public function count(string $text): int
    {
        return $this->cache->remember("tokens:$text", fn() => ceil(strlen($text) / 4));
    }
}
```

---

## Contracts and Dependency Injection

### When to Create a Contract

**Create a contract when:** Multiple implementations exist or are planned. Testing requires substituting a mock or fake. The dependency crosses a package/module boundary. Requirements explicitly specify extensibility.

**Don't create a contract when:** Only one implementation exists and none are planned. The class is internal to a module and not a testing boundary. Direct instantiation is simpler and testing is not impacted.

### Injection Rules

| Do                                 | Don't                                                 |
|------------------------------------|-------------------------------------------------------|
| Inject contracts in constructor    | Instantiate services with `new`                       |
| Use Laravel's container            | Use static service locators                           |
| Type-hint interfaces               | Type-hint concrete classes (when an interface exists)  |
| Let container resolve dependencies | Manually wire dependencies                            |

```php
// ✅ Correct
class ProcessAgentResponseService
{
    public function __construct(
        private LlmClientContract $llmClient,
        private AgentModelService $agentModelService,
    ) {}
}
```

```php
// ❌ Violation — direct instantiation, service locator, static call
class ProcessAgentResponseService
{
    public function execute(): void
    {
        $agentService = new AgentModelService();
        $toolExecutor = app(ToolExecutor::class);
        AgentModelService::create($data);
    }
}
```

---

## Naming Conventions

### Class Naming

| Type            | Pattern                   | Example                         |
|-----------------|---------------------------|---------------------------------|
| Providers       | `*ServiceProvider`        | `PackageServiceProvider`        |
| Model Services  | `{Model}ModelService`     | `AgentModelService`             |
| Domain Services | `{Action}{Domain}Service` | `CreateAgentService`            |
| Contracts       | `*Contract`               | `LlmClientContract`            |
| Models          | Singular                  | `Agent`, `Tool`, `Conversation` |
| Exceptions      | `*Exception`              | `AgentNotFoundException`        |
| DTOs            | `*Data` or `*Dto`         | `CompletionResponseData`        |
| Events          | Past tense                | `AgentCreated`, `ToolExecuted`  |

### Methods

- Short, descriptive, predictable
- Boolean methods prefixed with `is`, `has`, or `can`
- Must match documented terminology
- Action methods use verbs: `create`, `execute`, `process`

---

## Code Practices

### Required

1. Business logic lives in `Services/<Domain>/`
2. Use `Services/Models/` for all persistence
3. Support classes must be pure (no side effects)
4. Config files belong in `config/` with sensible defaults
5. Write full test coverage
6. Enforce strict type declarations
7. Use custom exceptions for expected failures
8. Inject dependencies via constructor
9. Include PHPDoc block on every class
10. **Never use function-level namespace imports** (`use function ...`)

### Forbidden

1. Direct instantiation of services in methods (`new ServiceName()`)
2. Static service calls (`ServiceName::method()`)
3. Service locator pattern in business code (`app(ServiceName::class)` outside providers)
4. Business logic in models (beyond accessors/mutators/scopes)
5. Database queries in controllers
6. Upward layer dependencies
7. Circular dependencies between services
8. **Interfaces without purpose** – Don't create contracts for single-implementation classes unless testing requires it
9. **Speculative generalization** – Don't build extensibility for requirements that don't exist
10. **Proxy services** – Don't create services that just pass through to another service
11. **Wrapper classes** – Don't wrap a class just to rename methods or add no behavior
12. **DTOs that mirror models** – Don't create DTOs that are 1:1 copies of Eloquent models

---

## Code Quality

### Testability

- Keep methods focused enough to test with a single assertion or small group of related assertions
- Avoid hidden dependencies — if a method needs something, inject it via the constructor
- If testing requires mocking 5+ dependencies, the class is doing too much — split it
- Don't bury logic in untestable private methods; extract to a separate class if complex
- Avoid global state and singletons

### Complexity

- Keep methods under 20–30 lines; extract smaller methods if larger
- Avoid nesting deeper than 3 levels (use early returns, extract methods)
- If a class has 10+ public methods, consider splitting by responsibility
- Prefer explicit conditionals over clever one-liners
- If you need a comment to explain what code does, consider renaming or restructuring

### Performance

- Use eager loading (`with()`) for relationships accessed in loops
- Never run queries inside loops — batch or pre-fetch
- Consider query count when adding features; use `DB::enableQueryLog()` during development
- Use chunking (`chunk()`, `chunkById()`) for large dataset operations
- Cache expensive computations only when measured as slow, not preemptively

### Redundancy

- Extract repeated logic into model service methods or Support utilities
- If the same validation or transformation appears in 3+ places, consolidate it
- Duplication is acceptable when isolation or clarity benefits outweigh DRY
- If you intentionally duplicate, add a brief comment explaining why
- Watch for "almost identical" code — subtle differences often indicate bugs

---

## Quality Checks

```bash
composer check       # Run all checks (Pint, PHPStan, Pest) in sequence
composer lint        # Fix code style with Pint
composer lint:test   # Check code style without fixing
composer analyse     # Run PHPStan static analysis
composer test        # Run Pest tests
```

All checks must pass before submitting code changes. Not required for documentation-only changes.

---

## Sandbox Testing

The sandbox provides real API testing for validating package features against actual providers. See `sandbox/README.md` for full details.

**When to use:** Verifying API/provider integration. Testing real database persistence. Validating end-to-end behavior before deployment.

**CRITICAL:** Many features use Laravel queues. **Horizon MUST be running** for queue jobs to process:

```bash
cd sandbox
php artisan horizon              # Start Horizon (blocks terminal)
php artisan horizon &            # Or run in background
```

Horizon must be **restarted after code changes** to pick up new code. If tests seem to hang or return empty responses, check if Horizon is running.

---

## Documentation

**Public documentation:** VitePress site at [atlasphp.io](https://atlasphp.io)

**Key directories:** `docs/getting-started/`, `docs/core-concepts/`, `docs/capabilities/`, `docs/guides/`, `docs/api-reference/`

### Maintenance Rules

| Code Change         | Documentation Update                                 |
|---------------------|------------------------------------------------------|
| Adding a feature    | Update relevant VitePress docs                       |
| Changing behavior   | Update docs immediately                              |
| Adding a new module | Add documentation to appropriate section              |
| Fixing a bug        | No docs update unless behavior was misdocumented     |
| Deprecating         | Mark as deprecated in docs, add migration notes       |
| Removing            | Remove from docs completely (no "removed" comments)   |

- All code examples must be syntactically correct and runnable
- Cross-references must use relative links
- No duplicate content across files
- For Prism-level features, link to Prism documentation instead of duplicating

---

All agents must follow this document and the referenced guides. Non-compliant contributions will be rejected.