---
id: EMBED
name: Embeddings
---

## What this is

Turning text into numeric vectors that place similar meanings close together, so content can be compared
and searched by meaning rather than by exact words.

## Why it exists

- A developer compares and searches content by meaning instead of keywords.
- One configured vector size keeps stored vectors and requested vectors in agreement.
- Repeated embeddings are served from cache instead of paying to recompute them.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-EMBED-1 | One or more text inputs are turned into vector embeddings. | `sends embedding request to /v1/embeddings` |
| ✅ | R-EMBED-2 | Token usage is reported alongside the returned embeddings. | `sends embedding request to /v1/embeddings` |
| ✅ | R-EMBED-3 | A batch keeps each output aligned to the input that produced it. | `realigns a batch response by the index field, not array position` |
| ✅ | R-EMBED-4 | The configured vector size is sent to models that accept it. | `forwards the configured dimensions for text-embedding-3 models` |
| ✅ | R-EMBED-5 | The configured vector size is withheld from models that cannot accept it. | `does not send dimensions for models that do not support it` |
| ✅ | R-EMBED-6 | A per-request vector size overrides the configured size. | `preserves an explicit dimensions provider option` |
| ✅ | R-EMBED-7 | A returned vector of the wrong size fails with a clear error before storage rejects it. | `throws an actionable error when the vector dimension does not match the configured size` |
| ✅ | R-EMBED-8 | A resolved embedding is served from cache when caching is enabled. | `returns cached value when cache is enabled` |

## Open questions

- Whether a per-model embedding provider override belongs as a guaranteed row once the interface supports it.
