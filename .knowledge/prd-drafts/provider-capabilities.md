---
id: CAPABILITY
name: Provider capabilities
---

## What this is

Each provider's declaration of what it can do — which modalities and features it supports — and
the refusal a caller meets when asking a provider for something it does not offer.

## Why it exists

- A caller learns what a provider supports before spending a request on it.
- Asking a provider for something it cannot do fails clearly instead of silently.
- What a provider supports can be adjusted through configuration.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-CAPABILITY-1 | Each provider declares which capabilities it supports. | `reports correct capabilities` |
| ✅ | R-CAPABILITY-2 | A capability can be queried before it is used. | `returns true for supported features` |
| ✅ | R-CAPABILITY-3 | A feature a provider does not support is refused rather than silently ignored. | `throws UnsupportedFeatureException for embed` |
| ✅ | R-CAPABILITY-4 | Capability overrides supplied in configuration are honored. | `applies capability overrides from config` |
| ✅ | R-CAPABILITY-5 | A provider's batch support holds only for the modalities in its declared allow-list. | `canBatch requires both the batch flag and the modality allow-list` |
| ✅ | R-CAPABILITY-6 | A modality outside a provider's batch allow-list is refused. | `rejects modalities outside the provider allow-list` |
| ✅ | R-CAPABILITY-7 | A call to a declared capability reaches the provider that implements it. | `dispatch method is correct for each modality` |

## Open questions

- Whether a capability can be declared as model-dependent rather than provider-wide.
