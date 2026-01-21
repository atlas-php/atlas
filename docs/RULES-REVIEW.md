# Review Rules

> **Purpose:** Guidelines for conducting code reviews when explicitly requested by the user.
> **When to Use:** Only when the user asks for a review (e.g., "review this code", "check for bugs", "performance review").

---

## Overview

These rules define how to conduct structured code reviews. Reviews identify actionable issues without creating separate documentation files. Provide findings directly in your response.

**Key principle:** Only flag real issues. If it won't crash, won't produce wrong results, won't cause maintenance nightmares, and isn't noticeably slow—don't flag it.

---

## Review Types

When the user requests a review, identify which type(s) they want:

| Type         | Focus                                        | When User Says                                    |
|--------------|----------------------------------------------|---------------------------------------------------|
| Performance  | User-visible slowness, timeouts              | "performance review", "why is this slow"          |
| Quality      | Maintainability, readability                 | "code quality", "review for maintainability"      |
| Testability  | Can this code be tested effectively          | "testability review", "is this testable"          |
| Bugs         | Code that crashes or produces wrong results  | "check for bugs", "find issues"                   |
| Redundancy   | Duplication causing inconsistency            | "find duplication", "redundancy check"            |
| Architecture | Coupling, boundaries, separation of concerns | "architecture review", "check decoupling"         |

**Out of scope:** Security reviews require specialized expertise and tooling. If asked for a security review, recommend a dedicated security audit.

If unclear, ask the user which review type they want.

---

## Review Scope

When starting a review, clarify scope if not obvious:

| Scope       | What to Review                          | When to Use                              |
|-------------|-----------------------------------------|------------------------------------------|
| Focused     | Single file or method                   | "review this function"                   |
| Feature     | All files related to a feature          | "review the new export feature"          |
| Broad       | Module or system-wide patterns          | "review the agent system"                |

For broad reviews, prioritize Critical/High issues and note if a deeper focused review would help specific areas.

---

## Performance Review

**Focus:** Bottlenecks causing user-visible slowness or failures.

**Look for:**
- Slow page loads or API responses
- Timeouts on user-initiated actions
- N+1 queries on user-facing pages
- Synchronous operations blocking requests
- Memory issues causing degraded UX

**Ignore:**
- Micro-optimizations with no user impact
- Background job performance (unless user-visible)
- Theoretical bottlenecks not yet hit

---

## Quality Review

**Focus:** Code that increases risk of future bugs or makes changes harder.

**Look for:**
- High cyclomatic complexity
- Deep nesting obscuring logic
- Dead code confusing maintainers
- God classes/methods doing too much
- Unclear naming leading to misuse
- Missing or misleading documentation

**Ignore:**
- Style preferences already handled by linters
- Working code that's merely unfamiliar
- Missing comments on self-documenting code

---

## Testability Review

**Focus:** Code that resists effective testing.

**Look for:**
- Code requiring extensive mocking to test
- Hidden dependencies (`new` inside methods, static calls)
- Tightly coupled classes that can't be tested in isolation
- Global state or singletons making tests unreliable
- Methods doing too much to test meaningfully
- Missing seams for injecting test doubles

**Ignore:**
- Untested code that's easily testable (that's a coverage issue, not testability)
- Framework code that's intentionally hard to test

---

## Bug Review

**Focus:** Code that will crash, produce wrong results, or corrupt data.

**The Bug Test:** Will this code crash or produce wrong results in normal operation?

**Look for:**
- Wrong logic (inverted conditions, off-by-one errors)
- Null/undefined access that will throw
- Index out of bounds
- Wrong calculations or formulas
- Resource leaks (connections, file handles)
- Race conditions with observable bad outcomes
- Type errors that fail at runtime

**Ignore:**
- Auth/permission issues (security concern)
- Missing input validation (defensive coding)
- "Should add null check" (defensive coding)
- "Could fail if X" (hypotheticals)

**Severity levels:**
- Critical: Will crash or corrupt data
- High: Will produce wrong results
- Medium: Fails in realistic edge cases
- Low: Requires contrived scenario

---

## Redundancy Review

**Focus:** Duplication that has caused or will likely cause inconsistent behavior.

**Look for:**

**Diverged Copies:**
- Same bug fixed in one copy but not another
- Slightly different parameter orders or default values
- Comments referencing the "original" or "copied from"
- Similar method names with subtle behavior differences

**Structural Duplication:**
- Multiple implementations of the same business rule
- Parallel class hierarchies doing similar things
- Copy-pasted validation logic across endpoints
- Repeated query patterns that should be shared

**Ignore:**
- Intentional duplication (performance, isolation, clarity)
- Similar-looking code that handles genuinely different cases
- Duplication that hasn't caused problems and is unlikely to diverge

---

## Architecture Review

**Focus:** Coupling, boundary violations, separation of concerns, and appropriate abstraction levels.

**The Architecture Test:** Does this code know too much about things it shouldn't, do too many things itself, or add unnecessary layers of indirection?

### Quick Decision Guide

| Question                                 | If Yes                    | If No         |
|------------------------------------------|---------------------------|---------------|
| Is the code hard to test or change?      | Under-engineering concern | —             |
| Is the code hard to understand or trace? | Over-engineering concern  | —             |
| Neither?                                 | —                         | Probably fine |

### Under-Engineering (Too Coupled)

**Look for:**
- Service A directly instantiating Service B (use dependency injection)
- Hard-coded class references instead of contracts/interfaces
- Circular dependencies between services
- Business logic in the wrong layer (models, controllers, integrations)
- Direct database queries outside appropriate layer

**Severity:**
- Critical: Circular dependencies, complete layer bypass
- High: Business logic in wrong layer, tight coupling blocking testability
- Medium: Missing interface where testing requires it
- Low: Minor boundary blur, low-impact coupling

### Over-Engineering (Too Abstract)

**The Simplicity Test:** Does this abstraction earn its complexity? If removing a layer wouldn't hurt testability, flexibility, or clarity—it shouldn't exist.

**Look for:**
- Interfaces with only one implementation (and no realistic expectation of others)
- Wrapper classes that add no behavior
- Factory classes for simple object construction
- Multiple layers to reach simple functionality
- Generic solutions for problems that don't exist yet

**Symptoms:**
- Tracing through 4+ files to understand a simple operation
- Adding a field requires changes in 5+ places
- Tests require extensive mocking of internal collaborators

**Severity:**
- High: Abstraction actively hinders understanding or maintenance
- Medium: Unnecessary complexity with no clear benefit
- Low: Slightly more abstract than needed, minimal impact

### Before Flagging Architecture Issues

**For coupling concerns:**
1. Does this coupling block testing or reuse?
2. Would changes here cascade to unrelated code?

**For abstraction concerns:**
1. Does this abstraction serve a concrete purpose today?
2. Could this be simpler without losing testability?

If the current approach is working fine, don't flag it.

---

## Response Format

### For Issues Found
```
## {Review Type} Review

### Issues

**{SEVERITY}: {Brief summary}**
- File: `path/to/file.php`
- Problem: {What's wrong}
- Impact: {What happens / user effect / maintenance cost}
- Fix: {Suggested minimal fix}

**{SEVERITY}: {Brief summary}**
...

### Summary
{X} issues found: {breakdown by severity}
```

### For Clean Code
```
## {Review Type} Review

No issues found. {Brief explanation of what was checked and why it's clean.}
```

### Acknowledging Good Patterns (Optional)

When flagging multiple issues, briefly acknowledge one or two things done well:
```
### Done Well
- Clear separation between X and Y
- Good use of dependency injection in Z
```

Keep it brief—don't force it if nothing stands out.

---

## Guidelines

### Severity Anchoring

| Severity | Impact                                                      |
|----------|-------------------------------------------------------------|
| Critical | Users cannot use core functionality, data loss, untestable  |
| High     | Broken functionality, blocked goals, significant tech debt  |
| Medium   | Degraded experience, workaround exists, moderate debt       |
| Low      | Users unaffected; developer inconvenience only              |

### The Meaningful Test

Before flagging an issue, ask:
1. **Is it real?** Specific code, not hypotheticals
2. **Does it affect users or maintainers?** Articulate the impact
3. **Is the fix worth it?** Benefit outweighs effort
4. **Is it actionable?** Clear fix guidance

If any answer is "no," don't flag it.

### When in Doubt, Don't Flag

A review with 3 high-confidence issues is more valuable than one with 10 maybes. If you're unsure whether something is a real problem, leave it out.

### Limit Low-Severity Issues

Report all Critical and High issues. For Medium and Low, limit to the 3-5 most impactful. Long lists of minor issues obscure what matters.

---

## Quick Reference

| Review Type  | Core Question                                     |
|--------------|---------------------------------------------------|
| Performance  | Is this slow enough that users notice?            |
| Quality      | Is this hard to read or maintain?                 |
| Testability  | Can this be tested without excessive mocking?     |
| Bugs         | Will this crash or produce wrong results?         |
| Redundancy   | Is duplication causing inconsistent behavior?     |
| Architecture | Are boundaries respected and abstractions earned? |