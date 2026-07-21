---
id: BATCH
name: Batch
---

## What this is

Submitting many independent requests together as one deferred job that a provider processes within its
completion window, trading immediacy for roughly half the cost. Results come back keyed to each
caller-supplied identifier.

## Why it exists

- Large latency-tolerant workloads run together at roughly half the cost.
- Each result finds its way back to the record that produced it.
- Work that depends on synchronous round-trips is refused rather than run wrong.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-BATCH-1 | Many independent requests are submitted together as one deferred job. | `submits statelessly through the resolved driver` |
| ✅ | R-BATCH-2 | Each result carries back the caller-supplied key of the request that produced it. | `wraps a request DTO with a custom id` |
| ✅ | R-BATCH-3 | Several batches are tracked together as one group. | `group() succeeds when persistence is available` |
| ✅ | R-BATCH-4 | A group signals completion once its last job finishes. | `fires BatchGroupCompleted when the last job in a group completes` |
| ✅ | R-BATCH-5 | A request carrying tools is refused when added to a batch. | `rejects a request carrying local tools` |
| ✅ | R-BATCH-6 | A request carrying per-request middleware is refused when added to a batch. | `rejects a request carrying per-request middleware` |
| ✅ | R-BATCH-7 | A request type that cannot be batched is refused when added to a batch. | `rejects a non-batchable request type` |
| ✅ | R-BATCH-8 | Each provider batches only the modalities it declares support for. | `declares the exact batch modality matrix per provider` |
| ✅ | R-BATCH-9 | A modality outside a provider's batch support is refused. | `rejects modalities outside the provider allow-list` |

## Open questions

- Whether a group's rolled-up progress figures warrant their own guaranteed row.
