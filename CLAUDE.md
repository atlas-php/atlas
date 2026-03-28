# CLAUDE.md

This file is the source of truth for Claude Code workflow, task management, and behavioral rules. For project coding conventions and architecture standards, see `AGENTS.md`.

---

## Session Start

1. Read `AGENTS.md` — follow all conventions, no exceptions

---

## Plan Mode

- Enter plan mode for ANY non-trivial task (3+ steps or architectural decisions)
- If something goes sideways, STOP and re-plan immediately – don't keep pushing
- Use plan mode for verification steps, not just building
- Write detailed specs upfront to reduce ambiguity
- For tasks under 3 steps, skip planning and execute directly

---

## Task Tracking

- Use the built-in todo tool to track all active items during execution
- When writing a plan, add each item to the todo tool so progress is visible
- If the user requests additional items mid-execution, add them to the todo tool immediately — never hold items in context alone

---

## Lessons & Memory

- Lessons go in Claude's **local auto-memory** — not in repo files
- Save gotchas, patterns, and corrections that come up repeatedly so future sessions benefit
- If a memory becomes redundant or outdated, remove it
- Don't duplicate anything already in `AGENTS.md` — that's the source of truth for conventions

---

## Autonomous Bug Fixing

- When given a bug report: just fix it. Don't ask for hand-holding
- Point at logs, errors, failing tests – then resolve them
- Zero context switching required from the user
- Go fix failing tests without being told how
- If intended behavior is ambiguous, check the test suite or ask once – then fix autonomously

---

## Subagent Strategy

- Use subagents liberally to keep main context window clean
- Offload research, exploration, and parallel analysis to subagents
- For complex problems, throw more compute at it via subagents
- One task per subagent for focused execution

---

## Code Review Agent

For complex changes that touch **3+ files**, dispatch a code review subagent before presenting the work to the user. The review agent should:

1. **Diff all changed files** against the base branch
2. **Check against `AGENTS.md` conventions** — verify the changes follow project standards (strict types, thin controllers, no duplicate code, proper imports, etc.)
3. **Look for regressions** — unintended side effects, broken patterns, missing error handling
4. **Report findings** — flag any issues back to the main agent for resolution before presenting to the user

Skip the review agent for simple, isolated changes (1–2 files, no architectural impact).
