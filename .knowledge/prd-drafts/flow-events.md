---
id: EVENT
name: Events
---

## What this is

The lifecycle signals a developer can listen to in order to watch a run unfold. They fire on their own at each stage, report without interfering, and selected ones reach live listeners as they happen.

## Why it exists

- A developer logs, traces, or reacts to a run without changing its behaviour.
- A single call's start, retries, and completion can be tied together.
- Live consumers watch a run progress in real time within transport limits.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-EVENT-1 | Lifecycle events fire automatically at each stage of a run. | `dispatches AgentStarted before the tool loop` |
| ❌ | R-EVENT-2 | Events observe a run and never change it. | src/Events/AgentCompleted.php — no test |
| ✅ | R-EVENT-3 | A stable identifier ties a call's start, retries, and completion together. | `stamps one correlation id across the whole retried call lifecycle` |
| ✅ | R-EVENT-4 | Selected events broadcast in real time to subscribers. | `orchestration events broadcast on provided channel` |
| ✅ | R-EVENT-5 | Event ordering is deterministic for a synchronous run. | `dispatches events in correct lifecycle order` |
| ❌ | R-EVENT-6 | Event ordering is deterministic for a queued run. | src/Queue/Jobs/ExecuteAtlasJob.php — no test |
| ✅ | R-EVENT-7 | A broadcast tool payload is capped to stay within transport limits. | `caps broadcast tool payloads when a max length is configured` |

## Open questions

- None.
