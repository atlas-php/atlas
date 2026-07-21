---
id: TOOLLOOP
name: Tool loop
---

## What this is

The behaviour that lets a model reach an answer by calling your code. Across successive steps the model requests tools, receives their results, and keeps going until it produces a final reply.

## Why it exists

- A developer exposes tools and lets the model decide when to use them.
- Runaway conversations are bounded so a request always terminates.
- A tool that fails does not abort the turn — the model sees the error and adapts.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-TOOLLOOP-1 | The model calls tools across steps until it produces a final answer. | `handles one tool call across two round trips` |
| ✅ | R-TOOLLOOP-2 | A step ceiling stops the loop once its limit is reached. | `throws MaxStepsExceededException when limit reached` |
| ✅ | R-TOOLLOOP-3 | Removing the step ceiling lets the loop run without a limit. | `allows unlimited steps when maxSteps is null` |
| ✅ | R-TOOLLOOP-4 | Each tool declares its parameters as typed fields. | `returns parameters as Field array` |
| ✅ | R-TOOLLOOP-5 | A tool's return value is converted to text the model can read. | `resolves tool, calls handle, serializes result` |
| ✅ | R-TOOLLOOP-6 | A forced tool choice applies only to the opening step, then relaxes to automatic. | `forces the tool choice on the opening step, then relaxes it to auto` |
| ✅ | R-TOOLLOOP-7 | A specific named tool can be forced for the opening step. | `forces a specific named tool on the opening step, then relaxes to auto` |
| ✅ | R-TOOLLOOP-8 | A tool choice that forces no tool is left as the model's own decision. | `does not relax a non-required tool choice` |
| ✅ | R-TOOLLOOP-9 | Independent tool calls in one step run at the same time when concurrency is enabled. | `executes multiple tool calls concurrently with concurrent true` |
| ✅ | R-TOOLLOOP-10 | By default the tool calls in a step run one after another. | `executes multiple tool calls sequentially when concurrent is false` |
| ✅ | R-TOOLLOOP-11 | A tool that throws is caught and its error is fed back so the loop continues. | `catches tool errors and sends error result to model` |

## Open questions

- None.
