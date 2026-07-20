# How to write a research note — the standard

This guide **is the standard** for every note in [`../research/`](../research/), with the template built in.
Shipped and versioned by `knowledge-template`; the per-project catalog of notes lives in
[`../research/README.md`](../research/README.md).

A research note is **a dated report on how the world works outside this codebase**, written so we can decide
something. It answers one question, cites what it learned from, and says what we're doing about it. It is
**input, never truth**: it informs a draft, never dictates one, and is never the source of record for our
behavior (that's `../prd/`). Answers: *"What has the world already done about `<X>`, and what are we aiming
at?"*

## Report the world, then say what we think — never both at once

`What they do` is **reporting**; Borrow, Avoid, and Verdict are **ours**. Keep them separate: our thinking
inside the reporting section becomes a borrow, then a verdict, then a requirement. The mechanism is
**sourcing at the point of claim** — every non-obvious claim in `What they do` carries a `[n]`, so anything
uncited is visibly ours.

| The urge | Where it goes |
|---|---|
| Say what they should have done | `What to avoid` |
| Say what we'd do about it | `Verdict for us` |
| State our own design position | `Verdict for us` — **never** inside `What they do` |
| Say what the product must do | Nowhere here. Tell the owner. |
| Say what you couldn't find out | `Open questions`, one line |
| Say the world has changed since | Re-read the note whole and re-stamp it |

## The template

```md
# Research: <topic>

**Last updated:** 2026-07-16
**Question it answers:** <one line — what you can decide after reading this>

## What they do

<Reporting only. Every non-obvious claim, and every number, carries [n].>

## What we can borrow

<Ours. The transferable ideas, as short bullets.>

## What to avoid

<Ours. The traps — theirs, and the ones we'd walk into.>

## Verdict for us

<Ours. The decision: what we're doing, deferring, not doing. Name the PRD or component it feeds.>

## Open questions

<One line each. What we didn't learn, and what we haven't decided.>

## Sources

1. <name — url>
2. <name — url>
```

**Do not add a heading.** The schema is closed.

## Section guidance

- **`Question it answers`** — one line, a scope contract. Anything that doesn't serve it is a second note.
- **`What they do`** — the world, as sources describe it. Structure it however the topic wants. Absolute
  rule: **nothing of ours goes here.**
- **`What we can borrow`** — transferable ideas as short bullets. Ideas, not decisions.
- **`What to avoid`** — the traps, theirs and ours.
- **`Verdict for us`** — **the decision, not a recap.** Chooses: this now, that deferred, that not at all,
  and names the PRD or component it feeds.
- **`Open questions`** — one line each. Honest to have many.

## Sourcing

- **Every non-obvious claim carries `[n]`** — every number, date, "the industry does X", legal posture.
- **Keep the real link.** Every `## Sources` entry carries the actual URL you read — one a reader can open
  and verify. A name with no link isn't checkable.
- **Cite the specific page you read, not the vendor.** A source blob at the top is not sourcing.
- **Never cite a source you didn't open.**

## How old is it

**`Last updated` is the date the whole note was last re-read against its sources** — not when the file was
touched. Updating means re-reading, not patching: fix one section and re-stamp and you make stale paragraphs
look fresh. Change a note only when its sources actually moved (or the owner asks) — never to reword, and
never a section the change doesn't touch.

## What a note is not

Nothing in it is binding. The path is always:

```
research  →  the owner reads it  →  the owner states a requirement  →  a prd/ row
```

Never `research → requirement`. Screenshots, competitor captures, and UI targets go in the sibling
[`../references/`](../references/) home — cite them from a note, don't embed the analysis there.

## Lint

Mechanical, in CI (`doc-lint`). **This list is exactly what the linter checks** — every other rule in this
guide is one you hold yourself to.

- The note carries a `**Last updated:**` date.
- Every `[n]` resolves to a Sources entry, and every Sources entry is cited at least once.
- **Every Sources entry carries a link** (`http…`), or is marked `(internal)` when it isn't a public
  page. A bare vendor name is not a source — nobody can check it, including you, later.
- No `##` outside the schema.
- The note has a row in [`../research/README.md`](../research/README.md). The catalog is **maintained by
  hand** — nothing regenerates it; add the row in the same task you add the note.

What it can't check, and you own: that the link goes where you say, that you actually opened it, and that
nothing of ours crept into `What they do`. A URL that resolves is not the same as a source that supports
the claim — the lint buys you a shape, not honesty.
