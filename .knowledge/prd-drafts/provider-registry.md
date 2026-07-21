---
id: PROVIDER
name: Provider registry
---

## What this is

The lookup that turns a provider name into a ready connection, and the two ways a new provider
is added — a compatible endpoint by configuration, or a custom provider by naming a driver.
Every provider resolves the same way.

## Why it exists

- Any provider resolves the same way, so adding one is configuration rather than code.
- A misnamed provider fails immediately instead of failing deep inside a request.
- A resolved provider is shared, so repeated calls pay no rebuild cost.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-PROVIDER-1 | A provider named in configuration resolves to a ready-to-use connection. | `registers and resolves a provider` |
| ✅ | R-PROVIDER-2 | An unknown provider name is refused rather than silently resolved. | `throws ProviderNotFoundException for unknown key` |
| ✅ | R-PROVIDER-3 | An endpoint speaking the OpenAI-compatible protocol becomes a provider through configuration alone, with no new code. | `resolves chat_completions driver from config` |
| ✅ | R-PROVIDER-4 | A fully custom provider is added by naming a driver that receives its dependencies. | `custom driver class via config receives all four constructor deps` |
| ✅ | R-PROVIDER-5 | A configuration-named custom driver can serve a live capability call. | `custom driver class resolved from config can execute a modality call` |
| ✅ | R-PROVIDER-6 | An unrecognized driver name is refused. | `throws AtlasException for unknown driver string` |
| ✅ | R-PROVIDER-7 | A resolved provider is reused rather than rebuilt on each call. | `caches resolved instances` |

## Open questions

- Whether a provider alias may resolve to more than one endpoint for load spreading.
