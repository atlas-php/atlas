---
id: RETRY
name: Retry and branch
---

## What this is

The regenerate-response behavior of a chat thread. Retrying an agent reply creates a new version
alongside the old one, keeps only the chosen version active, and lets a UI step between them. A
reply built from several tool-calling steps is treated as one version.

## Why it exists

- A user can regenerate an agent reply and compare the alternatives.
- Switching between versions cleanly changes what the agent sees next.
- Multi-step replies regenerate as a whole, never leaving orphaned fragments.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-RETRY-1 | A regenerated reply is stored as a sibling sharing the original's parent. | `maintains parent_id consistency across multiple retries` |
| ✅ | R-RETRY-2 | Regenerating a reply deactivates the previous version. | `toggles active state so loadMessages returns only the active sibling` |
| ✅ | R-RETRY-3 | Only the active sibling loads into later conversation history. | `excludes inactive sibling tool messages from loadMessages` |
| ✅ | R-RETRY-4 | A reply can be retried only while no later user message exists. | `returns false for canRetry when a later active user message exists` |
| ✅ | R-RETRY-5 | A reply with no later user message is retryable. | `returns true for canRetry when no later active user message exists` |
| ✅ | R-RETRY-6 | A user message itself cannot be retried. | `returns false for canRetry on user messages` |
| ✅ | R-RETRY-7 | The alternative versions of one reply can be stepped through. | `cycles through three retry groups correctly` |
| ✅ | R-RETRY-8 | Retrying preserves message ordering without gaps or collisions. | `increments sequences correctly across retries with no gaps or collisions` |
| ✅ | R-RETRY-9 | A multi-step reply stays grouped as a single version. | `groups multi-step responses as single sibling groups` |
| ✅ | R-RETRY-10 | Stepping to a multi-step version activates every message in that group. | `cycleSibling activates all messages in a multi-step group` |

## Open questions

- None.
