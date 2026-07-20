# How to write MEMORY — the standard

This guide **is the standard** for `../MEMORY.md`, the always-loaded list of friction to avoid. Shipped and
versioned by `knowledge-template`. **The template is `../MEMORY.md` itself** — one bullet per trap.

`MEMORY.md` holds **the friction we've hit in this codebase and the workaround for each** — traps, gotchas,
non-obvious constraints an agent would pay to have known up front.

**Write it at the moment of pain, not at the end of the task.** A workaround stays obvious for about five minutes; after that it stops feeling worth writing down, and you finish the task genuinely believing you hit nothing. Every entry that never got written was lost exactly that way.

**The test for "is this friction?" is simple: did it cost you an attempt?** If you ran a command and it
failed, and the fix was knowledge rather than a code change — an env var, a flag, an ordering, a guard to
satisfy, a second run — that is friction, and the next agent will lose the same attempt unless you write it
down. Do not wait for something dramatic: the ordinary case is a one-line workaround you found in ninety
seconds and would otherwise forget by the end of the task.

**Top rule — never include PII.** MEMORY is committed and world-readable: no real names, emails, secrets,
tokens, or machine paths in a trap — describe the failing thing generically, and refer to people by role.

## A living list, not an archive

- **When friction is genuinely solved — fixed in code, or made impossible by a guard or test — delete its
  entry.** This is the one doc you *shrink* as the project matures.
- If a trap hardens into a permanent "never do X" rule, it graduates to `AGENTS.md` — move it, delete it here.
- Rely on an entry and find it no longer true? Fix or delete it in passing.

## Scope: this codebase only

Not user preferences, not how someone likes to work, not cross-project notes — those live in the agent's own
memory. `MEMORY.md` is about *this repo's* traps and nothing else.

## Form

- **One bullet each:** the trap, and the workaround or what to do instead. State the *why* in a clause.
- **Concrete, not vague.** Not "the X build is flaky" but "run `X` twice — the first run races the codegen
  and fails intermittently".
- **Not a changelog.** Only what's *still* true and still bites.
- **Never record the absence of friction.** No "none hit this task", no dated all-clear. A task that
  hit nothing leaves this file untouched; say so in your reply instead.
- **Only touch an entry to add, delete, or correct it.** Never reword an entry for its own sake.
