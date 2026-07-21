---
id: DISCOVERY
name: Provider discovery
---

## What this is

The runtime interrogation of a configured provider — the models and voices it offers, whether its
credentials work, and which capabilities it supports — without sending a real generation request.

## Why it exists

- A model or voice selector can be built from live provider data.
- Credentials can be checked before a request depends on them.
- Repeated interrogation avoids repeated provider calls.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-DISCOVERY-1 | A provider's available models are listed at runtime. | `lists models from /v1/models` |
| ✅ | R-DISCOVERY-2 | A provider's available voices are listed at runtime. | `fetches voices from /v1/tts/voices` |
| ✅ | R-DISCOVERY-3 | A provider's credentials and connectivity can be validated. | `validate returns true when models succeeds` |
| ✅ | R-DISCOVERY-4 | A provider's capability support can be inspected at runtime. | `delegates capabilities to driver` |
| ✅ | R-DISCOVERY-5 | A provider's listings are reused from cache rather than refetched each time. | `flushCache clears models and voices cache` |
| ✅ | R-DISCOVERY-6 | Listing caching is disabled when its configured lifetime is set to none. | `enabled checks ttl` |

## Open questions

- Whether a stale listing can be refreshed on demand without waiting for its lifetime to expire.
