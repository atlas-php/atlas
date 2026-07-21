---
id: ERROR
name: Errors
---

## What this is

Every failure Atlas can raise, expressed as a typed, catchable error that carries the provider context a caller needs to react — which provider, which model, what the provider itself said, and how to recover.

## Why it exists

- A caller catches one base type to handle any Atlas failure, or a specific type to handle one category.
- A failure explains itself with the provider's own words instead of an opaque wrapper.
- A rate limit tells the caller exactly how long to back off.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-ERROR-1 | Every Atlas failure is catchable through one common base error type. | `AtlasException extends RuntimeException` |
| ✅ | R-ERROR-2 | A failed provider call exposes its provider, model, status, and the provider's own message. | `ProviderException stores all properties` |
| ✅ | R-ERROR-3 | The provider's real error message is surfaced however the provider shapes its response. | `ProviderException::from extracts the real message across provider error shapes` |
| ✅ | R-ERROR-4 | A rate-limit error carries how long to wait before retrying. | `RateLimitException stores provider, model, and retryAfter` |
| ✅ | R-ERROR-5 | An unsupported-feature error names the feature and the provider lacking it. | `UnsupportedFeatureException::make includes feature and provider` |
| ✅ | R-ERROR-6 | A failed provider call exposes the provider's raw response body. | `ProviderException exposes the raw response body` |
| ✅ | R-ERROR-7 | A network failure before any response is a typed error carrying no status. | `ConnectionException is a ProviderException with a null status and no status bracket` |
| ✅ | R-ERROR-8 | An unknown provider name is refused with a typed error. | `ProviderNotFoundException includes key in message` |
| ✅ | R-ERROR-9 | An unknown agent is refused with a typed error. | `throws on unknown agent key` |
| ✅ | R-ERROR-10 | An unknown tool is refused with a typed error. | `throws ToolNotFoundException for unknown tool name` |
| ✅ | R-ERROR-11 | Exceeding the agent step limit is refused with a typed error. | `throws MaxStepsExceededException when limit reached` |

## Open questions

- None.
