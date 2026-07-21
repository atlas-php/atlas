---
id: MODERATE
name: Moderation
---

## What this is

Checking text for unsafe or policy-violating content and returning a verdict with a per-category
breakdown, so an application can block or flag it before a user ever sees it.

## Why it exists

- An application blocks harmful content before it reaches a user.
- A per-category breakdown shows which kind of violation was detected.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-MODERATE-1 | Checked content returns a flagged-or-safe verdict. | `handles safe content` |
| ✅ | R-MODERATE-2 | Each policy category reports whether the content was flagged for it. | `sends moderation request to /v1/moderations` |
| ✅ | R-MODERATE-3 | A relevance score is reported for each policy category. | `sends moderation request to /v1/moderations` |
| ❌ | R-MODERATE-4 | A category scoring above the safety threshold is flagged as unsafe. | src/Providers/OpenAi/Handlers/Moderate.php — no test |

## Open questions

- Whether the flagging threshold is a package concern or delegated entirely to the provider.
