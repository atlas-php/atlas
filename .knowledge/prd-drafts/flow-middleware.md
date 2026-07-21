---
id: MIDDLEWARE
name: Middleware
---

## What this is

Hooks a developer inserts to wrap execution at the agent, step, tool, or provider layer. Each hook declares which layer it belongs to, and can observe a run, change what flows through it, or stop it.

## Why it exists

- A developer adds logging, auth, or rate limiting without touching core logic.
- A hook lands at the right layer automatically, by what it declares.
- The same per-request hook runs whether the work happens inline or in the background.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-MIDDLEWARE-1 | Middleware is routed to its execution layer by the interface it declares. | `routes agent middleware to agent layer` |
| ✅ | R-MIDDLEWARE-2 | Provider middleware runs only on the modality it targets. | `routes text middleware only to text methods` |
| ✅ | R-MIDDLEWARE-3 | Middleware can short-circuit the pipeline without continuing downstream. | `short-circuits when middleware omits next` |
| ✅ | R-MIDDLEWARE-4 | Middleware can modify the context before it passes downstream. | `provider middleware can read and modify context` |
| ✅ | R-MIDDLEWARE-5 | Middleware can observe execution around the next handler without altering it. | `runs single middleware before and after` |
| ✅ | R-MIDDLEWARE-6 | Per-request middleware is preserved when the request runs in the background. | `restores per-request middleware across the queue boundary onto the executed request` |

## Open questions

- None.
