# How to write a PRD — the standard

This guide **is the standard** for every PRD in [`../prd/`](../prd/), with the template built in. It is
shipped and versioned by `knowledge-template` — do not rewrite it per project. The per-project **catalog**
(declared components + file list) lives in [`../prd/README.md`](../prd/README.md), not here.

`../prd/` holds the **ratified contracts** for what the product does: one file per built system, every
requirement carrying an ID and a status — ✅ where a test proves it, ❌ where nothing does yet. A PRD
asserts *what must be true, why, and whether it is* — not how it's built. If a claim there has a ✅, a test
proves it.

## The catalog is the product map

A PRD answers for one system. **[`../prd/README.md`](../prd/README.md) answers for the product**: the
components in order, then every contract under its component with **a one-line gloss**. Someone new — human
or agent — reads that page top to bottom and knows what the product is made of before opening a single
file.

The drafts have their own catalog, [`../prd-drafts/README.md`](../prd-drafts/README.md), in the same shape.
**Read together, the two catalogs are the whole system on one page** — what is ratified, and what is
proposed — which is why both are maintained by hand and both are linted for completeness.

**A catalog holds what is durable: what exists, and what each one is.** Never how many rows are green.
That number moves with every test run, so it lives in the glyph column of the contract that owns it and
nowhere else — a count in a summary is stale the day after it is written, and unlike a stale row nobody
re-reads a summary to catch it. Give every Contents row its gloss; a bare filename list makes the source of
truth read worse than the proposals beside it.

## A PRD asserts, it never explains

If the file grows while the row count doesn't, you're writing prose instead of requirements. Every fact
has one home; the urge to explain has one, and it is not the PRD:

| The urge | Where it goes |
|---|---|
| Explain how it's built | The design doc |
| Justify why it's built that way | The decision log |
| Say it isn't built, or isn't proven | The ❌ already says it |
| A requirement belongs in another file | **Move it** — a stop-and-ask, not a row |
| Undecided, so the requirement can't be written | `## Open questions`, one line |
| Something deliberately deferred | A `../prd-drafts/` stub — `id`, `name`, one sentence |
| Record what tests don't prove | A ❌ on the row it doesn't prove |
| Restate a rule another PRD owns | Cite its ID |
| Record a value or tunable | The header owns it — cite the symbol |

If none of those fit, you're about to write something with no home. **Raise it, don't write it.**

## Where a requirement lives

Each project declares its own components (layers) in [`../prd/README.md`](../prd/README.md), **in order** —
domain ontology, not architecture. Two universal rules:

1. **Layers are ordered; reading goes one way.** A lower layer may cite an upper one, never the reverse.
2. **Shared behavior goes up, never sideways.** If two entities obey the same rule, it belongs to their
   base or the layer above — one ID, cited by both, never written twice.

### Placement

Read the ladder against **the components this project declared** — the shipped default is
`base-` / `entity-` / `flow-`, but a project may name its own, and the questions apply to whatever it
listed. Ask in order, **stop at the first yes**:

1. True regardless of which kind of thing is involved? → it's about the substrate → the **lowest** layer.
2. True of exactly one kind of thing? → that kind's file, one layer up.
3. Needs two or more kinds interacting before it means anything? → the **emergent** layer above them.
4. About what appears on screen? → **not a PRD requirement.** Presentation.

**A requirement never spans two layers.** If it seems to, it's two — split it. If it doesn't fit one layer
cleanly, **stop and ask.**

## Naming

### Files

**One flat directory. The filename prefix is the layer** — never also a frontmatter field or a subdirectory.
Lowercase, hyphenated, one file per node.

```
prd/
  base-<substrate>.md
  entity-<thing>.md        entity-<other-thing>.md
  flow-<emergent-behavior>.md
```

Entities are singular nouns. Flow files are noun phrases for the emergent thing — never a verb, question,
or computation (`<thing>-access` is a question the graph answers, not a thing). Every file matches a listed
component's prefix or the lint fails. No subdirectories; the only non-PRD file is `README.md`.

### Namespaces and IDs

- Form: `R-<NS>-<n>`. `<NS>` is declared once, in the file's frontmatter `id:`.
- **One namespace per file. One file per namespace.** A second namespace means a second file — as does a
  table past ~15 rows.
- `<n>` starts at one and runs unbroken. **A namespace's numbers never have a hole** — `doc-lint` fails on
  a gap. A contract holds what is true now, so a withdrawn requirement is erased outright rather than kept
  as struck history, and the rows below it renumber to close the gap.
- **Renumbering rewrites meaning, so it is never done alone.** `R-THING-7` erased means the old
  `R-THING-8` becomes `R-THING-7` — and every citation of either now points somewhere new. Nothing will
  tell you: the ID still resolves, the lint still passes, and the reader gets a confident wrong answer.
  **Erasing a row is one change that also updates every citation of every ID at or after it** — in the
  other contracts, and in the code comments that name their `R-<NS>-<n>`. Grep the whole repo for the
  namespace before you erase, and if a citation is somewhere you cannot edit in the same change, **stop and
  ask.**
- **Never compound.** `R-A-2 / R-B-2` is illegal — hoist it to a shared file, cited by both.

## The template

Copy this into every PRD. These headings, this order, every time.

```md
---
id: THING
name: Thing
last_verified: 2026-07-16
---

## What this is

<At most three sentences, in domain terms. What the thing is, not how it works.>

## Why it exists

<At most three bullets. What is true for the user when this works.>

- <outcome the user gets>
- <another outcome>

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-THING-1 | <the behavior that must hold, under 25 words> | `<test case name>` |
| ❌ | R-THING-2 | <built but unproven behavior> | src/thing.ext:88 — no test |
| ❌ | R-THING-3 | <nothing exists yet> | — |

## Open questions

<One line each. Design decisions not yet made, so the requirement can't be written.>

- <an unresolved decision>
```

**The glyph is the first column and its header stays blank.** The schema is closed — the only `##` headings
are `What this is`, `Why it exists`, `Requirements`, `Open questions`. Changing it needs approval.

An idea enters as a `../prd-drafts/` stub — two frontmatter lines and a sentence. **You do not write a PRD
to have an idea.**

## Section guidance

- **`What this is`** — what the thing is to someone who doesn't know the system, in the product's vocabulary.
  Not what it owns or depends on. Can't say it in three sentences → it's two files.
- **`Why it exists`** — goals as **outcomes**: what is true for the user when this works. Every requirement
  should serve one.
- **`Requirements`** — the document. Everything else is scaffolding.
- **`last_verified`** — the date **the whole file** was last read row-by-row against its tests. CI runs and
  edits don't move it. Absent until the first review — that's what makes a file a draft.
- **`Open questions`** — design decisions not yet made, one line each. Not build gaps (a requirement that
  exists and isn't proven is a ❌ row).

## Writing a requirement

Requirements come from the owner. **You are transcribing, not authoring** — the rewrite may tighten the
sentence and nothing else.

- **Never add.** If the owner didn't say it, it isn't a requirement.
- **Never generalize.** "No recipients" is not "an invalid recipient set."
- **Never hedge.** "Cannot" does not become "should generally not."
- **Never turn behavior into mechanism** — *how* is the design doc's.
- **A gap is an open question, not a guess.**
- **Contradicts something already written? Stop and ask.** Never reconcile two of the owner's claims.

### Splitting

**Split where it would be built and tested separately.** *"A valid token is required; a missing token is
rejected as unauthorized; an out-of-scope token is refused as forbidden"* is three rows. (Note the status
*names*, not codes — a numeric literal in requirement text fails the lint.) Inverse: **one assertion proven
by several tests is still one requirement** — empty list, all-invalid, filtered-to-zero are situations, not
claims.

### Every row stands alone

**A requirement is read where it is cited, not where it sits.** Another contract cites `R-THING-4`; a
comment in the code names it; a reviewer opens the file at one line. In none of those does the row above it
come along. So a row that borrows its subject or its verb from its neighbour is unreadable exactly where it
matters most.

This is what the word cap breaks first. An author trims to fit, the trimmed words are the ones the row
above already said, and the table still scans fine top-to-bottom — which is why nobody catches it.

```
✅ A seller-only participant cannot create a bid policy.
❌ Seller-only participants cannot.
❌ Illegal jumps are rejected.
❌ After the window it is allowed.
```

**The cap never buys ambiguity.** If the standalone sentence doesn't fit in 25 words, that is the format
telling you the row is two requirements — split it. Trimming the subject is not the way out. **But the cap
is only one of the two signals, and the weaker one:** a row can stand alone, fit in fifteen words, and
still assert three things joined by "and". That is the *one assertion* rule under [Form](#form), and it
catches what the cap never will.

**A term the file itself defines is not a borrowed subject.** If a row enumerates the values or names the
thing, later rows may use that name bare — *"Exclusive enforces a single sale"* is complete, because
`Exclusive` is a value this file established. What must never be borrowed is a **pronoun**, a **bare
adjective standing in for its noun**, or an **elided verb**. Expanding every defined term back to its full
phrase is how a table stops being scannable, and the rule is not asking for that.

Read every row cold, on its own, before you ship the file. Any row that raises *"which one?"*, *"cannot
what?"*, or *"what is allowed?"* is not finished.

**Recovering a row that was already written this way.** The Evidence column is the anchor: open the test it
names, and it tells you the true subject and verb. Where there is no test — a `❌` row with `—` — you may
restore **only what an adjacent row states literally**, and nothing more. If the meaning needs a decision
rather than a lookup, that is authoring: leave the row alone and raise it. Recovering from a neighbour is
allowed here precisely because you are removing the dependency, not creating one.

### Form

- **No implementation symbols** — not a type, function, class, or file.
- **No infrastructure, and no vendor.** Not a database, connection, table, column, queue, cache, endpoint,
  wire constant, or the name of a package you installed. These slip past the rule above because they are
  none of those things, and they are how a contract quietly turns into a schema description.

  ```
  ✅ A trading organization has exactly one workspace.
  ❌ Every participant is paired one-to-one with a Tenant sharing its UUID.
  ❌ Marketplace tables stay on the central connection.
  ```

  **The test: would this row change if the storage, the framework, or the vendor were swapped out
  tomorrow?** If yes, it is a design decision wearing a contract's clothes — the behavior it protects is the
  requirement, and the mechanism belongs in the design doc. If the answer is that the row would become
  *meaningless* rather than merely reworded, you have found a requirement with no product behind it: raise
  it, don't write it.

  **When the interface *is* the deliverable, its vocabulary is product vocabulary.** For a system whose
  product is an integration, the thing a customer buys is the endpoint, the field, the error they must
  handle — that is the domain, and stripping it leaves nothing. The swap test still sorts it: the endpoint
  survives a change of framework, so it stays; the auth scheme, the serialization format and the status
  code do not, so they go.

- **This binds the file's `name:` and its catalog gloss too**, not only the rows. A contract whose every
  requirement is clean but which is *titled* after a wire constant is still named for its mechanism, and
  the title is what a reader meets first.
- **Tunables by name, never by value** — and only where the name is already the owner's word.
- **One assertion.** "and", "also", or "except"? Suspect two. **A joining semicolon is rejected by the
  lint** — it is the clearest sign a row is two requirements compressed to fit the word cap. Split it.
- **Under 25 words.** If it won't fit, it isn't atomic.
- **No numeric literal in the text** — name the tunable instead.
- Present tense, declarative, observable.
- **Cite IDs, never documents.** `R-OTHER-2` is an edge the lint sorts; "see the other doc" is invisible.

**Evidence is the one place technical detail is allowed** — test names, `file:line`, paths. It answers
*where was this proven*. The row above it stays the owner's.

**Cite the ID from the code, too.** The function or module that implements a requirement names its
`R-<NS>-<n>` in a comment or doc-block. Evidence points from the contract to the test; that comment points
from the code back to the contract — so a reader who lands anywhere in the loop can walk the other two.

## Tests and status

Every row answers **one** question: *does an automated test prove this?* ✅ or ❌. No third answer.

- **❌ means failed, blocked, skipped, or not run.** Code that exists but nothing tests is ❌.
- **Evidence says which kind of ❌.** Test name → ✅. `file:line` + `— no test` → built, unproven. `—` →
  nothing exists. **Evidence is empty iff nothing exists.**
- **Never write ✅ without naming the test that proves it.** Can't find one? The row is ❌.
- **A compile is not a test.**
- **Evidence names the test, never describes it** — a name a linter can assert exists.
- A **removed** requirement is deleted from the table, and the rows below it renumber to close the gap. A
  contract states what must be true now; a withdrawn claim kept as struck history is stale information in
  the one file that must be readable cold. The decision log is where a reversal is remembered — **erasing
  the row is half the change, and updating every citation of it is the other half.**
- Untestable presentation requirements carry `Evidence: signoff:<date>`.

## Drafts and graduation

Proposals live isolated in [`../prd-drafts/`](../prd-drafts/) until approved — see its README.

- **A ratified PRD may never cite a draft.** Every ID a `prd/` file cites resolves inside `prd/`. The lint
  enforces it.
- **Ratification is per file, not per row.** A draft graduates when the owner ratifies **the file's
  claims — all of them**. Ratifying one new claim inside a draft adds a row *to the draft*; it does not
  move the file. Graduating carries every other row into the source of truth on the owner's authority, so
  if you can't tell whether they meant the file or the row, **stop and ask.**
- **The line between the two homes is approval, not proof.** A draft becomes a contract when the owner
  ratifies its claims — not when its tests go green. **Proof is the glyph column**, and a ratified contract
  is expected to carry ❌ rows: that is what `file:line — no test` exists to say. Waiting for an all-green
  file leaves the source of truth empty and the real knowledge stranded in a home contracts may not cite.
- **Graduation is a move, not a rewrite.** `git mv ../prd-drafts/<file>.md ./<file>.md`; the IDs carry
  across unchanged. The first conformance review then sets glyphs and stamps `last_verified`.

## Refining

- **Change only what the contract changed.** Edit a row when the owner's claim changed or the code drifted
  — never to reword, and never a row the change doesn't touch.
- **The only writable surfaces are a table row, the three sentences, and the three bullets.** Never add prose.
- New behavior → **new row, new ID, appended.** Changed → **edit in place.** Removed → **erase the row,
  renumber to close the gap, and update every citation in the same change.**
- Never restate a requirement that has an ID elsewhere — cite it. Never add a `##` heading.

### Stop and ask

Do not proceed if the requirement doesn't fit one layer; needs a new namespace, file, or component; would
move a requirement between files; would make this file cite one it never cited; would create a cycle;
would graduate a draft the owner ratified only one row of; would erase a requirement whose ID is cited
somewhere you cannot update in the same change; or contradicts an existing requirement.

## Lint

Mechanical, in CI (`doc-lint`). A red lint is a broken PRD, not a style note. **This list is exactly what
the linter checks** — every other rule in this guide is one you hold yourself to.

- Every filename matches a component prefix listed in `../prd/README.md`. `prd/` and `prd-drafts/` are
  **flat** — a subdirectory is rejected, because files inside one would escape every check here.
- One namespace per file; one file per namespace. Every ID matches its file's declared namespace, and the
  namespace in `id:` is a single token.
- Every cited ID resolves; no ID is defined in two files.
- **A namespace's numbers run unbroken from one** — a gap means a row was erased without renumbering.
- **No `prd/` file cites a `prd-drafts/` id.**
- Every ✅ has something in Evidence.
- `last_verified` present iff the file has at least one ✅ row.
- **Citations only go up the stack**, and the citation graph is a DAG — a cycle *within* one layer fails too.
- No `##` outside the schema. No requirement over 25 words. No numeric literal in requirement text.
- **No semicolon in requirement text** — that's two requirements in one row.
- **The catalog is well-formed** — `../prd/README.md` has a Components list (`prefix — gloss`) and a
  Contents list naming every PRD. Catalogs are **maintained by hand**; nothing regenerates them. Add the
  row in the same task you add the file, or the build goes red.

### What the lint cannot check — so you must

The shipped linter is stack-neutral: it has no idea what your test runner is. So **a ✅ is only ever checked
for *having* Evidence — never for that test existing.** A plausible-looking name that matches nothing in the
suite passes forever, and the one claim this whole format rests on quietly becomes untrue.

If ✅ is going to mean anything here, close that gap yourself: pull the backticked Evidence names out of
`../prd/*.md`, list your suite's test names, and fail on any Evidence name the suite doesn't contain. Wire it
into the same gate as `doc-lint` — it is a handful of lines, and it is the difference between a contract and
a claim.

Two more the linter can't see, and therefore owns nothing of: that Evidence is empty **only** where nothing
exists (a `—` on code that does exist is a lie the lint will never catch), and that a requirement is the
owner's claim rather than one you generalized.
