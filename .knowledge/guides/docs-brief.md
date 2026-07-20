# How to write a BRIEF — the standard

This guide **is the standard** for `../BRIEF.md`, the always-loaded orientation an agent reads first.
Shipped and versioned by `knowledge-template`. **The template is `../BRIEF.md` itself** — start from it and
fill each section by answering its question.

A brief describes **what the project is, who it serves, and what it covers** — how the platform
fits together is [`../OVERVIEW.md`](../OVERVIEW.md)'s job, not this file's — about one screen. Not how the
code is built (that's `CODEMAP.md`), not how to work in it (that's `AGENTS.md`).

**Top rule — never include PII.** This file is committed and world-readable: no real name, username, email,
phone, or address; no secrets or machine paths. Refer to people by role; the product by its public brand or
repo name.

## Where the answers are

**Most of a brief is not in the code.** Scope and external systems are; story, users, and what the project
refuses are not. Work down this list, and notice where it stops:

1. **`README.md`, product docs, a landing page** — the story and the pitch, usually already written.
2. **An existing `AGENTS.md`, or any prior docs** — often carries the *why* and the refusals.
3. **Issues, milestones, a roadmap** — what's in scope now versus later.
4. **The code** — confirms *scope* and *external systems*: what it integrates with, what surfaces exist.
   It is evidence of what was built, never of who it is for or why.

**Then ask.** Users / ICP and the *Out of scope* refusals are owner knowledge; a repo cannot tell you who
someone sells to or what they have decided not to build. **Never infer a market from a schema** — a
plausible invented ICP is worse than a blank one, because it is loaded on every future task and nobody
re-checks it. **Adopting? This is a step, not a courtesy** — list sourced versus inferred and get the inferred
sections confirmed before the adoption is done (`ADOPT.md` step 4).

## Section guidance

- **Story** — *what is this and why does it exist?* One short paragraph a stranger understands: the project
  and the change it creates. A second identity (a rewrite, a fork) goes in a clause.
- **Why it exists** — the problem, who feels it, and why existing options fall short.
- **Users / ICP** — who uses it, what they're trying to accomplish, and the qualities that matter most to
  them. For an internal project, name the internal role and its job.
- **Scope** — the product areas, features, or surfaces the project spans, and what's explicitly out of scope.
- **External Systems** — the databases, services, and third-party systems it relies on, each with what it's
  used for.

## Quality bar

- **Skimmable** — short bullets, one idea per line, about one screen.
- **No placeholders left** — replace every `<...>` and `_(none)_`; delete a section rather than writing "none".
- **Public and PII-free** — people by role; no private data, secrets, or machine paths.
- **Shape is universal** — the same sections fit any project; adjust the words, not the shape.
- **Change only when it changed** — update when the project's story, users, scope, or systems actually
  shifted, not to reword; leave the rest untouched.
