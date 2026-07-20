#!/usr/bin/env python3
"""Teeth-test for doc-lint — ships in the payload beside the linter.

The valid base is a *copy of the real payload* with two sample PRDs added, so the
structural checks (every shipped dir/guide present, the trio's standard pointer) are
exercised against the actual shipped tree. Each mutation must fail on its own rule.

    python3 .knowledge/scripts/test_doc_lint.py
"""
import os
import shutil
import subprocess
import sys
import tempfile

HERE = os.path.dirname(os.path.abspath(__file__))
DOC_LINT = os.path.join(HERE, "doc-lint")
PAYLOAD = os.path.dirname(HERE)  # the real .knowledge/ this script ships in

CATALOG = """# prd/ — catalog

To write or modify one, follow [../guides/docs-prd.md](../guides/docs-prd.md).

## Components

```
1. base- — the substrate
2. entity- — a placed thing
```

## Contents

- **Base**
  - [base-core](./base-core.md)
- **Entities**
  - [entity-widget](./entity-widget.md)
"""
BASE = """---
id: CORE
name: Core
last_verified: 2026-07-16
---

## What this is

The substrate.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-CORE-1 | The substrate persists between runs | `coreHolds` |
"""
WIDGET = """---
id: WIDGET
name: Widget
---

## What this is

A widget, built on R-CORE-1.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ❌ | R-WIDGET-1 | A widget reports its state on demand | — |
"""


# Minimal catalogs for the homes we clear — a real project's catalogs link to the files we just
# deleted, so they must be reset too or the fixture fails on its own dangling links.
# The fixture is a *populated* project, not an empty one: a draft, a research note, a reference set and
# a project how-to. Rules that only bite on a populated home stayed untested for a long time — both bugs
# found by piloting on a real repo were of exactly that shape.
DRAFT = """---
id: FUTURE
name: Future thing
---

## What this is

Something proposed, not yet ratified.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| \u274c | R-FUTURE-1 | A future thing is proposed | \u2014 |
"""
NOTE = """# Research: how others do it

**Last updated:** 2026-07-16
**Question it answers:** what shape does the rest of the world use

## What they do

They do a thing [1], and we have seen it ourselves [2].

## Verdict for us

Borrow the shape.

## Sources

1. A public page — https://example.com/a-page
2. Our own walkthrough (internal)
"""
HOWTO = """# How to do the recurring thing

The steps, in order, with the commands.

1. Run the first command.
2. Check it worked.
"""
STUB_CATALOGS = {
    "prd-drafts/README.md": """# prd-drafts/ — proposals (catalog)

Isolated until approved. To write one, follow [../guides/docs-prd.md](../guides/docs-prd.md).

## Drafts

| Draft | Proposes | Reserved namespace |
|---|---|---|
| [entity-future](./entity-future.md) | a future thing | FUTURE |
""",
    "research/README.md": """# research/ — prior art (catalog)

To write one, follow [../guides/docs-research.md](../guides/docs-research.md).

## Notes

| Last updated | Note | Question it answers |
|---|---|---|
| 2026-07-16 | [how-others-do-it](./how-others-do-it.md) | what shape the world uses |
""",
    "references/README.md": """# references/ — visual targets (catalog)

## Sets

| Set | Competitor / platform | What we're comparing |
|---|---|---|
| [acme](./acme/) | Acme | their onboarding |

## How to add one

- One folder per source.
""",
}

BRIEF = """# Brief — Sample Project

## Story

A sample project, filled in as an adopted repo would be.

---
*Editing this file? Follow the standard first: [`guides/docs-brief.md`](./guides/docs-brief.md).*
"""
CODEMAP = """# Codemap — Sample Project

## Entry Points

- `src/main.ext` — where it starts.

---
*Editing this file? Follow the standard first: [`guides/docs-codemap.md`](./guides/docs-codemap.md).*
"""

FRAMING = """*This describes the platform as designed. What is proven today is recorded row by row in the
contracts — [`prd/`](./prd/) for the ratified ones, [`prd-drafts/`](./prd-drafts/) for those in proposal.*
"""
WHAT_THIS_IS = """## What this is

A sample project for sample customers, sold by the seat.
"""
OVERVIEW = """# Overview — Sample Project

""" + FRAMING + """
""" + WHAT_THIS_IS + """
## The platform

```mermaid
flowchart LR
  a["Core"] --> b["Widget"]
```

## How it works

- **Core** — the substrate everything stands on. [contract](./prd/base-core.md)
- **Widget** — a placed thing, reports its state. [contract](./prd/entity-widget.md)

## What you use

- **An operator** — one workspace, where widgets are placed and watched.

## What governs it

- **What a widget may report** — the owner sets it. See [`prd/entity-widget.md`](./prd/entity-widget.md).

---
*Editing this file? Follow the standard first: [`guides/docs-overview.md`](./guides/docs-overview.md).*
"""

AGENTS = """# AGENTS

Always read first: `.knowledge/BRIEF.md`, `.knowledge/CODEMAP.md`, `.knowledge/MEMORY.md`.

Never modify this file without approval; when approved, follow `.knowledge/guides/docs-agents.md`.
"""


def w(aidir, rel, content):
    path = os.path.join(aidir, rel)
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, "w", encoding="utf-8") as fh:
        fh.write(content)


def w_repo(aidir, rel, content):
    """Write beside .knowledge/, at the repo root — where AGENTS.md lives."""
    w(os.path.dirname(aidir), rel, content)


def build_base(aidir):
    shutil.copytree(PAYLOAD, aidir)          # the real shipped payload — structure exactly as shipped
    # This script ships *inside* a project's .knowledge/, so the payload above may already hold that
    # project's real PRDs, notes, and reference sets. Clear the content homes: the fixture below is the
    # whole of the valid base, and the test stays deterministic no matter what the project has written.
    for home in ("prd", "prd-drafts", "research", "references"):
        d = os.path.join(aidir, home)
        for name in os.listdir(d):
            if name == "README.md":
                continue
            path = os.path.join(d, name)
            shutil.rmtree(path) if os.path.isdir(path) else os.remove(path)
    for rel, body in STUB_CATALOGS.items():  # ...and reset their catalogs, which linked to those files
        w(aidir, rel, body)
    mp = os.path.join(aidir, "MAP.md")       # generated from content we just replaced — stale by construction
    if os.path.exists(mp):
        os.remove(mp)
    w(aidir, "prd-drafts/entity-future.md", DRAFT)      # a populated project, not an empty one
    w(aidir, "research/how-others-do-it.md", NOTE)
    w(aidir, "references/acme/NOTES.md", "What inspires: the layout. Provenance: public marketing page.\n")
    w(aidir, "guides/do-the-thing.md", HOWTO)
    w(aidir, "BRIEF.md", BRIEF)                             # an adopted project has these filled in
    w(aidir, "CODEMAP.md", CODEMAP)
    w(aidir, "OVERVIEW.md", OVERVIEW)
    gr = os.path.join(aidir, "guides/README.md")            # ...and list it in the guides catalog
    with open(gr, encoding="utf-8") as fh:
        text = fh.read()
    # append, don't replace a placeholder row — a real project's catalog already lists its own how-tos
    w(aidir, "guides/README.md",
      text.rstrip() + "\n| [do-the-thing.md](./do-the-thing.md) | do the recurring thing |\n")
    w(aidir, "prd/README.md", CATALOG)       # add two sample PRDs + a matching catalog
    w(aidir, "prd/base-core.md", BASE)
    w(aidir, "prd/entity-widget.md", WIDGET)
    w_repo(aidir, "AGENTS.md", AGENTS)       # the repo root the payload is adopted into


def run(aidir, *flags):
    p = subprocess.run([sys.executable, DOC_LINT, *flags, aidir], capture_output=True, text=True)
    return p.returncode, p.stdout


def case(name, mutate, expect_ok, sub=None, flags=()):
    with tempfile.TemporaryDirectory() as d:
        aidir = os.path.join(d, ".knowledge")
        build_base(aidir)
        mutate(aidir)
        code, out = run(aidir, *flags)
    ok = (code == 0)
    passed = (ok == expect_ok) and (sub is None or sub in out)
    detail = "" if passed else f"  (exit={code}, wanted_ok={expect_ok}, sub={sub!r})\n{out}"
    print(f"[{'PASS' if passed else 'FAIL'}] {name}{detail}")
    return passed


def main():
    cases = [
        # --- the shipped structure is intact ---
        ("valid full payload passes", lambda a: None, True),
        ("a shipped guide was removed",
         lambda a: os.remove(os.path.join(a, "guides/docs-brief.md")),
         False, "required file `guides/docs-brief.md` is missing"),
        ("a home directory was removed",
         lambda a: shutil.rmtree(os.path.join(a, "references")),
         False, "required directory `references/` is missing"),
        ("the linter script was renamed away",
         lambda a: os.remove(os.path.join(a, "scripts/doc-lint")),
         False, "required file `scripts/doc-lint` is missing"),
        # --- payload integrity: `.version` is only a claim until the checksums agree with it ---
        ("a versioned guide was edited after adoption",
         lambda a: w(a, "guides/docs-prd.md",
                     open(os.path.join(a, "guides/docs-prd.md"), encoding="utf-8").read()
                     + "\nOur project decided otherwise about all of the above.\n"),
         False, "no longer match the checksums recorded"),
        ("the manifest no longer covers a versioned file",
         lambda a: w(a, ".payload-manifest", "".join(
             line + "\n"
             for line in open(os.path.join(a, ".payload-manifest"), encoding="utf-8").read().splitlines()
             if not line.endswith("scripts/doc-lint"))),
         False, "does not cover `scripts/doc-lint`"),
        ("the payload manifest is missing",
         lambda a: os.remove(os.path.join(a, ".payload-manifest")),
         False, "integrity manifest is missing"),
        # --- the stakeholder-facing overview is schema-closed, same as a contract ---
        ("an overview heading outside the schema",
         lambda a: w(a, "OVERVIEW.md", OVERVIEW + "\n## Why it is different\n\nInvented prose.\n"),
         False, "`## Why it is different` is not in the closed schema"),
        ("an overview missing a required section",
         lambda a: w(a, "OVERVIEW.md", OVERVIEW.replace(WHAT_THIS_IS, "")),
         False, "missing required section `## What this is`"),
        ("overview sections out of order",
         lambda a: w(a, "OVERVIEW.md", OVERVIEW.replace("## What you use", "## PLACE")
                     .replace("## What governs it", "## What you use").replace("## PLACE", "## What governs it")),
         False, "sections are out of order"),
        ("an overview without its framing line",
         lambda a: w(a, "OVERVIEW.md", OVERVIEW.replace(FRAMING, "")),
         False, "framing line under the title is gone"),
        ("a trio file lost its standard pointer",
         lambda a: w(a, "BRIEF.md", "# Brief\n\nJust a project, no pointer at the bottom.\n"),
         False, "must end with its standard pointer"),
        # --- PRD contract rules ---
        ("two namespaces in one file",
         lambda a: w(a, "prd/entity-widget.md", WIDGET.replace("R-WIDGET-1", "R-OTHER-1")),
         False, "does not match file namespace"),
        ("duplicate ID across files",
         lambda a: w(a, "prd/base-core.md", BASE.replace("id: CORE", "id: WIDGET").replace("R-CORE-1", "R-WIDGET-1")),
         False, "already defined"),
        ("requirement over 25 words",
         lambda a: w(a, "prd/base-core.md", BASE.replace(
             "persists between runs",
             "persists between runs and also across every conceivable restart cycle no matter how many "
             "times the machine happens to reboot itself over and over again without any exception")),
         False, "max 25"),
        ("numeric literal in requirement",
         lambda a: w(a, "prd/base-core.md", BASE.replace("between runs", "for 30 runs")),
         False, "numeric literal"),
        ("two assertions joined by a semicolon",
         lambda a: w(a, "prd/base-core.md", BASE.replace("persists between runs",
                                                         "persists between runs; it also survives a crash")),
         False, "semicolon"),
        ("a namespace skips an id",
         lambda a: w(a, "prd/base-core.md",
                     BASE + "| ❌ | R-CORE-3 | The substrate reports its own version | — |\n"),
         False, "namespace `CORE` skips R-CORE-2"),
        ("checkmark with no test named",
         lambda a: w(a, "prd/base-core.md", BASE.replace("| `coreHolds` |", "| — |")),
         False, "names no test"),
        ("contract cites a draft",
         lambda a: (w(a, "prd-drafts/entity-future.md",
                      "---\nid: FUTURE\nname: Future\n---\n\n## Requirements\n\n"
                      "|  | ID | Requirement | Evidence |\n|:--:|---|---|---|\n"
                      "| ❌ | R-FUTURE-1 | A future thing exists | — |\n"),
                    w(a, "prd/entity-widget.md", WIDGET.replace("built on R-CORE-1.", "built on R-CORE-1 and R-FUTURE-1."))),
         False, "prd-drafts/ proposal"),
        ("citation resolves nowhere",
         lambda a: w(a, "prd/entity-widget.md", WIDGET.replace("R-CORE-1.", "R-GHOST-9.")),
         False, "resolves nowhere"),
        ("catalog omits a PRD file",
         lambda a: w(a, "prd/README.md", CATALOG.replace("  - [entity-widget](./entity-widget.md)\n", "")),
         False, "catalog omits"),
        ("catalog omits a research note",
         lambda a: w(a, "research/market.md",
                     "# Research: market\n\n**Last updated:** 2026-07-16\n\n## What they do\n\nA claim.\n"),
         False, "research/README.md: catalog omits market.md"),
        ("two files claim one namespace",
         lambda a: (w(a, "prd/base-core.md", BASE.replace("id: CORE", "id: WIDGET").replace("R-CORE-1", "R-WIDGET-9")),
                    w(a, "prd/entity-widget.md", WIDGET.replace("built on R-CORE-1.", "standalone."))),
         False, "one file per namespace"),
        ("a catalog links to a missing file",
         lambda a: w(a, "guides/README.md",
                     open(os.path.join(a, "guides/README.md"), encoding="utf-8").read()
                     + "\n[ghost](./docs-ghost.md)\n"),
         False, "links to missing"),
        ("a README is missing a required section",
         lambda a: w(a, "research/README.md", "# research/ — catalog\n\nGutted, no sections.\n"),
         False, "missing required section"),
        ("last_verified without a checkmark",
         lambda a: w(a, "prd/entity-widget.md", WIDGET.replace("name: Widget\n---", "name: Widget\nlast_verified: 2026-07-16\n---")),
         False, "no ✅ row"),
        ("citation into a later layer",
         lambda a: w(a, "prd/base-core.md", BASE.replace("The substrate.", "The substrate. Uses R-WIDGET-1.")),
         False, "up the stack"),
        ("filename prefix not a component",
         lambda a: w(a, "prd/gadget-foo.md",
                     "---\nid: GADGET\nname: Gadget\n---\n\n## Requirements\n\n"
                     "|  | ID | Requirement | Evidence |\n|:--:|---|---|---|\n"
                     "| ❌ | R-GADGET-1 | A gadget does a thing | — |\n"),
         False, "filename prefix not a listed component"),
        ("heading outside the schema",
         lambda a: w(a, "prd/entity-widget.md", WIDGET + "\n## Notes\n\nextra prose.\n"),
         False, "not in the closed schema"),
        ("a PRD hides in a subdirectory",
         lambda a: w(a, "prd/legacy/entity-hidden.md",
                     "---\nid: HIDDEN\nname: Hidden\n---\n\n## Requirements\n\n"
                     "|  | ID | Requirement | Evidence |\n|:--:|---|---|---|\n"
                     "| ❌ | R-HIDDEN-1 | A hidden thing exists | — |\n"),
         False, "is a subdirectory"),
        ("a citation cycle inside one layer",
         lambda a: (w(a, "prd/entity-widget.md", WIDGET.replace("A widget, built on R-CORE-1.", "A widget. See R-OTHER-1.")),
                    w(a, "prd/entity-other.md",
                      "---\nid: OTHER\nname: Other\n---\n\n## What this is\n\nAn other. See R-WIDGET-1.\n\n"
                      "## Requirements\n\n|  | ID | Requirement | Evidence |\n|:--:|---|---|---|\n"
                      "| ❌ | R-OTHER-1 | An other thing exists | — |\n"),
                    w(a, "prd/README.md", CATALOG + "  - [entity-other](./entity-other.md)\n")),
         False, "citation cycle"),
        ("research heading outside the schema",
         lambda a: (w(a, "research/market.md",
                      "# Research: market\n\n**Last updated:** 2026-07-16\n\n## What they do\n\nStuff.\n"
                      "\n## Extra Thoughts\n\nMine.\n"),
                    w(a, "research/README.md",
                      open(os.path.join(a, "research/README.md"), encoding="utf-8").read() + "\n[market](./market.md)\n")),
         False, "not in the closed schema"),
        ("a source with no link",
         lambda a: (w(a, "research/market.md",
                      "# Research: market\n\n**Last updated:** 2026-07-16\n\n## What they do\n\nA claim [1].\n"
                      "\n## Sources\n\n1. Some vendor's documentation\n"),
                    w(a, "research/README.md",
                      open(os.path.join(a, "research/README.md"), encoding="utf-8").read() + "\n[market](./market.md)\n")),
         False, "carries no link"),
        ("an internal source needs no link",
         lambda a: (w(a, "research/market.md",
                      "# Research: market\n\n**Last updated:** 2026-07-16\n\n## What they do\n\nA claim [1].\n"
                      "\n## Sources\n\n1. Our own auction walkthrough (internal)\n"),
                    w(a, "research/README.md",
                      open(os.path.join(a, "research/README.md"), encoding="utf-8").read() + "\n[market](./market.md)\n")),
         True),
        ("a source that is never cited",
         lambda a: (w(a, "research/market.md",
                      "# Research: market\n\n**Last updated:** 2026-07-16\n\n## What they do\n\nA claim [1].\n"
                      "\n## Sources\n\n1. First — https://example.com/a\n2. Unused — https://example.com/b\n"),
                    w(a, "research/README.md",
                      open(os.path.join(a, "research/README.md"), encoding="utf-8").read() + "\n[market](./market.md)\n")),
         False, "is never cited"),
        ("research note missing its date",
         lambda a: w(a, "research/competitor.md",
                     "# Research: competitor\n\n**Question it answers:** what\n\n## What they do\n\nStuff.\n"),
         False, "**Last updated:**"),
        ("research citation without a source",
         lambda a: w(a, "research/market.md",
                     "# Research: market\n\n**Last updated:** 2026-07-16\n\n## What they do\n\nA claim [1].\n"),
         False, "no Sources entry"),
        ("a catalog is missing its guide link",
         lambda a: w(a, "prd/README.md",
                     CATALOG.replace("To write or modify one, follow [../guides/docs-prd.md](../guides/docs-prd.md).\n\n", "")),
         False, "must link to"),
        ("an unexpected entry at the root",
         lambda a: w(a, "SCRATCH.md", "not allowed here\n"),
         False, "unexpected entry at the .knowledge/ root"),
        ("an unfilled orientation doc",
         lambda a: w(a, "BRIEF.md", "# Brief — <project>\n\nUnfilled.\n\n---\n"
                                    "*Editing this file? Follow the standard first: [`guides/docs-brief.md`]"
                                    "(./guides/docs-brief.md).*\n"),
         False, "shipped placeholder"),
        ("reporting with no citation",
         lambda a: w(a, "research/how-others-do-it.md",
                     NOTE.replace("They do a thing [1], and we have seen it ourselves [2].",
                                  "They do a thing, and we have seen it ourselves.")
                         .replace("1. A public page — https://example.com/a-page\n", "")
                         .replace("2. Our own walkthrough (internal)\n", "")),
         False, "cites no source"),
        # --- the entry point that routes agents into .knowledge/ at all ---
        ("the repo has no AGENTS.md",
         lambda a: os.remove(os.path.join(os.path.dirname(a), "AGENTS.md")),
         False, "no AGENTS.md"),
        ("AGENTS.md no longer points at its own standard",
         lambda a: w_repo(a, "AGENTS.md",
                          "# AGENTS\n\nRead `.knowledge/BRIEF.md`, `.knowledge/CODEMAP.md`, "
                          "`.knowledge/MEMORY.md`.\n"),
         False, "must point at"),
        ("AGENTS.md no longer loads the trio",
         lambda a: w_repo(a, "AGENTS.md", "# AGENTS\n\nBuild well. Nothing about the knowledge base.\n"),
         False, "must load the orientation trio"),
    ]
    results = [case(*c) for c in cases]
    print(f"\n{sum(results)}/{len(results)} checks passed")
    return 0 if all(results) else 1


if __name__ == "__main__":
    sys.exit(main())
