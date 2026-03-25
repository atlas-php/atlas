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

## Atlas v3 Architecture

Atlas v3 is a unified AI execution layer for Laravel. It owns its own provider layer — no external AI package dependency.

### Layer Model

```
Consumer API (Facade, fluent builders)
         ↓
Executor (tool loop, steps, orchestration events)
         ↓
Driver (routes modality calls to handlers)
         ↓
Handlers + Resolvers (build HTTP payloads, parse responses)
         ↓
HttpClient (sends HTTP, fires transport events)
```

### Key Principles

1. **Own the provider layer** — Atlas talks directly to AI provider APIs
2. **Drivers are thin coordinators** — they route to modality handlers, never build HTTP payloads
3. **Handlers compose resolvers** — MessageFactory, MediaResolver, ToolMapper, ResponseParser
4. **Stateless drivers** — one request → one response; the executor handles loops
5. **Shared HttpClient** — all providers use the same transport with consistent event dispatching

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

```
package-root/
├── docs/                 # VitePress documentation
├── src/
│   ├── Concerns/         # Cross-domain traits
│   ├── Console/          # Artisan commands
│   ├── Embeddings/       # Vector embeddings and similarity search
│   ├── Enums/            # Shared enums (Provider, Role, Modality, FinishReason, etc.)
│   ├── Events/           # Transport and orchestration events
│   ├── Exceptions/       # Exception hierarchy
│   ├── Executor/         # Agent executor, tool loop, steps
│   ├── Facades/          # Atlas facade
│   ├── Input/            # Media input types (Image, Audio, Video, Document)
│   ├── Messages/         # Typed conversation messages
│   ├── Middleware/        # MiddlewareStack and context objects
│   ├── Pending/          # Fluent request builders + Concerns/
│   ├── Persistence/      # Models/, Services/, Middleware/, Enums/, Concerns/
│   ├── Providers/        # Contracts/, Concerns/, Handlers/, Tools/, {Provider}/
│   ├── Queue/            # Queue dispatch infrastructure + Jobs/
│   ├── Requests/         # Immutable request DTOs
│   ├── Responses/        # Response objects and StorableContract
│   ├── Schema/           # JSON schema builder + Fields/
│   ├── Support/          # Pure utilities
│   ├── Testing/          # Fakes for testing
│   ├── Tools/            # Tool infrastructure (definition, serialization)
│   └── Voice/            # Voice session HTTP controllers
├── config/
├── tests/
│   ├── Unit/
│   └── Feature/
└── sandbox/
```

### Directory Rules

1. **Domain-organized** – Each top-level `src/` directory represents a domain concern, not a generic pattern
2. **Namespacing follows structure** – e.g., `Atlasphp\Atlas\Messages\UserMessage`
3. **Cross-domain references are allowed** – Domains may import types from other domains
4. **Domain-local contracts** – Interfaces live with their domain (e.g., `Providers/Contracts/`), not in a shared `Contracts/` directory
5. **Domain-local concerns** – Traits scoped to a single domain live in that domain's `Concerns/` subdirectory; only genuinely cross-cutting traits live in the top-level `Concerns/`
6. **Provider sub-structure** – `Providers/` contains subdirectories for handlers (`Handlers/`), resolver contracts (`Contracts/`), provider tools (`Tools/`), and per-provider implementations (`OpenAi/`, `Anthropic/`, etc.)
7. **No unnecessary nesting** – Don't create subdirectories until there are enough files to justify them

### Adding Files

| Adding...                          | Location                            |
|------------------------------------|-------------------------------------|
| New enum                           | `src/Enums/`                        |
| New message type                   | `src/Messages/`                     |
| New request/response object        | `src/Requests/` or `src/Responses/` |
| New exception                      | `src/Exceptions/`                   |
| New event                          | `src/Events/`                       |
| New handler interface              | `src/Providers/Handlers/`           |
| New resolver contract              | `src/Providers/Contracts/`          |
| Provider-specific implementation   | `src/Providers/{ProviderName}/`     |
| Provider-scoped contract           | `src/Providers/Contracts/`          |
| New tool class                     | `src/Tools/`                        |
| Embedding/vector feature           | `src/Embeddings/`                   |
| Cross-domain trait                 | `src/Concerns/`                     |
| Domain-scoped trait                | `src/{Domain}/Concerns/`            |
| Persistence model/service          | `src/Persistence/Models/` or `src/Persistence/Services/` |
| Queue infrastructure               | `src/Queue/`                        |
| Fluent builder                     | `src/Pending/`                      |
| Test fake                          | `src/Testing/`                      |

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

| Type                | Pattern                   | Example                          |
|---------------------|---------------------------|----------------------------------|
| Providers           | `*ServiceProvider`        | `PackageServiceProvider`         |
| Model Services      | `{Model}ModelService`     | `AgentModelService`              |
| Domain Services     | `{Action}{Domain}Service` | `CreateAgentService`             |
| Contracts           | `*Contract`               | `MediaResolverContract`          |
| Handler interfaces  | `*Handler`                | `TextHandler`, `AudioHandler`    |
| Models              | Singular                  | `Agent`, `Tool`, `Conversation`  |
| Exceptions          | `*Exception`              | `AgentNotFoundException`         |
| DTOs                | `*Data` or `*Dto`         | `CompletionResponseData`         |
| Events              | Past tense                | `AgentCreated`, `ToolExecuted`   |
| Enums (shared)      | Singular noun             | `Role`, `Provider`, `Modality`   |
| Enums (persistence) | Context-prefixed          | `ExecutionStatus`, `MessageRole` |
| Traits (capability) | `Has*`                    | `HasMeta`, `HasAuthor`           |
| Traits (builder)    | `Builds*`                 | `BuildsHeaders`                  |
| Traits (resolver)   | `Resolves*`               | `ResolvesProvider`               |
| Traits (action)     | `{Verb}s*`                | `TracksExecution`, `StoresMedia` |

> **Handler vs Contract interfaces:** Handler interfaces (`src/Providers/Handlers/`) use `*Handler` naming — they define modality capabilities (what a provider can do). Resolver contracts (`src/Providers/Contracts/`) use `*Contract` naming — they define composition seams (how provider internals plug together). Both are PHP interfaces; the naming distinction reflects their architectural role.

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

**Public documentation:** VitePress site at [atlasphp.org](https://atlasphp.org)

**Key directories:** `docs/getting-started/`, `docs/core-concepts/`, `docs/capabilities/`, `docs/guides/`, `docs/api-reference/`

### Maintenance Rules

| Code Change         | Documentation Update                                |
|---------------------|-----------------------------------------------------|
| Adding a feature    | Update relevant VitePress docs                      |
| Changing behavior   | Update docs immediately                             |
| Adding a new module | Add documentation to appropriate section            |
| Fixing a bug        | No docs update unless behavior was misdocumented    |
| Deprecating         | Mark as deprecated in docs, add migration notes     |
| Removing            | Remove from docs completely (no "removed" comments) |

- All code examples must be syntactically correct and runnable
- Cross-references must use relative links
- No duplicate content across files
- For Prism-level features, link to Prism documentation instead of duplicating

---

All agents must follow this document and the referenced guides. Non-compliant contributions will be rejected.