---
id: SEARCH
name: Similarity search
---

## What this is

A single search that finds the records closest in meaning to a query. It returns one uniform result
shape whether a model stores one vector per row or many chunks per row, and is callable from
application code or as an agent tool. Embeddings stay in sync as records change, re-embedding only
what moved.

## Why it exists

- Developers add semantic search with one call, regardless of how a model stores its vectors.
- The same search works from application code and as an agent tool.
- Editing a record re-embeds only what changed, keeping indexing cheap.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-SEARCH-1 | A whole-record search returns records ranked by meaning. | `embeds the query and returns SearchResult wrapping each matched record` |
| ✅ | R-SEARCH-2 | A chunked search returns matching chunks with their parent records. | `embeds the query string and returns SearchResult objects with hydrated parents` |
| ✅ | R-SEARCH-3 | A whole-record result leaves chunk-only fields empty. | `leaves chunk-only fields null on whole-record results` |
| ✅ | R-SEARCH-4 | A result limit caps how many matches return. | `respects the limit option` |
| ✅ | R-SEARCH-5 | A minimum-similarity floor restricts results to the closest matches. | `uses whereVectorSimilarTo when min_similarity is set, otherwise orderByVectorDistance` |
| ✅ | R-SEARCH-6 | An id scope restricts a search to specific records. | `scopes results to multiple ids when ids is an array` |
| ✅ | R-SEARCH-7 | An arbitrary predicate can further scope a search over the owner records. | `applies the where callback as an Eloquent scope on the owner builder` |
| ✅ | R-SEARCH-8 | An empty id scope returns nothing without calling the embedding provider. | `returns empty Collection without calling the embedding API when ids is an empty array` |
| ✅ | R-SEARCH-9 | Similarity search is available to an agent as a tool. | `creates tool from usingModel factory` |
| ✅ | R-SEARCH-10 | Searching a model that supports neither embedding mode fails with a clear message. | `throws when the model does not implement VectorEmbeddable` |
| ✅ | R-SEARCH-11 | Only changed chunks are re-embedded when a record is saved. | `edit → hash invalidation → sweep detects → only changed chunks re-embed` |
| ✅ | R-SEARCH-12 | A whole-record embedding is skipped when no source field changed. | `returns false when source field is not dirty` |

## Open questions

- None.
