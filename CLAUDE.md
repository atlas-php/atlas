# CLAUDE.md

This file is the source of truth for Claude Code workflow, task management, and behavioral rules. For project coding conventions and architecture standards, see `AGENTS.md`.

---

## Session Start

1. Read `AGENTS.md` — follow all conventions
2. Review `LESSONS.md` for the relevant project

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

## Self-Improvement & Lessons

- After ANY correction from the user: update `LESSONS.md` with the pattern
- Review lessons at session start before beginning work

**Writing lessons:**
- Keep entries tight and direct — one or two sentences max per lesson
- State the rule, not the story. Enough context to understand the "what" and "why", nothing more
- Bad: *"When I was working on the pipeline refactor, Tim pointed out that I was using Axios to fetch user data in onMounted instead of using Inertia props, which broke partial reloads and caused a flash of empty state"*
- Good: *"Server data must come from Inertia props, never Axios in onMounted — breaks partial reloads"*

**Maintaining lessons:**
- When reviewing lessons at session start, remove or update any that are outdated, redundant, or no longer apply to the current codebase
- If a lesson was absorbed into `AGENTS.md` as a formal convention, remove it from `LESSONS.md` — don't keep duplicates
- Consolidate similar lessons into a single entry rather than letting them accumulate

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