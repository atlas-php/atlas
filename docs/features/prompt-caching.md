# Prompt Caching

Prompt caching reuses the static prefix of a request — the system prompt, tool definitions, and prior conversation turns — so repeated tokens are billed at a steep discount instead of full price on every turn. For multi-turn conversations and agents with large system prompts, this is a major cost and latency win.

Atlas turns caching on by default and reports the savings on every response.

## How each provider caches

| Provider | Mechanism | What Atlas does |
|----------|-----------|-----------------|
| **Anthropic** | Explicit `cache_control` breakpoints | Atlas marks the system prompt and the end of the message history so the prefix is cached |
| **OpenAI** | Automatic (prompts ≥ 1024 tokens) | Nothing required — the provider caches server-side |
| **xAI** | Automatic | Nothing required |
| **Google** | Automatic implicit caching (Gemini 2.5+) | Nothing required |

You can check whether a provider supports caching:

```php
Atlas::provider('anthropic')->capabilities()->supports('caching'); // true
```

## Configuration

Caching is enabled by default. Control it globally in `config/atlas.php`:

```php
'prompt_cache' => (bool) env('ATLAS_PROMPT_CACHE', true),
```

Override it per call:

```php
// Disable caching for a single call
Atlas::agent('support')->cache(false)->message('Hello')->asText();

// Explicitly enable (e.g. when the global default is off)
Atlas::text('anthropic', 'claude-sonnet-4-5')->cache()->message('Hello')->asText();
```

`->cache()` is available on both `Atlas::agent()` and `Atlas::text()` builders.

## Reading cache usage

Every response's `usage` reports cache activity, regardless of provider:

```php
$response = Atlas::agent('support')->forConversation($id)->message('Hi')->asText();

$response->usage->cachedTokens;      // tokens served from cache (read) — the discount
$response->usage->cacheWriteTokens;  // tokens written to cache (Anthropic; first turn)
```

On the first call a stable prefix is written to the cache (`cacheWriteTokens`); on subsequent calls within the cache window the same prefix is read back cheaply (`cachedTokens`). With persistence enabled, these values are stored on the execution's usage for cost tracking.

## Notes

- Caching only helps once the cacheable prefix crosses the provider's minimum (around 1024 tokens). Below that, providers ignore it — there is no penalty.
- Anthropic's cache entries are ephemeral (~5 minute TTL). Keep turns flowing to benefit from reads.
- Caching composes with [conversation history](/guides/conversations) and [media replay](/guides/conversations#replaying-attached-media-on-later-turns): the replayed prefix is exactly what gets cached.
