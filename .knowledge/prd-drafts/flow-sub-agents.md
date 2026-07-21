---
id: SUBAGENT
name: Sub-agents
---

## What this is

The behaviour that lets one agent hand part of a job to another agent. A listed agent becomes a delegation tool: it runs on its own, in isolation, and returns its answer to the parent as a tool result.

## Why it exists

- A developer keeps each agent focused and delegates specialised work to another.
- Independent hand-offs run together so a fan-out finishes sooner.
- Runaway or circular delegation is refused, keeping a chain bounded and predictable.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-SUBAGENT-1 | An agent listed among another agent's tools becomes a delegation tool. | `is flagged as a delegation tool` |
| ✅ | R-SUBAGENT-2 | A delegated sub-agent runs and returns its answer as the tool result. | `runs the sub-agent with the task and returns its text` |
| ✅ | R-SUBAGENT-3 | A sub-agent runs from a fresh history, not the parent's conversation. | `runs the sub-agent in isolation from the parent conversation` |
| ✅ | R-SUBAGENT-4 | A parent's per-call variables are forwarded into the sub-agent. | `forwards parent per-call variables into the sub-agent prompt` |
| ✅ | R-SUBAGENT-5 | Several sub-agents delegated in one step run at the same time. | `executes a concurrent batch containing a delegation tool through the concurrent path` |
| ✅ | R-SUBAGENT-6 | Delegation is refused once it would exceed the maximum depth. | `throws when the delegation depth limit is reached` |
| ✅ | R-SUBAGENT-7 | Delegating to an agent already in the chain is always refused. | `throws when the agent is already in the delegation chain` |
| ✅ | R-SUBAGENT-8 | By default a sub-agent failure surfaces as a failed delegation. | `propagates sub-agent errors when delegation_errors is throw` |
| ✅ | R-SUBAGENT-9 | A sub-agent failure can instead be handed back to the parent as text. | `returns the error as a string when delegation_errors is return` |

## Open questions

- None.
