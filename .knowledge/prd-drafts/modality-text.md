---
id: TEXT
name: Text
---

## What this is

The core capability that turns a message into generated text through one call, whichever provider and
model back it. It carries instructions, media, and tools into the request and returns the text, why it
stopped, and what it cost.

## Why it exists

- A developer generates text the same way no matter which provider serves the call.
- A single request can carry vision input and run tools without extra plumbing.
- Multi-step tool runs report their cost step by step, not just as a total.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-TEXT-1 | A user message returns a generated text response. | `dispatches asText to driver` |
| ✅ | R-TEXT-2 | A text response reports why generation stopped. | `maps end_turn stop reason to Stop` |
| ✅ | R-TEXT-3 | Media supplied with a message is sent inline for vision. | `converts user message with media to content array` |
| ✅ | R-TEXT-4 | Instructions steer the model as system-level guidance. | `sets instructions as top-level param` |
| ✅ | R-TEXT-5 | Usage totals accumulate across every step of a tool run. | `merges usage across all steps` |
| ✅ | R-TEXT-6 | Each step of a tool run is recorded with its own usage. | `AgentStepCompleted stores stepNumber, finishReason, usage` |
| ✅ | R-TEXT-7 | Provider-specific options pass through to the underlying request. | `passes provider options through` |
| ✅ | R-TEXT-8 | A provider option overrides a normalized setting on collision. | `overrides a colliding generationConfig scalar instead of turning it into a list` |

## Open questions

- Whether streaming delivery warrants its own contract separate from the text modality.
