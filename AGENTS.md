# AGENTS

Rules for every agent working in this repository. These rules are law; where they conflict with your general
habits, this file wins.

This is an **open-source Laravel package — a unified AI SDK (Atlas v3) that owns its own provider layer**.
Everything here ships into strangers' applications: the public API is a contract, every dependency is an
imposition on every consumer, and the documentation is the product's front door. The *what & why* lives in
`.knowledge/BRIEF.md`; the knowledge map is `.knowledge/README.md`. This file defines how you build here.

## Before you work

Load light; pull depth only when the task needs it.

1. **Always read first:** `.knowledge/BRIEF.md` (what & why), `.knowledge/CODEMAP.md` (where things are),
   `.knowledge/MEMORY.md` (current friction). `.knowledge/README.md` maps the rest.
2. **On demand, when the task enters an area:** `.knowledge/prd/` (ratified contracts — source of truth),
   `.knowledge/prd-drafts/` (proposals), `.knowledge/research/` + `.knowledge/references/` (prior art, visual
   targets), `.knowledge/guides/` (how to write each doc + project how-tos).
3. **How work flows:** `research/` -> `prd-drafts/` -> `prd/`; a `prd/` contract never cites a draft. New
   guaranteed behavior is a `prd/` row backed by a test — cite its `R-<AREA>-<n>` in the code. Follow a doc's
   guide before writing or modifying it, and keep docs true in the same task. Run
   `python3 .knowledge/scripts/doc-lint .knowledge` before finishing; scratch -> `.knowledge/tmp/`.
4. Read every file before editing it; search before writing new logic — reuse, extend, refactor.
5. When the user raises a concern, investigate before contradicting — evidence, not a hunch.
6. Read the relevant VitePress documentation before working on any module. **Consumer docs are the source of
   truth — they override all assumptions.**

## Hard gates — require explicit approval

- **Persisted state.** Any change to schema, stored data, or migrations is confirmed first.
- **Dependencies.** Do not add, remove, or major-version-bump a package without approval.
- **Deletions.** Do not delete files outside the task's immediate scope without approval.
- **Commits.** Do not commit or push unless told to.
- **This file.** Never modify `AGENTS.md` without approval; when approved, follow
  `.knowledge/guides/docs-agents.md`. If a rule seems wrong or missing, raise it.

**This is a public open-source package — the gates above carry extra weight, plus:**

- **Public API changes.** Renaming or removing a public method, changing a signature, changing a config key, or
  changing documented behavior is a breaking change for every consumer. Never do it without approval.
- **Dependencies are imposed on every consumer.** A Composer dependency ships into every consuming application —
  the approval gate above is absolute here, major-version bumps included.
- **Shipped migrations.** Consumers have already run the old ones — any change to a shipped migration or
  persisted schema is confirmed before proceeding.

## Never

- Never touch secrets or commit credentials.
- Never leave debug output or commented-out code in completed work.
- Never commit credentials, tokens, or real API keys — not in code, tests, fixtures, docs examples, or sandbox
  files. This repository is public.
- Never leave `dd()`, `dump()`, `ray()`, `var_dump()`, or commented-out code in completed work.
- Never depend on a consuming application. All code is **stateless, framework-aware, and application-agnostic** —
  the package must be fully self-contained.
- Never use `use function` imports.
- Never call a real provider API from an automated test. Unit and Feature tests use the fakes in `src/Testing/`;
  real-API validation happens only in the sandbox.

## Tech stack

- **Package:** Atlas v3 — a unified AI SDK for Laravel. It owns its own provider layer; there is **no external AI
  package dependency**. Modern PHP 8.2+, PSR-12 formatted by Pint, statically analyzed by PHPStan.
- **Testing:** Pest — Feature tests for workflows, Unit tests for services — built on the fakes in `src/Testing/`.
- **Sandbox:** a full Laravel app (`sandbox/`) with real persistence and Horizon, for validation against
  real provider APIs.
- **Consumer docs:** VitePress, published at atlasphp.org.

## Architecture

Atlas owns its own provider layer — no external AI package dependency.

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

### Layer boundaries

Dependencies flow **downward only**. A lower layer never imports from a higher one, and no two services depend
on each other circularly.

**Model services** (`Persistence/Services/`, named `{Model}ModelService`) are the single point of truth for
persistence: create/update/delete, model-specific query helpers, pre-persistence normalization. They never
orchestrate workflows, call other services, call providers, or dispatch events. All Eloquent access in the
package goes through them — no direct queries elsewhere, no business logic in models beyond accessors, mutators,
and scopes.

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

**Domain services** (named by intent: `CreateAgentService`, `ProcessToolCallService`) implement business logic:
orchestrating model services, managing transactions, dispatching events and jobs, calling providers through
contracts. They contain no direct Eloquent queries and no provider implementation details.

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

**Provider clients** (`Providers/{Provider}/`) are low-level external API clients: HTTP calls, authentication,
request/response transformation, retries. They return DTOs or primitives and contain no business decisions, no
database access, and no dependencies on domain services. `OpenAiClient::complete()` returning a
`CompletionResponse` is a provider client; a client that also writes the response to a conversation table is not.

**Support** (`Support/`) holds pure utilities only: no database, no HTTP, no service dependencies, no state, no
side effects. `TokenCounter::count()` computing from its input is Support; a `TokenCounter` that caches results
through a `CacheContract` is not — caching is a side effect and belongs a layer up.

### Contracts & dependency injection

Inject dependencies through the constructor and let Laravel's container resolve them. Type-hint the interface
when one exists.

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

**Earn your abstractions.** Every layer of indirection needs a concrete justification. Create a contract only
when multiple implementations exist or are planned, a test needs a substitution seam, or the dependency crosses
a package boundary — a single-implementation class that no test mocks gets no interface. The same discipline
forbids: speculative generalization for requirements that don't exist, proxy services that pass through to
another service, wrapper classes that add no behavior, and DTOs that mirror an Eloquent model 1:1.

## Naming conventions

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

Handler interfaces (`Providers/Handlers/`) use `*Handler` — they define modality capabilities (what a provider
can do). Resolver contracts (`Providers/Contracts/`) use `*Contract` — they define composition seams (how
provider internals plug together). Both are PHP interfaces; the naming reflects the architectural role.

Methods are short, descriptive, and predictable: booleans prefixed `is`/`has`/`can`, actions named with verbs
(`create`, `execute`, `process`), and every name must match documented terminology.

## Best practices — do / don't

- **Do** `declare(strict_types=1)` in every PHP file, PSR-12 formatted by Pint, modern PHP 8.2+ syntax.
- **Do** give every class a PHPDoc block summarizing its purpose:

```php
✅ /**
    * Class UserWebhookService
    *
    * Handles webhook registration, processing, and retry logic for user-related events.
    */
   class UserWebhookService { /* ... */ }
❌ class UserWebhookService { /* ... */ }   // no doc block — intent left to the reader to reverse-engineer
```

- **Do** raise custom exceptions for expected failures; keep config files in `config/` with sensible defaults.
- **Do** cover new features with full Pest coverage — Feature tests for workflows, Unit tests for services,
  built on `src/Testing/` fakes, **never real APIs**.

### Quality thresholds

- Methods stay under **20–30 lines**; nesting stays under **3 levels** — use early returns and extraction.
- A class with **10+ public methods** splits by responsibility. A class whose tests mock **5+ dependencies** is
  doing too much.
- No hidden dependencies — if a method needs something, the constructor receives it. No global state or
  singletons. No complex logic buried in untestable private methods — extract a class.
- Eager-load relationships accessed in loops; never query inside a loop — batch or pre-fetch. Chunk large
  dataset operations. Cache only what is *measured* slow, never preemptively.
- The same validation or transformation in **3+ places** gets consolidated. Duplication is acceptable when
  isolation or clarity outweighs DRY — but intentional duplication carries a brief comment saying why.
  "Almost identical" code is a bug signal — inspect the difference.

## Directory structure

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

Each top-level `src/` directory is a **domain concern, not a generic pattern**; namespacing follows structure
(`Atlasphp\Atlas\Messages\UserMessage`); cross-domain imports are allowed; and no subdirectory is created until
there are enough files to justify it.

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

Contracts and concerns live with their domain — never in a shared dumping ground — except the genuinely
cross-cutting ones in top-level `src/Concerns/`.

## Build, test & run

```bash
composer check      # the gate: Pint, PHPStan, Pest, doc-lint — must pass before a task is done
composer lint       # Pint (format)
composer lint:test  # Pint --test (format check only)
composer analyse    # PHPStan
composer test       # Pest
composer lint:docs  # python3 .knowledge/scripts/doc-lint .knowledge
```

Use `composer lint` / `lint:test` / `analyse` / `test` for faster iteration, but the full `composer check`
must pass before done. Documentation-only changes are exempt from the code gate but must still pass `lint:docs`.

## Sandbox testing

The sandbox (`sandbox/`) validates package features against real provider APIs and real persistence — see
`sandbox/README.md`. Use it for provider integration, real database behavior, and end-to-end validation.

**Horizon must be running** for queued features to process, and **must be restarted after code changes** to pick
up new code:

```bash
cd sandbox
php artisan horizon    # blocks the terminal; append & to background it
```

If sandbox tests hang or return empty responses, check Horizon first.

## Changelog

`CHANGELOG.md` is consumer-facing: write for someone deciding whether to upgrade, never for someone reading the
diff.

- **One line per change**, leading with the user-visible effect, never the internal mechanism.
- **Section order:** `### Added` → `### Changed` → `### Fixed` → `### Migration`. Omit empty sections — except
  `### Migration`, which is always present and always last. Drop-in releases state: `No breaking changes —
  drop-in upgrade. No consumer action required.` Breaking releases name what changed and the smallest steps a
  consumer must take.
- **Consumer-facing only.** No housekeeping, refactors, test cleanup, or dependency bumps. No class names, file
  paths, or stack traces in `### Fixed`. Mention a config key or signature only when the consumer needs it.
- **Header:** `## [vX.Y.Z](https://github.com/atlas-php/atlas/releases/tag/vX.Y.Z) - YYYY-MM-DD`, with a
  trailing `---` between releases.

## Documentation duties

Keep docs true in the same task that changes reality. Before creating or editing a doc, read its home
`README.md` and follow its `guides/docs-*.md`.

- Moved/restructured files -> update `.knowledge/CODEMAP.md`.
- Hit friction — **anything that cost you a failed attempt**: an env var or flag you had to discover,
  a guard you had to satisfy, a command that only worked the second way you tried it, an error whose
  message didn't say what to do -> **write the line into `.knowledge/MEMORY.md` the moment you find
  the workaround, before you carry on** — by the end of the task it will feel too small to mention,
  which is exactly how the next agent loses the same hour. Delete it once solved.
- Owner ratifies a draft (**the whole file**, not one row) -> `git mv` it into `prd/`; IDs carry over and
  the conformance review then sets glyphs. **Approval moves a draft, not proof** — proof is the glyph
  column. Behavior and its requirement row change in the same commit.
- Scratch -> `.knowledge/tmp/` (git-ignored).
- Keep the runtime `.knowledge/` orientation docs terse and agent-facing — no rationale, maintainer commentary,
  or change history in `BRIEF.md` / `CODEMAP.md` / `MEMORY.md`; they address the next agent doing work, nothing
  else.

**Consumer docs (`docs/`, VitePress, published at atlasphp.org):** a second surface with a different audience,
equally your responsibility, not the user's.

| Code change | Documentation update |
|---|---|
| Adding a feature | Update the relevant docs in the same task |
| Changing behavior | Update docs immediately |
| Adding a module | Add docs to the appropriate section |
| Fixing a bug | None, unless behavior was misdocumented |
| Deprecating | Mark deprecated, add migration notes |
| Removing | Remove from docs completely — no "removed" comments |

- Every code example in the docs must be syntactically correct and runnable.
- Cross-references use relative links. No duplicate content across files — for Prism-level features, link to
  Prism docs instead of restating them.

## Definition of done

A task is done when the change is verified against its stated requirement — never based on effort — and:

1. `composer check` passes (Pint, PHPStan, Pest, doc-lint). Use `composer lint` / `lint:test` / `analyse` /
   `test` for faster iteration, but the full `composer check` must pass. Documentation-only changes are exempt
   from the code gate but must still pass `lint:docs`.
2. Documentation reflects the change, per Documentation duties (both the runtime `.knowledge/` docs and the
   consumer `docs/`).
3. Every rule here held. This file is the checklist — re-read it, do not restate it.
4. New guaranteed behavior is proven by a `.knowledge/prd/` requirement and its test.
5. **Friction you hit is in `.knowledge/MEMORY.md`, not only in your reply** — the next agent reads the file,
   not this conversation. Hit none? Say that in your reply. **Never write "no friction" into the file** —
   `MEMORY.md` records traps, never their absence.

**When creating task lists or plans, the final step is always:** _"Re-read `AGENTS.md` and verify Definition of Done."_
