---
id: RERANK
name: Reranking
---

## What this is

Taking a query and a set of candidate documents and returning them ordered by how well each answers the
query, sharpening results a first-pass search already gathered.

## Why it exists

- A developer improves retrieval quality by reordering candidates by true relevance.
- Weak candidates are trimmed by rank, score, or length before use.
- Ranked positions map back onto the caller's own records for display.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-RERANK-1 | A query and its documents return the documents ordered by relevance. | `returns indexes in relevance order` |
| ✅ | R-RERANK-2 | A minimum-score floor drops documents scoring below it. | `applies minScore filter client-side` |
| ✅ | R-RERANK-3 | A per-document token cap limits how much of each document is ranked. | `sends max_tokens_per_doc when set` |
| ✅ | R-RERANK-4 | A top-N limit narrows the result to the highest-scoring documents. | `returns top N results` |
| ✅ | R-RERANK-5 | A helper returns the single highest-scoring result. | `returns top result` |
| ✅ | R-RERANK-6 | A helper returns every result scoring above a given threshold. | `filters results above score threshold` |
| ✅ | R-RERANK-7 | A helper reorders the caller's own array into relevance order. | `reorders original documents by relevance` |

## Open questions

- None.
