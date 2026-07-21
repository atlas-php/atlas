---
id: RESPONSE
name: Responses
---

## What this is

The result every generation hands back — what it produced, and what it cost in tokens. It reports usage the same way across providers and modalities, and can even estimate the input cost before a call is made.

## Why it exists

- A developer tracks and budgets cost from a consistent usage figure, whatever the provider.
- A caller knows why generation stopped without inspecting raw provider output.
- The cost of a request can be estimated up front, before it is ever sent.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-RESPONSE-1 | Every generation reports the input and output tokens it consumed. | `builds a full usage from an array` |
| ✅ | R-RESPONSE-2 | Reasoning and cached token counts are reported when the provider supplies them. | `merges reasoning tokens when present` |
| ✅ | R-RESPONSE-3 | Token usage rolls up into a single total. | `calculates total tokens` |
| ✅ | R-RESPONSE-4 | Usage accumulates across every step of a multi-step run. | `merges two usage objects` |
| ✅ | R-RESPONSE-5 | A response reports why generation stopped. | `populates finishReason from Done chunk after iteration` |
| ✅ | R-RESPONSE-6 | A request's input tokens can be counted before it is sent. | `exposes its fields` |
| ✅ | R-RESPONSE-7 | A token count declares whether it is an exact provider figure or an estimate. | `exposes its fields` |
| ✅ | R-RESPONSE-8 | A streamed result accumulates its produced text as chunks arrive. | `accumulates text from chunks during iteration` |

## Open questions

- None.
