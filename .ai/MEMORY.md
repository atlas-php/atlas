# Memory - atlas

## Lessons

- The real facade method set lives in `src/Atlas.php` (the `@method static` map) — read it before naming any `Atlas::` call in docs or examples; do not invent fluent methods from memory, they drift from the actual surface.

## Preferences

- An Atlas feature isn't "done" on fakes alone: it needs full unit coverage, a runnable live real-provider script modeled on `sandbox/test-*-provider.php`, and a reported pass count. If it persists data, the lineage/audit trail must be queryable and demonstrably reconstructable.

## Known Traps / Gotchas

- `FakeDriver` skips the base `Driver` constructor, so `Driver::$config` is never set — any base method that reads `$config` will hit uninitialized state. Override such methods in the fake (e.g. `providerName()` is overridden to return `name()`) so base paths like `batch()` stay safe.
- Gemini can emit streamed tool calls one per chunk, so parsers must still handle bundled parallel calls defensively. Raw driver streams can expose finish-reason edge cases that the agent executor loop otherwise hides — test at the driver level, not only through the executor.
