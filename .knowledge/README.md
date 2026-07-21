# .knowledge/ — what lives here

The project's knowledge base: one home per kind of doc. This maps what's here and where to go — how to write
each lives in its `guides/*.md`. 

## The homes

| Path | Holds | How to write it |
|---|---|---|
| `BRIEF.md` | Orientation — what & why (always-loaded) | `guides/docs-brief.md` |
| `CODEMAP.md` | Orientation — where things are (always-loaded) | `guides/docs-codemap.md` |
| `MEMORY.md` | Orientation — current friction (always-loaded) | `guides/docs-memory.md` |
| `OVERVIEW.md` | **The platform, for a person** — a diagram and the walk through it | `guides/docs-overview.md` |
| `prd/` | **Tested contracts — the source of truth** | `guides/docs-prd.md` |
| `prd-drafts/` | Proposals, not yet approved | `guides/docs-prd.md` |
| `research/` | Prior art — dated notes on how others solved a problem | `guides/docs-research.md` |
| `references/` | Visual targets — screenshots, UI to match | `references/README.md` |
| `guides/` | The writing standards (`docs-*`) + project how-tos | `guides/README.md` |
| `../AGENTS.md` | The project's law (at the repo root, not here) | `guides/docs-agents.md` |
| `scripts/` | Tooling — the versioned linter, plus your own project scripts | `scripts/README.md` |
| `tmp/` | Git-ignored scratch — throwaway working files, never committed | — |

Work flows `research/` + `references/` → `prd-drafts/` → `prd/`.

## Versioning

Versioned by [knowledge-template](https://github.com/timothymarois/knowledge-template); the adopted version
is stamped in `.version`. To update, follow the upgrade steps in that repo.

`.payload-manifest` holds a checksum for every file that version owns — the `guides/docs-*.md` standards and
the two shipped scripts. `doc-lint` verifies them on every run, so the stamp is proven rather than trusted.
Never edit those files or the manifest here; a change belongs upstream and arrives as a version bump. Your
own project scripts live beside them in `scripts/` — only the listed files are off-limits.
