# AGENTS

Rules for every AI coding agent working in this repository. These rules are law; where they conflict with your general habits, this file wins.

This is an **open-source Laravel package**. Everything here ships into strangers' applications: the public API is a contract, every dependency is an imposition on every consumer, and the documentation is the product's front door.

---

## Before You Work

1. Read [`BRIEF.md`](./.ai/BRIEF.md) (scope), [`CODEMAP.md`](./.ai/CODEMAP.md) (structure), and [`MEMORY.md`](./.ai/MEMORY.md) (lessons) before your first edit.
2. Read the relevant VitePress documentation before working on any module. **Documentation is the source of truth — it overrides all assumptions.**
3. Read every file before editing it.
4. Search the codebase before writing new logic. If it exists — reuse, extend, or refactor. Never duplicate.
5. When the user raises a concern, investigate before contradicting. Contradict only with evidence from the codebase.

## Hard Gates — Require Explicit User Approval

- **Public API changes.** Renaming or removing a public method, changing a signature, changing a config key, or changing documented behavior is a breaking change for every consumer. Never do it without approval.
- **Dependencies.** Never add, remove, or major-version-bump a Composer dependency without approval — a package dependency is imposed on every consuming application.
- **Migrations.** Any change to shipped migrations or persisted schema must be confirmed before proceeding — consumers have already run the old ones.
- **Deletions.** Do not delete files or directories outside the immediate scope of the task without approval.
- **This file.** Never modify `AGENTS.md` without approval. If a rule seems wrong or missing, raise it.

## Never

- Never commit credentials, tokens, or real API keys — not in code, tests, fixtures, docs examples, or sandbox files. This repository is public.
- Never leave `dd()`, `dump()`, `ray()`, `var_dump()`, or commented-out code in completed work.
- Never depend on a consuming application. All code is **stateless, framework-aware, and application-agnostic** — the package must be fully self-contained.
- Never use `use function` imports.
- Never call a real provider API from an automated test. Unit and Feature tests use the fakes in `src/Testing/`; real-API validation happens only in the sandbox.

## Documentation Duties

This repository has two documentation surfaces with different audiences. Both are your responsibility, not the user's.

**Runtime docs (`.ai/`, agent-facing):**

- Restructured directories or moved files → update `CODEMAP.md` in the same task.
- Learned something that would have saved you time (a trap, a non-obvious constraint, a tooling quirk) → append it to `MEMORY.md`.
- Do not add rationale, maintainer commentary, or history to these files. They address the next agent doing work, nothing else.

**Consumer docs (`docs/`, VitePress, published at atlasphp.org):**

| Code change | Documentation update |
|---|---|
| Adding a feature | Update the relevant docs in the same task |
| Changing behavior | Update docs immediately |
| Adding a module | Add docs to the appropriate section |
| Fixing a bug | None, unless behavior was misdocumented |
| Deprecating | Mark deprecated, add migration notes |
| Removing | Remove from docs completely — no "removed" comments |

- Every code example in the docs must be syntactically correct and runnable.
- Cross-references use relative links. No duplicate content across files — for Prism-level features, link to Prism docs instead of restating them.

---

## Architecture

Atlas v3 is a unified AI SDK for Laravel. It owns its own provider layer — no external AI package dependency.

**Runtime flow** (a request travels down, never sideways or up):

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

- **Drivers are thin coordinators** — they route to modality handlers and never build HTTP payloads.
- **Handlers compose resolvers** — MessageFactory, MediaResolver, ToolMapper, ResponseParser.
- **Drivers are stateless** — one request → one response; loops belong to the executor.
- **One shared HttpClient** — all providers use the same transport with consistent event dispatching.

## Layer Boundaries

Dependencies flow **downward only**. A lower layer never imports from a higher one, and no two services depend on each other circularly.

**Model services** (`Persistence/Services/`, named `{Model}ModelService`) are the single point of truth for persistence: create/update/delete, model-specific query helpers, pre-persistence normalization. They never orchestrate workflows, call other services, call providers, or dispatch events. All Eloquent access in the package goes through them — no direct queries elsewhere, no business logic in models beyond accessors, mutators, and scopes.

```php
// ❌ The most common violation — orchestration smuggled into the model layer
class AgentModelService
{
    public function createAndNotify(array $data): Agent
    {
        $agent = Agent::create($data);
        $this->notificationService->send($agent);   // belongs in a domain service
        event(new AgentCreated($agent));            // belongs in a domain service
        return $agent;
    }
}
```

**Domain services** (named by intent: `CreateAgentService`, `ProcessToolCallService`) implement business logic: orchestrating model services, managing transactions, dispatching events and jobs, calling providers through contracts. They contain no direct Eloquent queries and no provider implementation details.

```php
// ✅ Orchestration lives here — persistence delegated, dependencies injected
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

**Provider clients** (`Providers/{Provider}/`) are low-level external API clients: HTTP calls, authentication, request/response transformation, retries. They return DTOs or primitives and contain no business decisions, no database access, and no dependencies on domain services. `OpenAiClient::complete()` returning a `CompletionResponse` is a provider client; a client that also writes the response to a conversation table is not.

**Support** (`Support/`) holds pure utilities only: no database, no HTTP, no service dependencies, no state, no side effects. `TokenCounter::count()` computing from its input is Support; a `TokenCounter` that caches results through a `CacheContract` is not — caching is a side effect and belongs a layer up.

## Contracts & Dependency Injection

Inject dependencies through the constructor and let Laravel's container resolve them. Type-hint the interface when one exists.

```php
// ❌ All three forbidden acquisition patterns
class ProcessAgentResponseService
{
    public function execute(): void
    {
        $agentService = new AgentModelService();          // direct instantiation
        $toolExecutor = app(ToolExecutor::class);          // service locator in business code
        AgentModelService::create($data);                  // static service call
    }
}
```

(`app()` is permitted inside service providers only.)

**Earn your abstractions.** Every layer of indirection needs a concrete justification. Create a contract only when multiple implementations exist or are planned, a test needs a substitution seam, or the dependency crosses a package boundary — a single-implementation class that no test mocks gets no interface. The same discipline forbids: speculative generalization for requirements that don't exist, proxy services that pass through to another service, wrapper classes that add no behavior, and DTOs that mirror an Eloquent model 1:1.

---

## Naming Conventions

| Type | Pattern | Example |
|---|---|---|
| Providers | `*ServiceProvider` | `PackageServiceProvider` |
| Model services | `{Model}ModelService` | `AgentModelService` |
| Domain services | `{Action}{Domain}Service` | `CreateAgentService` |
| Contracts | Domain noun | `MediaResolver`, `Storable` |
| Handler interfaces | `*Handler` | `TextHandler`, `AudioHandler` |
| Models | Singular | `Agent`, `Conversation` |
| Exceptions | `*Exception` | `AgentNotFoundException` |
| DTOs | `*Data` or `*Dto` | `CompletionResponseData` |
| Events | Past tense | `AgentCreated`, `ToolExecuted` |
| Enums (shared) | Singular noun | `Role`, `Provider`, `Modality` |
| Enums (persistence) | Context-prefixed | `ExecutionStatus`, `MessageRole` |
| Traits (capability) | `Has*` | `HasMeta`, `HasOwner` |
| Traits (builder) | `Builds*` | `BuildsHeaders` |
| Traits (resolver) | `Resolves*` | `ResolvesProvider` |
| Traits (action) | `{Verb}s*` | `TracksExecution`, `StoresMedia` |

Handler interfaces (`Providers/Handlers/`) use `*Handler` — they define modality capabilities (what a provider can do). Resolver contracts (`Providers/Contracts/`) use `*Contract` — they define composition seams (how provider internals plug together). Both are PHP interfaces; the naming reflects the architectural role.

Methods are short, descriptive, and predictable: booleans prefixed `is`/`has`/`can`, actions named with verbs (`create`, `execute`, `process`), and every name must match documented terminology.

## Package Structure

```
package-root/
├── docs/                 # VitePress documentation
├── src/
│   ├── Concerns/
│   ├── Console/
│   ├── Embeddings/
│   ├── Enums/
│   ├── Events/
│   ├── Exceptions/
│   ├── Executor/
│   ├── Facades/
│   ├── Input/
│   ├── Messages/
│   ├── Middleware/
│   ├── Pending/
│   ├── Persistence/      # Models/, Services/, Middleware/, Enums/, Concerns/
│   ├── Providers/        # Contracts/, Concerns/, Handlers/, Tools/, {Provider}/
│   ├── Queue/
│   ├── Requests/
│   ├── Responses/
│   ├── Schema/
│   ├── Support/
│   ├── Testing/
│   ├── Tools/
│   └── Voice/
├── config/
├── tests/                # Unit/, Feature/
└── sandbox/
```

Each top-level `src/` directory is a **domain concern, not a generic pattern**; namespacing follows structure (`Atlasphp\Atlas\Messages\UserMessage`); cross-domain imports are allowed; and no subdirectory is created until there are enough files to justify it.

Where a new file goes:

| Adding... | Location |
|---|---|
| Enum | `src/Enums/` (shared) or `src/Persistence/Enums/` |
| Message type | `src/Messages/` |
| Request / response object | `src/Requests/` / `src/Responses/` |
| Exception | `src/Exceptions/` |
| Event | `src/Events/` |
| Handler interface | `src/Providers/Handlers/` |
| Resolver or provider-scoped contract | `src/Providers/Contracts/` |
| Provider-specific implementation | `src/Providers/{ProviderName}/` |
| Tool class | `src/Tools/` |
| Embedding / vector feature | `src/Embeddings/` |
| Cross-domain trait | `src/Concerns/` |
| Domain-scoped trait / contract | `src/{Domain}/Concerns/` / `src/{Domain}/Contracts/` |
| Persistence model / service | `src/Persistence/Models/` / `src/Persistence/Services/` |
| Queue infrastructure | `src/Queue/` |
| Fluent builder | `src/Pending/` |
| Test fake | `src/Testing/` |

Contracts and concerns live with their domain — never in a shared dumping ground — except the genuinely cross-cutting ones in top-level `src/Concerns/`.

---

## Code Rules

- `declare(strict_types=1)` in every PHP file. PSR-12, formatted by Pint. Modern PHP 8.2+ syntax.
- Every class carries a PHPDoc block summarizing its purpose:

```php
/**
 * Class UserWebhookService
 *
 * Handles webhook registration, processing, and retry logic for user-related events.
 */
```

- Custom exceptions for expected failures. Config files live in `config/` with sensible defaults.
- New features require full Pest coverage: Feature tests for workflows, Unit tests for services — built on `src/Testing/` fakes, never real APIs.

### Quality thresholds

- Methods stay under **20–30 lines**; nesting stays under **3 levels** — use early returns and extraction.
- A class with **10+ public methods** splits by responsibility. A class whose tests mock **5+ dependencies** is doing too much.
- No hidden dependencies — if a method needs something, the constructor receives it. No global state or singletons. No complex logic buried in untestable private methods — extract a class.
- Eager-load relationships accessed in loops; never query inside a loop — batch or pre-fetch. Chunk large dataset operations. Cache only what is *measured* slow, never preemptively.
- The same validation or transformation in **3+ places** gets consolidated. Duplication is acceptable when isolation or clarity outweighs DRY — but intentional duplication carries a brief comment saying why. "Almost identical" code is a bug signal — inspect the difference.

---

## Sandbox Testing

The sandbox (`sandbox/`) validates package features against real provider APIs and real persistence — see `sandbox/README.md`. Use it for provider integration, real database behavior, and end-to-end validation.

**Horizon must be running** for queued features to process, and **must be restarted after code changes** to pick up new code:

```bash
cd sandbox
php artisan horizon    # blocks the terminal; append & to background it
```

If sandbox tests hang or return empty responses, check Horizon first.

---

## Changelog

`CHANGELOG.md` is consumer-facing: write for someone deciding whether to upgrade, never for someone reading the diff.

- **One line per change**, leading with the user-visible effect, never the internal mechanism.
- **Section order:** `### Added` → `### Changed` → `### Fixed` → `### Migration`. Omit empty sections — except `### Migration`, which is always present and always last. Drop-in releases state: `No breaking changes — drop-in upgrade. No consumer action required.` Breaking releases name what changed and the smallest steps a consumer must take.
- **Consumer-facing only.** No housekeeping, refactors, test cleanup, or dependency bumps. No class names, file paths, or stack traces in `### Fixed`. Mention a config key or signature only when the consumer needs it.
- **Header:** `## [vX.Y.Z](https://github.com/atlas-php/atlas/releases/tag/vX.Y.Z) - YYYY-MM-DD`, with a trailing `---` between releases.

---

## Definition of Done

A task is done when the change is verified against its stated requirement — never based on effort — and:

1. `composer check` passes: Pint, PHPStan, Pest. Use `composer lint` / `lint:test` / `analyse` / `test` for faster iteration — but the full `composer check` must pass before done. Documentation-only changes are exempt.
2. Documentation reflects the change, per Documentation Duties.
3. Every rule in this file was upheld. This file is the checklist — re-read it, do not restate it.

**When creating task lists or plans, the final step is always:** _"Re-read `AGENTS.md` and verify Definition of Done."_
