---
id: TRANSPORT
name: Transport
---

## What this is

The one shared connection every provider call rides on. It retries failures that are worth retrying, paces the attempts, and stamps each call so its whole lifecycle can be followed.

## Why it exists

- A call survives a transient hiccup instead of failing the first time the network blinks.
- A rate limit is respected automatically rather than hammering the provider.
- A whole request, retries included, can be traced under one identifier.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-TRANSPORT-1 | A transient provider failure is retried before it surfaces. | `retries transient 500 errors within limit` |
| ✅ | R-TRANSPORT-2 | A permanent provider failure is refused without any retry. | `does not retry a permanent 4xx even with retry enabled` |
| ✅ | R-TRANSPORT-3 | A rate-limited call waits the interval the provider asks for before retrying. | `uses Retry-After header for rate limits` |
| ✅ | R-TRANSPORT-4 | Each successive retry waits a longer backoff interval than the last. | `uses exponential backoff for transient errors` |
| ✅ | R-TRANSPORT-5 | A connection failure is retried like any other transient error. | `retries connection failures (status 0)` |
| ✅ | R-TRANSPORT-6 | A connection failure surfaces once its retry attempts are exhausted. | `retries a connection failure then rethrows when attempts are exhausted` |
| ✅ | R-TRANSPORT-7 | One correlation identifier spans a call across all of its retries. | `stamps one correlation id across the whole retried call lifecycle` |
| ✅ | R-TRANSPORT-8 | Streamed response frames are parsed reliably across read boundaries. | `handles large payloads that span multiple buffer reads` |

## Open questions

- Whether provider and model attribution on a call's lifecycle events belongs to a dedicated observability contract rather than here.
