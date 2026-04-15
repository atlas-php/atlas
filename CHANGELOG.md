# Changelog

All notable changes to Atlas will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com), and this project adheres to [Semantic Versioning](https://semver.org).

[All Releases](https://github.com/atlas-php/atlas/releases)

---

## [v3.0.1](https://github.com/atlas-php/atlas/releases/tag/v3.0.1) - 2026-04-14

### Changed

- Replaced unmaintained `textalk/websocket ^1.6` with its maintained successor `phrity/websocket ^3.0` (same author, same `WebSocket\Client` namespace). Unblocks `psr/http-message ^2.0` in consuming apps, which was previously forced to `^1.0` by the old dependency.

### Internal

- `WebSocketConnection` now unwraps `$client->receive()` via `Message::getContent()` (v3 returns `Message`, v1 returned a stringable).
- `ConnectionTimeoutException` replaces `TimeoutException` in receive-path error handling.
- Voice handler `Client` construction switched from the removed `['headers' => [...]]` option to `Client::addHeader()` calls.
- All 2548 tests pass; no public API changes for consumers.

---

## [v2.5.1](https://github.com/atlas-php/atlas/releases/tag/v2.5.1) - 2026-03-17

### Changed

- Model listing now returns `list<string>` instead of `list<array{id, name}>` — simpler output, no more empty `name` fields
- Fixed provider support table (ElevenLabs has a models endpoint; Perplexity/VoyageAI do not)

---

## [v2.5.0](https://github.com/atlas-php/atlas/releases/tag/v2.5.0) - 2026-03-16

### Provider Model Listing

List available models from any AI provider — the first PHP AI SDK to ship this.

```php
Atlas::models('openai')->all();       // cached list of models
Atlas::models('openai')->refresh();   // force fresh from API
Atlas::models('openai')->clear();     // clear cache
Atlas::models('openai')->has();       // check if provider supports listing
```

- 10 of 13 Prism providers supported (Perplexity, VoyageAI, Z have no models endpoint)
- Automatic caching with configurable TTL, store, and enable/disable
- Ollama fallback from `/v1/models` to native `/api/tags`
- Sandbox `atlas:models` command with `--all`, `--refresh`, `--clear` flags

### Embedding Defaults & Caching

- Default provider/model config — `->using()` no longer required on every call
- Built-in `CacheEmbeddings` pipeline middleware with per-request overrides
- Sandbox `--cache` and `--cache-demo` flags on `atlas:embed`

### Other

- Updated Prism to v0.99.22 (new providers, GPT-5 reasoning, multimodal embeddings)
- Updated Laravel Pint to v1.29.0
- 66 new tests

### Breaking Changes

None.
