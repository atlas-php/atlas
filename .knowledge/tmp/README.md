# tmp/ — local scratch

Throwaway space for AI-generated temporary files: draft output, generated assets, experiment results,
anything you need while working but nobody should keep. **Everything here is git-ignored** (except this
README and the folder markers) — it stays on your machine and never lands in a commit.

## Rules

- **Never durable knowledge.** A fact worth keeping goes to its home (`prd/`, `research/`, `MEMORY.md`, …),
  never here. If something in `tmp/` matters tomorrow, it's in the wrong place.
- **Assume it vanishes.** Don't reference a `tmp/` file from any committed doc — the link will rot.
- **Yours only.** Nothing here is shared; it's not part of the copied payload's meaning, just a workbench.
