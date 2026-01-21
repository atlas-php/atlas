# Documentation Rules

**Read this file before creating or updating any documentation.**

---

## Core Principles

1. **Accuracy First** - Documentation must reflect the actual implementation. Never document features that don't exist or work differently than described.

2. **Single Source of Truth** - Each concept should be documented in one place. Cross-reference, don't duplicate.

3. **Keep It Current** - Update documentation when code changes. Outdated docs are worse than no docs.

4. **Document the Why** - Code shows *what* and *how*. Documentation should explain *why* decisions were made.

---

## When to Document

Document when:
- A public interface isn't obvious from its signature
- Configuration options exist
- Behavior differs from what a developer might reasonably assume
- Multiple components interact in non-trivial ways
- A design decision might seem arbitrary without context

Don't document:
- Self-explanatory code
- Implementation details that may change
- Anything a developer can understand in 30 seconds of reading the code

---

## Document Types

### SPEC (Technical Specification)
**Location:** `docs/spec/`

Reference documentation describing how the system works. Written for developers who need to understand or integrate with a module.

**Use sections as needed.** Not every SPEC requires every section—include only what's relevant.

```markdown
# SPEC-{Module}

## Overview
Technical summary: what it does, when to use it.

## Architecture
Components, data flow, dependencies. Include diagrams if helpful.

## Interfaces
Contracts, public methods, events. The API surface.

## Data Structures
Models, DTOs, value objects.

## Error Handling
Expected errors, how they surface, how to handle them.

## Configuration
Required and optional settings.

## Design Decisions
Brief rationale for non-obvious architectural choices.

## Usage Examples
Code examples showing common use cases.
```

### GUIDE (Task-Oriented Documentation)
**Location:** `docs/guides/`

Step-by-step instructions for accomplishing specific tasks. Written for developers who need to *do* something, not just understand it.

```markdown
# {Task Title}

## Goal
What the reader will accomplish.

## Prerequisites
What they need before starting.

## Steps
Numbered, actionable steps with code examples.

## Common Issues
Troubleshooting for likely problems.

## Next Steps
Related guides or deeper documentation.
```

**Examples:** "Creating a Custom Tool", "Extending an Agent", "Adding a New Provider"

---

## Writing Guidelines

### Do

- Use clear, concise language
- Include code examples where helpful
- Link to related documentation
- Keep paragraphs short (3-5 sentences max)
- Explain *why*, not just *what*
- Document error cases and edge cases

### Don't

- Don't document obvious things
- Don't use marketing language
- Don't duplicate content across files
- Don't include sensitive data (keys, passwords)
- Don't over-format with excessive headers and bullets

### Code Examples

- Must be syntactically correct
- Should be minimal but complete
- Include necessary imports/namespaces
- Use realistic variable names

```php
// Good
$agent = $registry->get('support-agent');
$response = $executor->execute($agent, 'Hello', $context);

// Bad - too abstract
$x = $r->get('a');
$y = $e->execute($x, 's', $c);
```

### Cross-References

Use relative links:

```markdown
See [SPEC-Agents](./spec/SPEC-Agents.md) for implementation details.
```

---

## Maintenance Rules

### When Code Changes

| Change Type     | Action                                           |
|-----------------|--------------------------------------------------|
| Add feature     | Update or create relevant SPEC                   |
| Change behavior | Update SPEC immediately                          |
| Deprecate       | Mark deprecated in docs, document migration path |
| Remove          | Remove from docs completely                      |

### Review Checklist

Before submitting documentation:

- [ ] Code matches documentation
- [ ] Code examples are correct and runnable
- [ ] Cross-references still valid
- [ ] No outdated information remains

---

## File Naming

| Type  | Pattern            | Example                      |
|-------|--------------------|------------------------------|
| SPEC  | `SPEC-{Module}.md` | `SPEC-Agents.md`             |
| GUIDE | `{Task-Title}.md`  | `Creating-Custom-Tools.md`   |