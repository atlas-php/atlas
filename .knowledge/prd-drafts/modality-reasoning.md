---
id: REASON
name: Reasoning
---

## What this is

The capability that lets a model work through a problem before answering, configured once with a single
effort knob that Atlas maps to each provider's native thinking format. Reasoning context carries across
the steps of a tool run so a thinking model stays coherent.

## Why it exists

- A developer picks an effort level once and it works across providers.
- A model's chain of thought survives a multi-step tool run instead of being lost between steps.
- A thought summary and its token cost are available when the model reports them.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-REASON-1 | One normalized effort level maps to each provider's native thinking format. | `maps reasoning effort to the Responses API reasoning object` |
| ✅ | R-REASON-2 | An effort level derives a thinking-token budget for budget-based providers. | `maps each effort to a thinking-token budget` |
| ✅ | R-REASON-3 | An explicit token budget overrides the effort-derived budget. | `prefers an explicit budget over the effort default` |
| ✅ | R-REASON-4 | A thought summary is requested when the caller opts in. | `requests a reasoning summary when includeSummary is set` |
| ✅ | R-REASON-5 | Reasoning content from the model is captured on the response. | `parses thinking blocks as reasoning` |
| ✅ | R-REASON-6 | Reasoning is captured with its signature so it can be replayed. | `captures the thinking signature into reasoning blocks for replay` |
| ✅ | R-REASON-7 | Reasoning from earlier steps is replayed on later tool turns so context survives. | `prepends a signed thinking block before tool_use blocks` |
| ✅ | R-REASON-8 | Reasoning is requested in a form that survives a stateless replay. | `requests encrypted reasoning content for stateless replay when reasoning is set` |

## Open questions

- Whether a raw provider option that collides with the effort knob is guaranteed to win, and how that is proven.
