---
id: FAILOVER
name: Provider failover
---

## What this is

An ordered chain of providers tried in turn: when the active provider is unavailable, the request
moves to the next provider in the chain. Largely a proposal today rather than built behavior.

## Why it exists

- A transient provider outage does not have to fail the request.
- A rate-limited provider can hand off to an alternate.
- The order in which alternates are tried is the consumer's choice.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ❌ | R-FAILOVER-1 | An ordered chain of alternate providers can be declared for a request. | — |
| ❌ | R-FAILOVER-2 | Failover advances to the next provider when the active one is unavailable. | — |
| ❌ | R-FAILOVER-3 | Failover advances to the next provider when the active one is rate-limited. | — |
| ❌ | R-FAILOVER-4 | A limit caps how far the chain is walked before the request fails. | — |
| ❌ | R-FAILOVER-5 | Failover composes with per-call retries without duplicating attempts. | — |

## Open questions

- Whether the chain is declared per request, per modality default, or globally.
- Whether a rate-limit response fails over immediately or after per-call retries are exhausted.
- How a served response reports which provider in the chain ultimately answered.
- Whether failover can be reconciled with a streaming response once the stream has begun.
