---
id: EXEC
name: Execution tracking
---

## What this is

A recorded audit trail of every provider call Atlas makes, whether from an agent or a direct
modality call. Each record carries the provider, model, token usage, timing, and outcome, plus the
steps and tool calls beneath it. Sub-agent runs nest under the run that spawned them, and usage
totals across the tree.

## Why it exists

- Every AI call has an audit trail of provider, model, cost, and timing.
- Developers inspect each step and tool call to debug agent behavior.
- The cost of a multi-agent run totals across its whole delegation tree.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-EXEC-1 | An agent provider call is recorded with its provider and model. | `creates execution in pending status with correct provider and model` |
| ✅ | R-EXEC-2 | A completed call records its token usage. | `transitions to completed and records usage` |
| ✅ | R-EXEC-3 | A call records when it began processing. | `transitions to processing and sets started_at` |
| ✅ | R-EXEC-4 | A failed call is recorded with its error. | `marks execution failed on exception` |
| ✅ | R-EXEC-5 | A direct modality call outside an agent is recorded as its own standalone execution. | `creates standalone execution for direct calls` |
| ✅ | R-EXEC-6 | A direct call made inside an agent run adds no second top-level execution. | `skips execution creation when agent execution is active` |
| ✅ | R-EXEC-7 | Each round trip in the agent tool loop is recorded as a step. | `creates step in pending with correct sequence` |
| ✅ | R-EXEC-8 | Each round trip records the model's response. | `creates step and records response on success` |
| ✅ | R-EXEC-9 | Each tool invocation records the arguments it received. | `creates tool call in pending with arguments` |
| ✅ | R-EXEC-10 | A completed tool invocation records its returned result. | `creates tool call and completes on success` |
| ✅ | R-EXEC-11 | Each tool invocation records how long it took to run. | `records duration` |
| ✅ | R-EXEC-12 | A nested sub-agent run is linked to the run that delegated to it. | `links a nested execution to its parent and delegating tool call` |
| ✅ | R-EXEC-13 | Token usage totals across the whole sub-agent tree. | `totalUsage sums the whole subtree across multiple children and grandchildren` |

## Open questions

- Whether delegation depth is a guaranteed behavior worth its own row beyond the parent-child link.
