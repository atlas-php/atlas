---
id: CACHE
name: Prompt caching
---

## What this is

The capability that reuses the static prefix of a request — instructions, tool definitions, and prior
turns — so repeated tokens are billed at a discount instead of full price. It is on by default and the
savings are reported on every response.

## Why it exists

- A developer pays less for long, repeated prompts without changing how they call Atlas.
- Caching can be turned off for a single call when it is not wanted.
- The cache savings are visible on the response so cost can be tracked.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-CACHE-1 | Prompt caching is on by default. | `defaults the cache flag from atlas.prompt_cache config` |
| ✅ | R-CACHE-2 | A caller disables caching for a single call. | `cache(false) overrides the config default off` |
| ✅ | R-CACHE-3 | A caller enables caching for a single call. | `cache(true) sets the cache flag on the request handed to the driver` |
| ✅ | R-CACHE-4 | Explicit cache breakpoints mark the stable prefix when caching is on. | `marks system and trailing message with cache_control when caching is on` |
| ✅ | R-CACHE-5 | No cache breakpoint is added when caching is off. | `does not emit cache_control when caching is off` |
| ✅ | R-CACHE-6 | Tokens read from the cache are reported on a response's usage. | `parses usage with cached tokens` |
| ✅ | R-CACHE-7 | Tokens written to the cache are reported on a response's usage. | `surfaces cache read/write tokens from the streamed message_start usage` |
| ❌ | R-CACHE-8 | Cache token counts are reported for providers that cache automatically. | src/Providers/Responses/ResponseParser.php:114 — no test |

## Open questions

- Whether the automatic-caching providers should carry their own proven cache-reporting rows or share one.
