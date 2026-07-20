# Memory — atlas

Always-loaded, read at the start of every task: the friction we've hit in **this codebase** and the
workaround for each — so you don't re-hit it. **A living list — delete an entry once it's genuinely solved;
a long MEMORY means something was solved and never pruned.** This codebase only.

## Friction / gotchas

*One bullet each: the trap, and the workaround. Delete when it's genuinely solved.*

- **`FakeDriver` skips the base `Driver` constructor**, so `Driver::$config` is never set — any base `Driver` method that reads `$config` fails under the fake. Override those methods in `FakeDriver` (e.g. `providerName()` returns `name()`) rather than letting them fall through to the `$config`-reading base.
- **Gemini streams tool calls one-per-chunk and can bundle several parallel calls into one chunk.** Raw driver streams also expose finish-reason edge cases the executor loop hides. Parse defensively (handle bundled parallel calls, don't assume one call per chunk) and test tool-call / finish-reason behavior at the driver level, not only through the executor.

---
*Editing this file? Follow the standard first: [`guides/docs-memory.md`](./guides/docs-memory.md).*
