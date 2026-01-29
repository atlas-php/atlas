# Agents

> **For AI Agents and Contributors:** This guide defines the mandatory conventions and coding standards for all work on this Laravel package repository.

---

## Critical Rules

**These rules are non-negotiable. Violations will result in rejected work.**

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
| Pipelines      | Before/after hooks for observability and extension       |
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
3. **Assess impact**: Focus on changes to:
   - Terminal methods (`asText()`, `asStructured()`, `asStream()`)
   - Tool API (`Tool::as()`, tool handlers)
   - Response/Request object structure
   - Streaming event format
4. **Update NOTES.md**: Record the review date, versions, and findings
5. **If changes needed**: Create tasks for code updates

### Why Atlas is Resilient

Atlas uses a thin proxy pattern:
- Captures Prism method calls via `__call()` for later replay
- Wraps terminal methods with pipeline hooks
- Converts Atlas tools to Prism tools
- Never re-implements Prism internals

Most Prism changes are transparent to Atlas.

### Key Integration Files

| File                                         | Purpose                                |
|----------------------------------------------|----------------------------------------|
| `src/Agents/Services/AgentExecutor.php`      | Calls terminal methods                 |
| `src/Tools/Services/ToolBuilder.php`         | Converts Atlas tools to Prism tools    |
| `src/PrismProxy.php`                         | Pipeline hooks around terminal methods |
| `src/Agents/Support/PendingAgentRequest.php` | Captures Prism method calls            |

---

## Documentation

**Public documentation:** VitePress site at [atlasphp.io](https://atlasphp.io)

**Key documentation files:**
- `docs/getting-started/` — Installation and configuration
- `docs/core-concepts/` — Agents, Tools, Pipelines, System Prompts
- `docs/capabilities/` — Chat, Streaming, Embeddings, etc.
- `docs/guides/` — How-to guides
- `docs/api-reference/` — Full API documentation

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

**Each domain module is self-contained with its own enums, models, and services.**

Domain modules encapsulate all related code within their directory. This follows domain-driven design principles for better code isolation, discoverability, and maintainability.

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
3. **Namespacing follows structure** – e.g., `Vendor\Package\Processes\Models\Process`, `Vendor\Package\Conversations\Enums\MessageRole`
4. **Cross-module references are allowed** – Modules may reference models/enums from other modules where needed
5. **Contracts remain centralized** – Interfaces that span modules live in `src/Contracts/`
6. **Foundation is infrastructure** – Service providers, configuration, and package setup live in `src/Foundation/`

### Adding to a Module

When adding new code:

| Adding...                | Location                 |
|--------------------------|--------------------------|
| New enum for a module    | `src/{Module}/Enums/`    |
| New model for a module   | `src/{Module}/Models/`   |
| New service for a module | `src/{Module}/Services/` |
| New event for a module   | `src/{Module}/Events/`   |
| Cross-module contract    | `src/Contracts/`         |

### Creating a New Module

When creating a new domain module:

1. Create the module directory under `src/` (e.g., `src/Memory/`)
2. Add subdirectories as needed: `Enums/`, `Models/`, `Services/`, `Events/`, etc.
3. Register services in the package service provider
4. Create corresponding test directories under `tests/Unit/{Module}/` and `tests/Feature/`
5. Document the module in the appropriate VitePress docs section

---

## Sandbox Testing

The sandbox provides real API testing for validating package features against actual providers.

**See `sandbox/README.md` for:**
- Real API use-case testing with acceptance criteria
- Database verification guidelines
- Instructions for creating new test commands

**When to use sandbox:**
- Verifying API/provider integration works correctly
- Testing real database persistence and retrieval
- Validating end-to-end feature behavior before deployment
- Understanding expected behavior when implementing new features

**CRITICAL: Running Horizon for Queue Processing**

Many features (delegation, async processing) use Laravel queues. **Horizon MUST be running** for queue jobs to process:

```bash
cd sandbox
php artisan horizon              # Start Horizon (blocks terminal)
# OR run in background:
php artisan horizon &            # Start in background
```

**Important:**
- Horizon must be **restarted after code changes** to pick up new code
- If tests seem to hang or return empty responses, check if Horizon is running
- Use `php artisan horizon:terminate` to stop Horizon gracefully

**Quick commands:**
```bash
cd sandbox
php artisan migrate:fresh          # Clean database for fresh testing
php artisan horizon &              # Start queue worker (REQUIRED for delegation)
php artisan test:custom-tools      # Test custom tool execution
php artisan package:chat           # Interactive agent conversation (if provided)
```

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

**Allowed:**
- Create, update, delete operations
- Model-specific query helpers
- Data normalization pre-persistence
- Returning models or collections

**Forbidden:**
- Orchestrating multi-step workflows
- Calling other domain services
- Calling integrations directly
- Cross-domain logic
- Event dispatching (leave to domain layer)

**Naming:** `{Model}ModelService` (e.g., `ContactModelService`, `TaskModelService`)

**Example – Correct:**
```php
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

**Example – Violation:**
```php
class AgentModelService
{
    // ❌ VIOLATION: Orchestrating workflow
    public function createAndNotify(array $data): Agent
    {
        $agent = Agent::create($data);
        $this->notificationService->send($agent); // ❌ Cross-domain
        event(new AgentCreated($agent)); // ❌ Event dispatching
        return $agent;
    }
}
```

---

### Services/<Domain> (Business Layer)

**Purpose:** Implements business logic and orchestrates workflows.

**Allowed:**
- Implementing documented use cases
- Orchestrating multiple model services
- Managing database transactions
- Dispatching events and jobs
- Calling integrations through contracts
- Cross-domain coordination

**Forbidden:**
- Direct Eloquent queries (use model services)
- Direct model creation/updates (use model services)
- Containing integration implementation details

**Naming:** Named by intent (e.g., `CreateAgentService`, `ProcessToolCallService`)

**Example – Correct:**
```php
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

**Example – Violation:**
```php
class CreateAgentService
{
    public function execute(array $data): Agent
    {
        // ❌ VIOLATION: Direct Eloquent query
        $agent = Agent::create($data);

        // ❌ VIOLATION: Direct integration instantiation
        $logger = new AuditLogger();
        $logger->log('agent.created', $agent);

        return $agent;
    }
}
```

---

### Integrations (External Layer)

**Purpose:** Low-level clients for external APIs and services.

**Allowed:**
- API/SDK calls
- Authentication handling
- Request/response transformation
- Retry logic and error handling
- Returning DTOs or primitives

**Forbidden:**
- Business logic or decisions
- Database access
- Workflow orchestration
- Depending on domain services

**Naming:** `{Vendor}Client` (e.g., `OpenAiClient`, `AnthropicClient`)

**Example – Correct:**
```php
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

**Example – Violation:**
```php
class OpenAiClient
{
    // ❌ VIOLATION: Business logic in integration
    public function completeAndSave(Agent $agent, array $messages): CompletionResponse
    {
        $response = $this->complete($messages);

        // ❌ VIOLATION: Database access
        $agent->conversations()->create([
            'response' => $response->content,
        ]);

        return $response;
    }
}
```

---

### Support (Utility Layer)

**Purpose:** Pure utilities with no side effects.

**Allowed:**
- Helper functions
- Traits
- Value objects
- Data transformers
- Pure functions

**Forbidden:**
- Database access
- External API calls
- Service dependencies
- Side effects of any kind
- State mutation

**Example – Correct:**
```php
class TokenCounter
{
    public static function count(string $text): int
    {
        // Pure function – no side effects
        return (int) ceil(strlen($text) / 4);
    }
}
```

**Example – Violation:**
```php
class TokenCounter
{
    // ❌ VIOLATION: External dependency
    public function __construct(private CacheContract $cache) {}

    public function count(string $text): int
    {
        // ❌ VIOLATION: Side effect (caching)
        return $this->cache->remember("tokens:$text", fn() => ceil(strlen($text) / 4));
    }
}
```

---

## Contracts and Dependency Injection

### When to Create a Contract

**Create a contract when:**
- Multiple implementations exist or are planned (e.g., provider clients)
- Testing requires substituting a mock or fake
- The dependency crosses a package/module boundary
- Requirements explicitly specify extensibility

**Don’t create a contract when:**
- Only one implementation exists and none are planned
- The class is internal to a module and not a testing boundary
- Direct instantiation is simpler and testing is not impacted

### Injection Rules

| Do                                 | Don’t                                                 |
|------------------------------------|-------------------------------------------------------|
| Inject contracts in constructor    | Instantiate services with `new`                       |
| Use Laravel’s container            | Use static service locators                           |
| Type-hint interfaces               | Type-hint concrete classes (when an interface exists) |
| Let container resolve dependencies | Manually wire dependencies                            |

**Example – Correct (with contract):**
```php
class ProcessAgentResponseService
{
    public function __construct(
        private LlmClientContract $llmClient,
        private AgentModelService $agentModelService,
    ) {}
}
```

**Example – Over-Engineered:**
```php
// ❌ Unnecessary: Interface for single implementation with no test benefit
interface AgentModelServiceContract
{
    public function create(array $data): Agent;
}

class AgentModelService implements AgentModelServiceContract
{
    public function create(array $data): Agent
    {
        return Agent::create($data);
    }
}
```

**Example – Violation:**
```php
class ProcessAgentResponseService
{
    public function execute(): void
    {
        // ❌ VIOLATION: Direct instantiation
        $agentService = new AgentModelService();

        // ❌ VIOLATION: Service locator pattern
        $toolExecutor = app(ToolExecutor::class);

        // ❌ VIOLATION: Static call to service
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
| Contracts       | `*Contract`               | `LlmClientContract`             |
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

### Required Practices

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

### Forbidden Practices

1. Direct instantiation of services in methods (`new ServiceName()`)
2. Static service calls (`ServiceName::method()`)
3. Service locator pattern in business code (`app(ServiceName::class)` outside providers)
4. Business logic in models (beyond accessors/mutators/scopes)
5. Database queries in controllers
6. Upward layer dependencies
7. Circular dependencies between services
8. **Interfaces without purpose** – Don’t create contracts for single-implementation classes unless testing requires it
9. **Speculative generalization** – Don’t build extensibility for requirements that don’t exist
10. **Proxy services** – Don’t create services that just pass through to another service
11. **Wrapper classes** – Don’t wrap a class just to rename methods or add no behavior
12. **DTOs that mirror models** – Don’t create DTOs that are 1:1 copies of Eloquent models

---

## Code Quality

### Testability

Write code that can be tested with minimal setup and mocking.

**Guidelines:**
- Keep methods focused enough to test with a single assertion or small group of related assertions
- Avoid hidden dependencies—if a method needs something, inject it via the constructor
- If testing requires mocking 5+ dependencies, the class is doing too much—split it
- Don’t bury logic in private methods that can’t be tested; extract to a separate testable class if complex
- Avoid global state and singletons that make tests unreliable

**Example – Testable:**
```php
class CalculateTokenUsageService
{
    public function __construct(
        private TokenCounterContract $tokenCounter,
    ) {}

    public function execute(string $prompt, string $response): int
    {
        return $this->tokenCounter->count($prompt) + $this->tokenCounter->count($response);
    }
}
```

**Example – Hard to Test:**
```php
class CalculateTokenUsageService
{
    public function execute(string $prompt, string $response): int
    {
        // ❌ Hidden dependency – can’t mock TokenCounter
        $counter = new TokenCounter();

        // ❌ Or worse, static call
        return TokenCounter::count($prompt) + TokenCounter::count($response);
    }
}
```

### Complexity

Keep code simple and readable. Complex code hides bugs and slows development.

**Guidelines:**
- Keep methods under 20–30 lines; extract smaller methods if larger
- Avoid nesting deeper than 3 levels (use early returns, extract methods)
- If a class has 10+ public methods, consider splitting by responsibility
- Prefer explicit conditionals over clever one-liners
- If you need a comment to explain what code does, consider renaming or restructuring

### Performance

Write efficient code that doesn’t create unnecessary bottlenecks.

**Guidelines:**
- Use eager loading (`with()`) for relationships accessed in loops
- Never run queries inside loops—batch or pre-fetch
- Consider query count when adding features; use `DB::enableQueryLog()` during development
- Use chunking (`chunk()`, `chunkById()`) for large dataset operations
- Cache expensive computations only when measured as slow, not preemptively

### Redundancy

Avoid duplication that leads to inconsistent behavior, but don’t over-DRY.

**Guidelines:**
- Extract repeated logic into model service methods or Support utilities
- If the same validation or transformation appears in 3+ places, consolidate it
- Duplication is acceptable when isolation or clarity benefits outweigh DRY
- If you intentionally duplicate, add a brief comment explaining why
- Watch for “almost identical” code—subtle differences often indicate bugs

---

## Quality Checks

**Run all quality checks with a single command:**
```bash
composer check
```

This runs Pint, PHPStan, and Pest in sequence. If any check fails, the command stops.

**Individual commands:**

| Command              | Purpose                         |
|----------------------|---------------------------------|
| `composer lint`      | Fix code style with Pint        |
| `composer lint:test` | Check code style without fixing |
| `composer analyse`   | Run PHPStan static analysis     |
| `composer test`      | Run Pest tests                  |
| `composer check`     | Run all checks in sequence      |

---

## Before Submitting

**For code changes:**
1. Run `composer check` – all checks must pass
2. Confirm documentation alignment
3. Remove debugging and unused imports
4. Verify all classes include required PHPDoc
5. Update documentation if behavior changed
6. **Verify layer boundaries are respected**
7. **Confirm dependencies are injected, not instantiated**
8. **Check that abstractions are justified** – no speculative interfaces or unnecessary indirection
9. **Verify code is testable** – no hidden dependencies or excessive mocking required
10. **Check for N+1 queries** – use eager loading where appropriate

**For documentation-only changes:** `composer check` is not required. Simply ensure the documentation is accurate and links are valid.

---

## Documentation Maintenance

| Code Change         | Documentation Update                                 |
|---------------------|------------------------------------------------------|
| Adding a feature    | Update relevant VitePress docs                       |
| Changing behavior   | Update docs immediately                              |
| Adding a new module | Add documentation to appropriate section             |
| Fixing a bug        | No docs update unless behavior was misdocumented     |
| Deprecating         | Mark as deprecated in docs, add migration notes      |
| Removing            | Remove from docs completely (no "removed" comments)  |

### Documentation Quality

- All code examples must be syntactically correct and runnable
- Cross-references must use relative links
- No duplicate content across files
- Keep documentation in sync with implementation
- For Prism-level features, link to Prism documentation instead of duplicating

---

## Code Reviews

**Code reviews are only performed when explicitly requested by the user.**

### Review Types

| Type         | Focus                             | Trigger                                      |
|--------------|-----------------------------------|----------------------------------------------|
| Performance  | User-visible slowness             | “performance review”, “why is this slow”     |
| Quality      | Maintainability, testability      | “code quality”, “review for maintainability” |
| Bugs         | Crashes, wrong results            | “check for bugs”, “find issues”              |
| Redundancy   | Duplication                       | “find duplication”, “redundancy check”       |
| Architecture | Coupling, boundaries, abstraction | “architecture review”, “check decoupling”    |

### Key Principles

- **Only flag real issues** – No hypotheticals or “could fail if” scenarios
- **Bugs are broken code** – Will crash or produce wrong results, not security/validation
- **Focus on user impact** – If users are not affected, it is Low severity at most
- **Provide actionable feedback** – Include specific files, problems, and fixes
- **Balance is key** – Flag both under-engineering (too coupled) and over-engineering (too abstract)

---

## Enforcement

**All agents and contributors must:**

1. Follow this guide precisely
2. Read relevant module documentation before coding
3. Use documentation as the source of truth
4. Complete all quality checks
5. Update documentation when code changes
6. Request clarification when documentation is incomplete
9. Avoid any direct vendor edits
10. **Respect all layer boundaries without exception**
11. **Use dependency injection for all service dependencies**
12. **Justify every abstraction** – If you cannot explain why an interface or layer exists, remove it
13. **Write testable code** – No hidden dependencies or methods requiring excessive mocking
14. **Avoid N+1 queries** – Use eager loading for relationships accessed in loops

**Tasks violating these rules will be rejected.**
