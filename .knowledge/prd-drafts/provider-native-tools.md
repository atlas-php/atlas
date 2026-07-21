---
id: PTOOL
name: Provider-native tools
---

## What this is

Provider-hosted tools — web search, page fetch, file search, code execution, X search — that run
on the provider's own servers during a request, rather than as the consumer's own code.

## Why it exists

- A request can use a provider's built-in search or code tools without local implementation.
- The sources such a tool consulted are recoverable from the result.
- A tool a provider cannot run is caught before the request is sent.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-PTOOL-1 | A provider-hosted tool attaches to a request. | `withProviderTools includes provider tools in built request` |
| ✅ | R-PTOOL-2 | A provider-hosted tool can be scoped to chosen domains. | `WebSearch includes domain scoping when provided` |
| ✅ | R-PTOOL-3 | The sources a provider-hosted tool cited are captured on the result. | `flows provider tool calls and annotations from final response` |
| ✅ | R-PTOOL-4 | A provider-hosted tool a capable provider offers is allowed onto the request. | `allows a supported provider tool on a registry-tracked provider` |
| ✅ | R-PTOOL-5 | A provider-hosted tool a first-class provider cannot run is refused before the request is sent. | `names the configured provider in an unsupported-feature error` |
| ✅ | R-PTOOL-6 | The set of provider-hosted tools each provider offers can be queried. | `exposes the full provider support map` |

## Open questions

- Whether provider-hosted tools attached to a custom or compatible provider should be validated at all.
