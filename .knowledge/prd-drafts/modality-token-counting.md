---
id: COUNT
name: Token counting
---

## What this is

The capability that counts the input tokens a request would consume before it is sent, covering the whole
payload including tool schemas and media. It uses a provider's native count where one exists and falls
back to a clearly flagged estimate otherwise.

## Why it exists

- A developer can estimate cost or check the context window before spending a call.
- The count reflects the real payload, including tools and media a local tokenizer cannot see.
- A developer knows whether a number is exact or an estimate before trusting it.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-COUNT-1 | Input tokens are counted before a request is sent, without generating a response. | `counts tokens through the text builder without running generation` |
| ✅ | R-COUNT-2 | The count covers the full payload including instructions, tool schemas, and media. | `sums the chars/4 heuristic over every string leaf, recursively` |
| ✅ | R-COUNT-3 | The count is exact when a provider offers a native token count. | `counts tokens via the count_tokens endpoint, stripping generation params` |
| ✅ | R-COUNT-4 | The count is flagged as an estimate when no native count is available. | `flags the estimate and attributes provider/model` |
| ✅ | R-COUNT-5 | The count identifies the provider and model it applies to. | `flags the estimate and attributes provider/model` |
| ✅ | R-COUNT-6 | A per-category breakdown accompanies the count when a provider supplies one. | `includes a non-empty breakdown in the array form` |
| ✅ | R-COUNT-7 | A standalone utility estimates the token count of a plain string offline. | `uses chars-over-four heuristic` |
| ❌ | R-COUNT-8 | Output tokens are excluded from a pre-send count. | src/Responses/TokenCount.php:14 — no test |

## Open questions

- Whether the pre-flight count is guaranteed to skip middleware and the tool loop, and how that is proven.
