# How to write AGENTS.md — the standard

This guide **is the standard** for the project's root `AGENTS.md` — the law every agent reads before touching
the repo. Shipped and versioned by `knowledge-template`. `AGENTS.md` is *not* inside `.knowledge/`; it sits at
the repo root and routes agents into it.

`AGENTS.md` does two jobs: **route** agents to the knowledge base, and **encode this stack's rules** so an
agent builds correctly here. Its sections are of two kinds:

- **Ship as written** (identical in every repo): *Before you work*, *Hard gates*, *Documentation duties*, and
  the top of *Never*. **Byte-identical — do not reword a line, and do not extend a bullet with project
  detail.** Their whole value is that you can compare them across repos and see they match; an enriched
  bullet reads better in place and quietly destroys that.
  **You may append project-specific lines below the shipped ones in the same section**, or add a section of
  your own (`## Project-specific hard gates`). What you may never do is edit, reword, reorder or interleave
  a shipped line. The test: every shipped line still present, unmodified, in order.
- **Fill in for this project** (by researching the codebase): *Tech stack*, *Architecture*, *Best practices*,
  *Directory structure*, *Build / test / run*, *Definition of done*.

## How to write it

1. **Research the codebase first — don't guess.** Identify the language/runtime, frameworks, the architecture
   and layering (what each layer may depend on), naming/style with the actual format + lint command, the test
   setup, and the one command that gates a change. Delegate the survey to subagents if your harness supports them.
2. **Keep the ship-as-written sections verbatim** — they're what make every repo behave the same.
3. **A rule that routes an obligation through a mechanism must say what happens without it.** This is the
   defect that testing finds most often, and it fails silently: the obligation doesn't get flagged, it
   simply disappears, because an agent hitting the uncovered case copies whatever the codebase already
   does — and the codebase is usually where the hole came from.

   ```
   ❌ Authorize in the Policy via the Form Request.
      → an action with no input has no Form Request, so it ships with no authorization at all
   ✅ Every state-changing route is authorized — no exceptions. With input, authorize in the Form
      Request via a Policy; without input, call the Policy directly from the controller.
   ```

   Write the critical ones — authorization, money, data loss, privacy — as **"no exceptions" first and
   *how* second.** A rule phrased as a mechanism is only as complete as the mechanism.

4. **If a rule is mechanically checkable, put it in the gate.** A checkable rule left as prose is the
   first one skipped: in testing, the one styling rule stated only in prose was missed by every agent,
   while the rules the gate enforced were followed without exception. Either wire it into the check
   command, or show it inside a `✅`/`❌` example where an agent is already looking — a rule that lives
   only in a bullet list is decoration.

5. **Encode best practices as enforceable rules** — specific and checkable. **Every area whose rule is about
   the shape of code carries a `✅`/`❌` pair**, not just prose: the wrong version beside the right one is the
   single most-followed thing in this file. Vague conventions get ignored.
6. **Set the Definition of done** to the real gate command.
7. **Then list what research could not tell you, and ask.** Reading a codebase surfaces the rules it
   *shows*; three kinds never appear in it, and a file missing them looks finished while omitting the rules
   the owner cares most about:
   - **Rules the code already obeys perfectly.** A ban nobody has ever broken leaves zero trace — search
     for the violation, find nothing, and the rule is invisible.
   - **Rules describing an intended pattern not built yet.** The directory is empty because the convention
     is aspirational, not because it doesn't exist.
   - **Workflow and taste.** Who runs the app, who signs off on UI, what the project refuses on principle.
     These live only with the owner.
   End by naming what you inferred versus what you guessed, and ask about the gaps. **Ask, don't invent.**
8. Keep it lean — rules an agent follows, not prose. Roughly a screen per section; past ~150 lines you are
   explaining rather than ruling, and the file stops being reread.

## The template

Copy this. Fill every `<placeholder>`; keep the ship-as-written sections as they are.

```md
# AGENTS

Rules for every agent working in this repository. These rules are law; where they conflict with your general
habits, this file wins.

This is a **<one line: what this project is and its stack>**. The *what & why* lives in `.knowledge/BRIEF.md`;
the knowledge map is `.knowledge/README.md`. This file defines how you build here.

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

## Hard gates — require explicit approval

- **Persisted state.** Any change to schema, stored data, or migrations is confirmed first.
- **Dependencies.** Do not add, remove, or major-version-bump a package without approval.
- **Deletions.** Do not delete files outside the task's immediate scope without approval.
- **Commits.** Do not commit or push unless told to.
- **This file.** Never modify `AGENTS.md` without approval; when approved, follow
  `.knowledge/guides/docs-agents.md`.

## Never

- Never touch secrets or commit credentials.
- Never leave debug output or commented-out code in completed work.
- <this stack's own hard "never" rules>

## Tech stack

- **<backend / runtime>:** <frameworks, versions, key services>
- **<frontend / UI>:** <frameworks, libraries>

## Architecture — the one rule that matters

<The core structural rule, then the layering as a table: Layer | Owns | May depend on | Must not.>

## Best practices — do / don't

<Enforceable rules grouped by area, each a Do/Don't. Each area that governs code shape shows a fenced
✅/❌ pair — the wrong way directly above or below the right way, in this project's language.>

```<lang>
✅ <the right way, as it would actually be written here>
❌ <the wrong way this rule exists to stop>
```

## Directory structure

<A terse tree of where each kind of thing lives.>

## Build, test & run

<The commands to check and run the project, including the one gate command.>

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

## Definition of done

1. <the project's gate command> passes.
2. Every rule here held.
3. New guaranteed behavior is proven by a `prd/` requirement and its test.
4. **Friction you hit is in `.knowledge/MEMORY.md`, not only in your reply** — the next agent reads the
   file, not this conversation. Hit none? Say that in your reply. **Never write "no friction" into the
   file** — `MEMORY.md` records traps, never their absence.
```

## The repo already has an `AGENTS.md`

The common case, and the one that loses work if you get it wrong. **Reconcile it — never replace it.**

That file is the accumulated judgement of everyone who worked here: a rule in it usually exists because
something once went wrong. Adopting the standard changes the *shape*, not the content.

1. **Read the existing file first and inventory every rule it makes** — before writing a line of the new one.
2. **Re-home each one** under the section of this standard where it belongs. A stack rule goes to *Best
   practices*, a "never do X" to *Never*, an approval requirement to *Hard gates*, a command to
   *Build, test & run*.
3. **Add the ship-as-written sections** it was missing, verbatim.
4. **Nothing is dropped silently.** If a rule looks obsolete, wrong, or contradicted by the standard, **say
   so and ask** — do not quietly leave it out. A rule that disappears in a reformat is indistinguishable
   from one that was never there.
5. **Keep its examples.** An existing `✅`/`❌` pair written against this codebase is worth more than
   anything you would write from scratch.

6. **A rule with no natural home keeps its own section.** The sections in this standard are the ones every
   project needs, not the only ones allowed. If the old file had a *Testing* section stating what each
   suite owns, and nothing here fits it, **add that section back** — dropping a real obligation because the
   template had no slot for it is the worst possible trade.

### Show your work — this is a step, not a courtesy

**Before you finish, write out the inventory and hand it over:** every rule you found in the old file, and
for each one, the section it now lives in — or that you are proposing to drop it, and why.

```
48 rules found · 44 re-homed · 4 proposed for removal (listed below, with reasons) — approve?
```

Without that list, nothing is visibly missing: a lost rule leaves no trace, the new file reads as complete,
and the reviewer has no way to notice that an obligation quietly evaporated. **Tested without it, an agent
kept 32 of 48 rules and reported success** — the losses included an entire testing section binding new
commands to specific suites. Counting them out loud is what makes the omission impossible to miss.

**Then have someone else check it — you are the worst auditor of your own omissions.** With the inventory
step in place the same test kept 43 of 49 rules, a real improvement, and the agent still reported that
nothing had been dropped while four rules were in fact gone and two more had been softened into vagueness.
A rule you never noticed you left out is one you cannot report. Hand the old file and the new one to a
fresh reviewer — another agent will do — and ask for a rule-by-rule verdict before calling it done.

## Changing it later

`AGENTS.md` is under its own hard gate: **never modify it without approval.** That applies to you as much
as to anyone — propose the change and say why.

- **A `MEMORY.md` trap that hardened into a permanent rule graduates here.** When friction stops being
  "work around it" and becomes "never do it", it moves into *Never* and is deleted from `MEMORY.md`. It
  lives in exactly one of the two files.
- **A convention that changed changes here in the same task**, not in a comment or a commit message.
- **Ship-as-written sections are never edited** — not even to improve them. Raise it upstream instead;
  they are what keep every repo behaving the same.

## CLAUDE.md — a router, not a copy

Claude Code reads `CLAUDE.md`. Keep it a thin **router** to `AGENTS.md` — never a second copy of the rules
(which would drift). If the project has none, create it:

```md
# CLAUDE.md

Before you respond to the user, do any task, or any action, you must read and follow
[AGENTS.md](./AGENTS.md) completely.

If you have not read it, your next step must be to read it first, always.
```

## Lint

`doc-lint` checks this file too — it is the entry point that sends an agent into `.knowledge/` at all, so
losing it silently unloads every doc below it. Three checks, deliberately shallow:

- The repo root has an `AGENTS.md`.
- Somewhere in it, all three of `.knowledge/BRIEF.md`, `.knowledge/CODEMAP.md`, and `.knowledge/MEMORY.md`
  are named.
- It names **this guide**, so an agent asked to revise it is routed to the standard first. That lives on
  the existing *Hard gates* line — one clause, not a second block: `AGENTS.md` is read on every task and
  edited almost never, so its own edit rule earns no more room than that.

**Nothing else is inspected** — not sections, not wording, not order. How a project writes its rules is its
own business; the lint only guarantees the orientation trio still gets loaded. (Linting a payload not yet
adopted into a repo? Pass `--payload` to skip both.)

## Rules

- **Ship-as-written stays verbatim** across repos — that's what keeps every project consistent.
- **Rules, not prose.** Every filled-in line is something an agent can follow or a reviewer can check.
- **Show, don't tell** — a `✅`/`❌` example beats a paragraph.
- **One law file.** `AGENTS.md` is the only place the stack's rules live; the `.knowledge/` docs never restate them.
